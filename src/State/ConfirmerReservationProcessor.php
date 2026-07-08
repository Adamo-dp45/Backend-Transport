<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Service\Paiement\PaiementProviderInterface;
use App\Entity\Reservation;
use App\Entity\User;
use App\Security\GareGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Confirmation d'une réservation = ENCAISSEMENT DU PAIEMENT uniquement (simulé pour l'instant).
 *
 * Le paiement ne dépend PAS du car : un client peut payer à l'avance (mobile), avant même qu'un car
 * soit affecté au voyage. La réservation passe CONFIRMEE + PAYE et « tient » fermement sa place, mais
 * AUCUN siège n'est encore attribué. L'attribution du siège et l'émission du billet se font ensuite,
 * en étape séparée (EmettreBilletReservationProcessor), à la gare ou une fois le car affecté.
 *
 * → Le « bon de réservation » (payé, sans siège) est ainsi distinct du billet de gare (avec siège).
 */
class ConfirmerReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private PaiementProviderInterface $paiement,
        private GareGuard $gareGuard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Reservation $reservation */
        $reservation = $data;
        /** @var User $user */
        $user = $this->security->getUser();

        // Seule la gare de MONTÉE agit sur la réservation (au guichet). Le paiement mobile passe par
        // le flux public (PaiementWebhookProcessor), non concerné par cette garde.
        $this->gareGuard->assertEstGare($user, $reservation->getGare(), 'Seule la gare de montée peut encaisser cette réservation');

        if ($reservation->getStatut() !== ReservationStatus::STATUT_EN_ATTENTE->value) {
            throw new BadRequestHttpException('Cette réservation ne peut plus être payée (déjà payée, annulée ou expirée)');
        }
        if ($reservation->getDateexpiration() !== null && $reservation->getDateexpiration() <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Cette réservation a expiré');
        }
        if ($reservation->getVoyage()->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage est clôturé');
        }

        return $this->em->wrapInTransaction(function () use ($reservation, $user, $operation, $uriVariables, $context) {
            // Paiement (simulé) — le vrai prestataire Mobile Money se branchera ici.
            $resultat = $this->paiement->payer($reservation);
            if (!$resultat->succes) {
                throw new BadRequestHttpException('Paiement refusé : ' . ($resultat->message ?? 'erreur inconnue'));
            }

            $reservation
                ->setEtatpaiement('PAYE')
                ->setReferencepaiement($resultat->reference)
                ->setDatepaiement(new \DateTimeImmutable())
                ->setStatut(ReservationStatus::STATUT_CONFIRMEE->value)
                ->setUpdatedBy($user?->getId());

            return $this->processor->process($reservation, $operation, $uriVariables, $context);
        });
    }
}
