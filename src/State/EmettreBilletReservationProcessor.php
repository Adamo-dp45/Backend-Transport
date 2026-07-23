<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Service\EmissionBilletService;
use App\Entity\Reservation;
use App\Entity\User;
use App\Security\GareGuard;
use App\Security\VoyageGuard;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Émission du BILLET (avec siège) à partir d'une réservation PAYÉE — l'étape « bon de réservation →
 * billet de gare ». Le car doit être affecté (le siège vient du car). Sérialisé sous verrou
 * pessimiste sur le voyage. La réservation reste CONFIRMEE et pointe désormais vers le billet.
 *
 * No-show : passé la deadline, la réservation n'est plus émise ici — elle bascule A_REGULARISER
 * (report + pénalité), cf. {@see RegulariserReservationProcessor}.
 */
class EmettreBilletReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private EmissionBilletService $emissionBillet,
        private GareGuard $gareGuard,
        private VoyageGuard $voyageGuard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Reservation $reservation */
        $reservation = $data;
        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        // Seule la gare de MONTÉE émet le billet (le passager embarque à sa gare).
        $this->gareGuard->assertEstGare($user, $reservation->getGare(), 'Seule la gare de montée peut émettre le billet de cette réservation');

        if ($reservation->getStatut() !== ReservationStatus::STATUT_CONFIRMEE->value
            || $reservation->getEtatpaiement() !== 'PAYE') {
            throw new BadRequestHttpException('Le billet ne peut être émis que pour une réservation payée (confirmée)');
        }
        if ($reservation->getTicket() !== null) {
            throw new BadRequestHttpException('Le billet de cette réservation a déjà été émis');
        }
        // Passé la deadline, le bon est périmé (no-show) : il passe A_REGULARISER (report + pénalité,
        // sauf si le départ avait été avancé par la compagnie — l'absence ne lui est pas imputable).
        if ($reservation->getDateexpiration() !== null && $reservation->getDateexpiration() <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException(
                'Le délai de retrait de ce bon est dépassé : régularisez la réservation (report sur un nouveau départ'
                . ($reservation->isPenaliteexoneree() ? ', sans pénalité : le départ a été avancé)' : ' avec pénalité)')
            );
        }

        $voyage = $reservation->getVoyage();
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage est clôturé');
        }
        /*
            Le car doit encore être là. « Dépassée » et non « atteinte » : quand le car EST à la gare
            de montée, c'est précisément le moment où l'on embarque et où l'on émet — seul un car qui
            en est REPARTI rend le billet inutile.
        */
        $this->voyageGuard->assertMonteeNonDepassee(
            $voyage,
            $reservation->getGare(),
            'Le car a déjà quitté cette gare : régularisez la réservation sur un autre départ.'
        );
        if ($voyage->getCar() === null) {
            throw new BadRequestHttpException('Affectez un car au voyage avant d\'émettre le billet (attribution du siège)');
        }

        return $this->em->wrapInTransaction(function () use (
            $reservation, $voyage, $entrepriseId, $user, $operation, $uriVariables, $context
        ) {
            $this->em->lock($voyage, LockMode::PESSIMISTIC_WRITE);

            // Prix VERROUILLÉ à la réservation (pas recalculé pour une émission à l'heure)
            $ticket = $this->emissionBillet->creer($reservation, $voyage, (int) $reservation->getPrix(), $entrepriseId, $user);
            $reservation->setTicket($ticket)->setUpdatedBy($user->getId());

            return $this->processor->process($reservation, $operation, $uriVariables, $context);
        });
    }
}
