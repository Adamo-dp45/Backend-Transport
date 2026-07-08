<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use App\Repository\DepannageRepository;
use App\Repository\PieceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stock & Approvisionnement (vue admin) : valeur du stock (snapshot), consommation / rotation et top
 * pièces consommées (via dépannages de la période), achats & coût d'appro par fournisseur (appros de la période).
 */
final class StockStatsDetailsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/stock/details', name: 'api_stats_stock_details', methods: ['GET'])]
    public function details(
        Request $request,
        Security $security,
        PieceRepository $pieceRepository,
        DepannageRepository $depannageRepository,
        ApprovisionnementRepository $approvisionnementRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Valeur du stock (snapshot courant) ──
        $vs = $pieceRepository->valeurStock($ent);
        $valeurStock = [
            'valeur' => (int) ($vs['valeur'] ?? 0),
            'unites' => (int) ($vs['unites'] ?? 0),
            'nbPieces' => (int) ($vs['nbpieces'] ?? 0),
        ];

        // ── Consommation (via dépannages de la période) ──
        $allConso = $depannageRepository->topPiecesConsommees($debut, $fin, $ent, 1000);
        $qteConsommee = array_sum(array_map(fn ($r) => (int) $r['quantite'], $allConso));
        $coutConsomme = array_sum(array_map(fn ($r) => (int) $r['cout'], $allConso));
        $topPieces = array_map(fn ($r) => [
            'libelle' => $r['libelle'] ?? '—',
            'quantite' => (int) $r['quantite'],
            'cout' => (int) $r['cout'],
        ], array_slice($allConso, 0, 12));

        // Rotation = consommation période / unités en stock (×100)
        $rotation = $valeurStock['unites'] > 0 ? round($qteConsommee / $valeurStock['unites'] * 100, 1) : 0;

        // ── Achats par fournisseur (appros de la période) ──
        $achats = array_map(fn ($r) => [
            'libelle' => $r['libelle'] ?? '—',
            'nbAppros' => (int) $r['nbappros'],
            'montant' => (int) $r['montant'],
        ], $approvisionnementRepository->achatsParFournisseur($debut, $fin, $ent));

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'valeurStock' => $valeurStock,
            'consommation' => [
                'quantiteTotale' => $qteConsommee,
                'coutTotal' => $coutConsomme,
                'rotation' => $rotation,
                'topPieces' => $topPieces,
            ],
            'achatsParFournisseur' => $achats,
        ]);
    }
}
