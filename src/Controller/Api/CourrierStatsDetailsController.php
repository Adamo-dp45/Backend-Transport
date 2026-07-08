<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\CourrierRepository;
use App\Repository\DetailcourrierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Compléments courrier (vue admin) : colis par tranche de valeur (grille tarifaire) et délai moyen de
 * livraison (datelivraison − createdAt des courriers LIVRE). Le statut / en attente / en transit /
 * taux perte-annulation viennent déjà de /api/stats/courriers.
 */
final class CourrierStatsDetailsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/courriers/details', name: 'api_stats_courriers_details', methods: ['GET'])]
    public function details(
        Request $request,
        Security $security,
        DetailcourrierRepository $detailcourrierRepository,
        CourrierRepository $courrierRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Colis par tranche de valeur ──
        $parTranche = array_map(fn ($r) => [
            'libelle' => $r['libelle'] ?: ('≥ ' . $r['valeurmin']),
            'nb' => (int) $r['nb'],
            'recette' => (int) $r['recette'],
        ], $detailcourrierRepository->parTrancheValeur($debut, $fin, $ent));

        // ── Colis perdus (niveau colis : un colis peut être perdu sans que tout le courrier le soit) ──
        $pertes = $detailcourrierRepository->comptePertes($debut, $fin, $ent);

        // ── Délai moyen de livraison (datelivraison - createdAt sur LIVRE) ──
        $totalSec = 0;
        $n = 0;
        foreach ($courrierRepository->livresPourDelai($debut, $fin, $ent) as $r) {
            if ($r['createdAt'] instanceof \DateTimeInterface && $r['datelivraison'] instanceof \DateTimeInterface) {
                $diff = $r['datelivraison']->getTimestamp() - $r['createdAt']->getTimestamp();
                if ($diff >= 0) {
                    $totalSec += $diff;
                    $n++;
                }
            }
        }

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'parTranche' => $parTranche,
            'delaiMoyenHeures' => $n > 0 ? round($totalSec / $n / 3600, 1) : null,
            'nbLivres' => $n,
            'colisPerdus' => $pertes['perdus'],
            'colisTotal' => $pertes['total'],
            'tauxPerteColis' => $pertes['total'] > 0 ? round($pertes['perdus'] / $pertes['total'] * 100, 1) : 0,
        ]);
    }
}
