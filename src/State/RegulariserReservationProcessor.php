<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\EmissionBilletService;
use App\Domain\Service\ReservationEcheanceService;
use App\Domain\Service\ReservationRegularisationService;
use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\VoyageRepository;
use App\Security\GareGuard;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * RÉGULARISATION d'une réservation payée en no-show (statut A_REGULARISER) : report sur un nouveau
 * départ (query ?voyage=) desservant le même tronçon, avec PÉNALITÉ (politique compagnie) et
 * COMPLÉMENT tarifaire si le tarif du jour a augmenté. Émet le billet (siège attribué) et rattache la
 * réservation au nouveau départ. Sérialisé sous verrou pessimiste sur le départ cible.
 */
class RegulariserReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private VoyageRepository $voyageRepository,
        private ReservationRegularisationService $regularisation,
        private EmissionBilletService $emissionBillet,
        private ActiviteLogger $activiteLogger,
        private GareGuard $gareGuard,
        private ReservationEcheanceService $echeance
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

        // Seule la gare de MONTÉE peut régulariser (report + émission du billet) la réservation.
        $this->gareGuard->assertEstGare($user, $reservation->getGare(), 'Seule la gare de montée peut régulariser cette réservation');

        if ($reservation->getStatut() !== ReservationStatus::STATUT_A_REGULARISER->value) {
            throw new BadRequestHttpException('Seule une réservation à régulariser peut être reportée');
        }

        $voyageId = (int) $this->requestStack->getCurrentRequest()?->query->get('voyage');
        if ($voyageId <= 0) {
            throw new BadRequestHttpException('Départ de report obligatoire (paramètre « voyage »)');
        }

        $cible = $this->voyageRepository->findOneBy(['id' => $voyageId, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
        if (!$cible) {
            throw new BadRequestHttpException('Départ de report introuvable');
        }
        if ($cible->getCar() === null) {
            throw new BadRequestHttpException('Affectez un car au départ de report avant de régulariser (attribution du siège)');
        }

        // Décompte à encaisser (pénalité + complément) — valide aussi le tronçon et la date du cible
        $calcul = $this->regularisation->calculer($reservation, $cible);

        return $this->em->wrapInTransaction(function () use (
            $reservation, $cible, $calcul, $entrepriseId, $user, $operation, $uriVariables, $context
        ) {
            $this->em->lock($cible, LockMode::PESSIMISTIC_WRITE);

            $ancienVoyage = $reservation->getVoyage()?->getCodevoyage() ?? '?';

            // Émet le billet sur le NOUVEAU départ, au nouveau prix (initial + complément)
            $ticket = $this->emissionBillet->creer($reservation, $cible, $calcul['nouveauPrix'], $entrepriseId, $user);

            $reservation
                ->setVoyage($cible)
                ->setPrix($calcul['nouveauPrix'])
                ->setPenalitemontant($calcul['penalite'])
                ->setMontantcomplement($calcul['complement'])
                ->setStatut(ReservationStatus::STATUT_CONFIRMEE->value)
                ->setTicket($ticket)
                // L'exonération couvrait CE report-là : une fois consommée, la réservation repart
                // sur un pied normal (un no-show ultérieur, lui, serait bien du fait du client).
                ->setPenaliteexoneree(false)
                ->setUpdatedBy($user->getId());

            /*
                La réservation change de départ : son échéance doit suivre, sinon elle continue de
                pointer sur l'ANCIEN départ — une date passée, incohérente avec le voyage désormais
                rattaché. Sans effet fonctionnel (le billet est émis dans la foulée, ce qui exclut la
                réservation des holds comme du cron), mais on ne laisse pas une donnée fausse en base.
            */
            $presentation = $this->echeance->limitePresentationPour($cible, $reservation->getGare(), $entrepriseId);
            if ($presentation !== null) {
                $reservation->setDateexpiration($presentation);
            }

            $this->activiteLogger->log(
                ActiviteLogger::RESERVATION_REGULARISEE,
                sprintf(
                    'Réservation %s régularisée : report %s → %s (pénalité %s, complément %d FCFA) — billet %s',
                    $reservation->getCode(),
                    $ancienVoyage,
                    $cible->getCodevoyage(),
                    $calcul['exoneree'] ? 'exonérée (départ avancé par la compagnie)' : $calcul['penalite'] . ' FCFA',
                    $calcul['complement'],
                    $ticket->getCodeticket()
                ),
                'Reservation',
                $reservation->getId()
            );

            return $this->processor->process($reservation, $operation, $uriVariables, $context);
        });
    }
}
