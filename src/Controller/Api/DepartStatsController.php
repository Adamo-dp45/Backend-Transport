<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques des DÉPARTS EFFECTIFS (notion de départ partiel). Un voyage part de sa gare de
 * provenance (v.gareprovenance) : c'est l'origine de la ligne pour un départ « complet », ou une gare
 * intermédiaire pour un départ « partiel ». On expose la segmentation complets/partiels, la recette
 * captée en cours de ligne et la recette par gare de départ effective. JSON simple (vue admin).
 */
final class DepartStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/gares/departs', name: 'api_stats_gares_departs', methods: ['GET'])]
    public function index(
        Request $request,
        Security $security,
        TicketRepository $ticketRepository,
        VoyageRepository $voyageRepository,
        ReservationRepository $reservationRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Segmentation des voyages (complets vs partiels) ──
        $types = $voyageRepository->countParTypeDepart($debut, $fin, $ent);
        $voyagesTotal = $types['complets'] + $types['partiels'];

        // ── Recette billets ventilée par type de départ ──
        $recetteType = $ticketRepository->recetteParTypeDepart($debut, $fin, $ent);
        $billetsTotal = $recetteType['complet']['billets'] + $recetteType['partiel']['billets'];

        // ── Recette par gare de départ effective (billets, avec part captée EN TANT QU'INTERMÉDIAIRE) ──
        $parGare = [];
        $init = function (int $gid, array $r) use (&$parGare): void {
            if (!isset($parGare[$gid])) {
                $parGare[$gid] = [
                    'libelle' => $r['libelle'] ?? '—',
                    'ville' => $r['ville'] ?? null,
                    'billets' => 0,
                    'recette' => 0,
                    'billetsPartiels' => 0,
                    'recettePartielle' => 0,
                    'reservations' => 0,
                    'recetteReservations' => 0,
                ];
            }
        };
        foreach ($ticketRepository->departsParGareProvenance($debut, $fin, $ent) as $r) {
            $gid = (int) $r['gareid'];
            $init($gid, $r);
            $estPartiel = (int) $r['gareid'] !== (int) $r['origineid'];
            $parGare[$gid]['billets'] += (int) $r['billets'];
            $parGare[$gid]['recette'] += (int) $r['recette'];
            if ($estPartiel) {
                $parGare[$gid]['billetsPartiels'] += (int) $r['billets'];
                $parGare[$gid]['recettePartielle'] += (int) $r['recette'];
            }
        }

        // ── Réservations payées, mêmes axes (départ effectif du voyage) : ajoutées à la recette des départs ──
        $resaComplet = 0;
        $resaPartiel = 0;
        foreach ($reservationRepository->departsPayesParGareProvenance($debut, $fin, $ent) as $r) {
            $gid = (int) $r['gareid'];
            $init($gid, $r);
            $estPartiel = (int) $r['gareid'] !== (int) $r['origineid'];
            $rec = (int) $r['recette'];
            $parGare[$gid]['recette'] += $rec;
            $parGare[$gid]['reservations'] += (int) $r['nbreservations'];
            $parGare[$gid]['recetteReservations'] += $rec;
            if ($estPartiel) {
                $parGare[$gid]['recettePartielle'] += $rec;
                $resaPartiel += $rec;
            } else {
                $resaComplet += $rec;
            }
        }
        $parGare = array_values($parGare);
        usort($parGare, fn ($a, $b) => $b['recette'] <=> $a['recette']);

        // ── Totaux recette des départs = billets + réservations payées ──
        $recetteComplet = $recetteType['complet']['recette'] + $resaComplet;
        $recettePartiel = $recetteType['partiel']['recette'] + $resaPartiel;
        $recetteTotale = $recetteComplet + $recettePartiel;

        return new JsonResponse([
            'voyagesComplets' => $types['complets'],
            'voyagesPartiels' => $types['partiels'],
            'voyagesTotal' => $voyagesTotal,
            'partVoyagesPartiels' => $voyagesTotal > 0 ? (int) round($types['partiels'] / $voyagesTotal * 100) : 0,
            'recetteTotale' => $recetteTotale,
            'recetteComplet' => $recetteComplet,
            'recettePartiel' => $recettePartiel,
            'recetteReservations' => $resaComplet + $resaPartiel,
            'billetsTotal' => $billetsTotal,
            'billetsPartiel' => $recetteType['partiel']['billets'],
            'partRecettePartielle' => $recetteTotale > 0 ? (int) round($recettePartiel / $recetteTotale * 100) : 0,
            'parGare' => $parGare,
        ]);
    }
}
