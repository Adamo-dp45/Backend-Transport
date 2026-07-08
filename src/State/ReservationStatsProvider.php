<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\ReservationStats\ReservationParSourceDto;
use App\Entity\Output\ReservationStats\ReservationParStatutDto;
use App\Entity\Output\ReservationStats\ReservationStatistiqueOutput;
use App\Entity\Output\ReservationStats\TopTrajetReservationDto;
use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistiques du module RÉSERVATION (période) : volume, répartition par statut/canal, taux de
 * conversion, recette des réservations payées, no-shows (forfaits) et top trajets réservés.
 */
class ReservationStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private ReservationRepository $reservationRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ReservationStatistiqueOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $ide = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($this->requestStack->getCurrentRequest());

        $rawStatut = $this->reservationRepository->statsParStatut($debut, $fin, $ide);
        $rawSource = $this->reservationRepository->statsParSource($debut, $fin, $ide);

        $totalParStatut = [];
        $recetteTotale = 0;
        $parStatut = [];
        foreach ($rawStatut as $row) {
            $total = (int) $row['total'];
            $recette = (int) $row['recette'];
            $totalParStatut[$row['statut']] = $total;
            $recetteTotale += $recette;
            $parStatut[] = new ReservationParStatutDto(statut: $row['statut'], total: $total, recette: $recette);
        }

        $total = array_sum($totalParStatut);
        $confirmees = $totalParStatut[ReservationStatus::STATUT_CONFIRMEE->value] ?? 0;
        $enAttente = $totalParStatut[ReservationStatus::STATUT_EN_ATTENTE->value] ?? 0;
        $aRegulariser = $totalParStatut[ReservationStatus::STATUT_A_REGULARISER->value] ?? 0;
        $expirees = $totalParStatut[ReservationStatus::STATUT_EXPIREE->value] ?? 0;
        $annulees = $totalParStatut[ReservationStatus::STATUT_ANNULEE->value] ?? 0;

        // « Converties » = réservations PAYÉES : confirmées + à régulariser (payées mais no-show récupérable)
        $payees = $confirmees + $aRegulariser;

        $parSource = [];
        $mobile = 0;
        foreach ($rawSource as $row) {
            $t = (int) $row['total'];
            if ($row['source'] === 'MOBILE') {
                $mobile = $t;
            }
            $parSource[] = new ReservationParSourceDto(source: $row['source'], total: $t);
        }

        $topTrajets = array_map(
            fn(array $r) => new TopTrajetReservationDto(montee: $r['montee'], descente: $r['descente'], total: (int) $r['total']),
            $this->reservationRepository->topTrajets($debut, $fin, $ide)
        );

        return new ReservationStatistiqueOutput(
            total: $total,
            confirmees: $confirmees,
            enAttente: $enAttente,
            aRegulariser: $aRegulariser,
            expirees: $expirees,
            annulees: $annulees,
            billetsEmis: $this->reservationRepository->countBilletsEmis($debut, $fin, $ide),
            noShows: $this->reservationRepository->countNoShows($debut, $fin, $ide),
            tauxConversion: $total > 0 ? round($payees * 100 / $total, 1) : 0.0,
            recetteConfirmee: $recetteTotale,
            partMobile: $total > 0 ? round($mobile * 100 / $total, 1) : 0.0,
            parStatut: $parStatut,
            parSource: $parSource,
            topTrajets: $topTrajets
        );
    }
}
