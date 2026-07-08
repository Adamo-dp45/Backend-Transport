<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\CarRepository;
use App\Repository\DepannageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Compléments flotte (vue admin) : taux de disponibilité (répartition des véhicules par état, snapshot
 * courant), coût moyen par panne et type de panne le plus fréquent (dépannages de la période, hors annulés).
 */
final class FlotteStatsDetailsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/flotte/details', name: 'api_stats_flotte_details', methods: ['GET'])]
    public function details(
        Request $request,
        Security $security,
        CarRepository $carRepository,
        DepannageRepository $depannageRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Disponibilité (snapshot courant, pas de période) ──
        $etatLabels = ['DISPONIBLE' => 'Disponible', 'EN_VOYAGE' => 'En voyage', 'EN_PANNE' => 'En panne', 'EN_MAINTENANCE' => 'Maintenance'];
        $parEtat = [];
        $total = 0;
        $dispo = 0;
        foreach ($carRepository->countParEtat($ent) as $r) {
            $nb = (int) $r['total'];
            $total += $nb;
            if ($r['etat'] === 'DISPONIBLE') {
                $dispo = $nb;
            }
            $parEtat[] = ['etat' => $etatLabels[$r['etat']] ?? $r['etat'], 'code' => $r['etat'], 'nb' => $nb];
        }

        // ── Pannes (période, hors annulés) ──
        $nb = $depannageRepository->countByPeriode($debut, $fin, $ent);
        $coutTotal = (int) $depannageRepository->coutTotal($debut, $fin, $ent);
        $parType = array_map(fn ($r) => [
            'type' => $r['type'] ?? '—',
            'nb' => (int) $r['nb'],
            'cout' => (int) $r['cout'],
        ], $depannageRepository->parTypePanne($debut, $fin, $ent));

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'disponibilite' => [
                'parEtat' => $parEtat,
                'total' => $total,
                'disponibles' => $dispo,
                'tauxDispo' => $total > 0 ? (int) round($dispo / $total * 100) : 0,
            ],
            'pannes' => [
                'nb' => $nb,
                'coutTotal' => $coutTotal,
                'coutMoyen' => $nb > 0 ? (int) round($coutTotal / $nb) : 0,
                'parType' => $parType,
                'typeFrequent' => $parType[0]['type'] ?? null,
            ],
        ]);
    }
}
