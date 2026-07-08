<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Compléments billetterie (vue admin) : désistements (reports/annulations), remises (total, par
 * bénéficiaire, par catégorie), matrice Origine-Destination, heures de pointe. JSON simple (pas de DTO).
 */
final class BilletterieStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/billetterie/details', name: 'api_stats_billetterie_details', methods: ['GET'])]
    public function details(Request $request, Security $security, TicketRepository $ticketRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Désistements ──
        $statuts = ['VALIDE' => 0, 'REPORTE' => 0, 'ANNULE' => 0];
        foreach ($ticketRepository->compteParStatut($debut, $fin, $ent) as $r) {
            $statuts[$r['statut']] = (int) $r['nb'];
        }
        $totalBillets = array_sum($statuts);
        $desistements = [
            'valide' => $statuts['VALIDE'],
            'reporte' => $statuts['REPORTE'],
            'annule' => $statuts['ANNULE'],
            'total' => $statuts['REPORTE'] + $statuts['ANNULE'],
            'taux' => $totalBillets > 0 ? (int) round(($statuts['REPORTE'] + $statuts['ANNULE']) / $totalBillets * 100) : 0,
        ];

        // ── Remises (total, par bénéficiaire, par catégorie dérivée) ──
        $remiseTotal = 0;
        $remiseNb = 0;
        $parBeneficiaire = [];
        $parCategorie = [];
        foreach ($ticketRepository->remisesParBeneficiaire($debut, $fin, $ent) as $r) {
            $t = (int) $r['total'];
            $nb = (int) $r['nb'];
            $remiseTotal += $t;
            $remiseNb += $nb;
            $parBeneficiaire[] = ['nom' => $r['nom'] ?? '—', 'categorie' => $r['categorie'], 'total' => $t, 'nb' => $nb];
            $cat = $r['categorie'] ?: 'Non catégorisé';
            $parCategorie[$cat]['categorie'] = $cat;
            $parCategorie[$cat]['total'] = ($parCategorie[$cat]['total'] ?? 0) + $t;
            $parCategorie[$cat]['nb'] = ($parCategorie[$cat]['nb'] ?? 0) + $nb;
        }
        $parCategorie = array_values($parCategorie);
        usort($parCategorie, fn ($a, $b) => $b['total'] <=> $a['total']);

        // ── Matrice O-D ──
        $cells = [];
        $garesMap = [];
        foreach ($ticketRepository->matriceOD($debut, $fin, $ent) as $r) {
            $cells[$r['deId']][$r['versId']] = (int) $r['nb'];
            $garesMap[$r['deId']] = $r['de'];
            $garesMap[$r['versId']] = $r['vers'];
        }
        asort($garesMap); // tri alphabétique des gares
        $gares = [];
        foreach ($garesMap as $id => $lib) {
            $gares[] = ['id' => $id, 'libelle' => $lib];
        }

        // ── Heures de pointe (00–23, trous comblés) ──
        $heuresMap = [];
        foreach ($ticketRepository->billetsParHeure($debut, $fin, $ent) as $r) {
            $heuresMap[(int) $r['heure']] = (int) $r['nb'];
        }
        $heures = [];
        for ($h = 0; $h < 24; $h++) {
            $heures[] = ['heure' => $h, 'nb' => $heuresMap[$h] ?? 0];
        }

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'desistements' => $desistements,
            'remises' => ['total' => $remiseTotal, 'nb' => $remiseNb, 'parBeneficiaire' => $parBeneficiaire, 'parCategorie' => $parCategorie],
            'matriceOD' => ['gares' => $gares, 'cells' => $cells],
            'heures' => $heures,
        ]);
    }
}
