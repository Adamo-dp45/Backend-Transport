<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\AlerteRepository;
use App\Repository\GareRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques des ALERTES d'exploitation sur une période (espace propriétaire) : volumes par famille,
 * sévérité, statut et gare, évolution par jour, taux et délai moyen de résolution. Vue admin d'entreprise
 * (sans audience). JSON simple, consommé par la page FT.
 */
final class AlerteStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/alertes', name: 'api_stats_alertes', methods: ['GET'])]
    public function index(
        Request $request,
        Security $security,
        AlerteRepository $alerteRepository,
        GareRepository $gareRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        $stats = $alerteRepository->statistiques($debut, $fin, $ent);

        // Libellés des gares concernées (idgare => libellé) pour l'affichage.
        $gareIds = array_filter(array_keys($stats['parGare']), static fn ($id) => $id > 0);
        $libelles = [];
        if ($gareIds) {
            foreach ($gareRepository->findBy(['id' => $gareIds]) as $g) {
                $libelles[$g->getId()] = $g->getLibelle();
            }
        }
        $parGare = [];
        foreach ($stats['parGare'] as $gid => $nb) {
            $parGare[] = [
                'libelle' => $gid > 0 ? ($libelles[$gid] ?? ('Gare #' . $gid)) : 'Sans gare (entreprise)',
                'nb' => $nb,
            ];
        }
        usort($parGare, static fn ($a, $b) => $b['nb'] <=> $a['nb']);

        $stats['parGare'] = $parGare;
        $stats['parFamille'] = $this->enListe($stats['parFamille']);
        $stats['parSeverite'] = $this->enListe($stats['parSeverite']);
        $stats['parStatut'] = $this->enListe($stats['parStatut']);

        $parJour = [];
        foreach ($stats['parJour'] as $jour => $nb) {
            $parJour[] = ['label' => $jour, 'nb' => $nb];
        }
        $stats['parJour'] = $parJour;

        return new JsonResponse($stats);
    }

    /** Transforme une map {libellé: nb} en liste triée par volume décroissant. */
    private function enListe(array $map): array
    {
        $out = [];
        foreach ($map as $libelle => $nb) {
            $out[] = ['libelle' => $libelle, 'nb' => $nb];
        }
        usort($out, static fn ($a, $b) => $b['nb'] <=> $a['nb']);

        return $out;
    }
}
