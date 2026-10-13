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

        /*
            « Converties » = réservations PAYÉES : confirmées + à régulariser (payées mais no-show
            récupérable).

            !! NE PAS RÉUTILISER CE NOM DANS LA BOUCLE CI-DESSOUS. L'entonnoir par canal l'a
            écrasé à chaque tour, et le taux global rendait la valeur de la DERNIÈRE source :
            14,3 % au lieu de 42,9 % sur la base de dev, affiché sous le libellé « payées /
            total » d'une carte qui montrait 2 payées sur 7. Aucune erreur, aucun statut : juste
            un chiffre faux, et plus bas des canaux à 33 % et 100 % tous DEUX au-dessus d'un
            « global » censé être leur moyenne — seul ce croisement l'a rendu visible.
        */
        $payeesGlobal = $confirmees + $aRegulariser;

        /*
            L'ENTONNOIR PAR CANAL. 'statsParSource' ne donnait que le VOLUME : on savait quelle part
            des bons vient du mobile, jamais si ce canal honore ce qu'il promet. Un canal qui apporte
            60 % du volume et n'en honore qu'un tiers ne vaut pas celui qui apporte 40 % et les
            honore tous — et seul le croisement statut x source le dit.
        */
        $parSource = [];
        $mobile = 0;
        foreach ($this->reservationRepository->entonnoirParSource($debut, $fin, $ide) as $row) {
            $emises = (int) $row['emises'];
            $payeesCanal = (int) $row['payees'];
            $presentees = (int) $row['presentees'];

            if ($row['source'] === 'MOBILE') {
                $mobile = $emises;
            }

            $parSource[] = new ReservationParSourceDto(
                source: $row['source'],
                total: $emises,
                payees: $payeesCanal,
                presentees: $presentees,
                aRegulariser: (int) $row['aregulariser'],
                perdues: (int) $row['perdues'],
                recette: (int) $row['recette'],
                tauxConversion: $emises > 0 ? round($payeesCanal * 100 / $emises, 1) : 0.0,
                // Rapporte aux PAYEES et non aux emises : un bon jamais paye n'avait pas de client a
                // presenter, et le compter ici ferait porter au canal une deperdition deja comptee.
                tauxPresentation: $payeesCanal > 0 ? round($presentees * 100 / $payeesCanal, 1) : 0.0,
            );
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
            tauxConversion: $total > 0 ? round($payeesGlobal * 100 / $total, 1) : 0.0,
            recetteConfirmee: $recetteTotale,
            partMobile: $total > 0 ? round($mobile * 100 / $total, 1) : 0.0,
            parStatut: $parStatut,
            parSource: $parSource,
            topTrajets: $topTrajets
        );
    }
}
