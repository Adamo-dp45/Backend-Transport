<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Entity\User;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Annulation d'une réservation EN_ATTENTE : passe ANNULEE et libère la place tenue sur le tronçon.
 *
 * Une réservation CONFIRMEE n'est PAS annulable ici — non pas qu'elle ait forcément un billet (le bon
 * payé existe justement sans siège), mais parce qu'elle a été encaissée : la défaire relèverait d'un
 * remboursement, or la politique maison n'en prévoit pas. Le client absent garde sa valeur via la
 * régularisation (report), et le désistement d'un billet déjà émis a son propre circuit.
 */
class AnnulerReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private GareGuard $gareGuard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Reservation $reservation */
        $reservation = $data;

        if($reservation->getStatut() !== ReservationStatus::STATUT_EN_ATTENTE->value) {
            throw new BadRequestHttpException('Seule une réservation en attente peut être annulée (une réservation confirmée a un billet : passez par le désistement).');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        // Seule la gare de MONTÉE peut annuler la réservation (la descente ne fait que la voir).
        $this->gareGuard->assertEstGare($user, $reservation->getGare(), 'Seule la gare de montée peut annuler cette réservation');

        $reservation
            ->setStatut(ReservationStatus::STATUT_ANNULEE->value)
            ->setUpdatedBy($user->getId());

        return $this->processor->process($reservation, $operation, $uriVariables, $context);
    }
}
