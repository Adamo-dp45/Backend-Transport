<?php

namespace App\Controller\Api;

use App\Domain\Service\ReservationEcheanceService;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\PassageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques de PONCTUALITÉ (vue admin) : à partir des passages réels (arrivée horodatée) et de
 * l'heure de passage prévue (somme des tronçons), calcule le retard à chaque étape puis agrège.
 *
 * Retard = arrivée réelle − heure de passage prévue (négatif = en avance). « À l'heure » = retard ≤ seuil
 * (par défaut 10 min, réglable via ?seuil). JSON simple (pas de DTO).
 */
final class PonctualiteStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/ponctualite', name: 'api_stats_ponctualite', methods: ['GET'])]
    public function index(
        Request $request,
        Security $security,
        PassageRepository $passageRepository,
        ReservationEcheanceService $echeance
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);
        $seuil = max(0, (int) $request->query->get('seuil', 10));

        $passages = $passageRepository->findAvecArriveePourPeriode($debut, $fin, $ent);

        $tousRetards = [];
        $tempsArrets = [];
        $parGare = [];   // gareId => ['libelle', 'retards'=>[], 'arrets'=>[]]
        $parLigne = [];  // ligneId => ['code', 'retards'=>[]]
        $parOrdre = [];  // ordre => ['retards'=>[]]
        $ordreParLigneGare = []; // cache "ligneId:gareId" => ordre

        foreach ($passages as $passage) {
            $voyage = $passage->getVoyage();
            $gare = $passage->getGare();
            $arrivee = $passage->getArriveeReelle();
            $prevue = $echeance->heurePassage($voyage, $gare);
            if ($voyage === null || $gare === null || $arrivee === null || $prevue === null) {
                continue;
            }
            $retard = (int) round(($arrivee->getTimestamp() - $prevue->getTimestamp()) / 60);
            $tousRetards[] = $retard;

            // Par gare
            $gid = $gare->getId();
            $parGare[$gid]['libelle'] ??= $gare->getLibelle();
            $parGare[$gid]['retards'][] = $retard;
            $tempsArret = $passage->getTempsArretMinutes();
            if ($tempsArret !== null) {
                $parGare[$gid]['arrets'][] = $tempsArret;
                $tempsArrets[] = $tempsArret;
            }

            // Par ligne
            $ligne = $voyage->getLigne();
            if ($ligne !== null) {
                $lid = $ligne->getId();
                $parLigne[$lid]['code'] ??= $ligne->getCodeligne();
                $parLigne[$lid]['retards'][] = $retard;

                // Position (ordre) de la gare sur la ligne → évolution du retard le long du trajet
                $key = $lid . ':' . $gid;
                if (!isset($ordreParLigneGare[$key])) {
                    $ordreParLigneGare[$key] = null;
                    foreach ($ligne->getArrets() as $arret) {
                        if ($arret->getGare()?->getId() === $gid) {
                            $ordreParLigneGare[$key] = (int) $arret->getOrdre();
                            break;
                        }
                    }
                }
                $ordre = $ordreParLigneGare[$key];
                if ($ordre !== null) {
                    $parOrdre[$ordre]['retards'][] = $retard;
                }
            }
        }

        $moyenne = static fn (array $r): float => $r === [] ? 0.0 : round(array_sum($r) / count($r), 1);
        $tauxALheure = static fn (array $r): int => $r === [] ? 0 : (int) round(100 * count(array_filter($r, static fn ($x) => $x <= $seuil)) / count($r));

        // ── Par gare (trié par retard moyen décroissant) ──
        $gares = [];
        foreach ($parGare as $gid => $d) {
            $gares[] = [
                'gareId' => $gid,
                'libelle' => $d['libelle'],
                'nbPassages' => count($d['retards']),
                'retardMoyen' => $moyenne($d['retards']),
                'tauxALheure' => $tauxALheure($d['retards']),
                'tempsArretMoyen' => isset($d['arrets']) ? $moyenne($d['arrets']) : null,
            ];
        }
        usort($gares, static fn ($a, $b) => $b['retardMoyen'] <=> $a['retardMoyen']);

        // ── Par ligne ──
        $lignes = [];
        foreach ($parLigne as $lid => $d) {
            $lignes[] = [
                'ligneId' => $lid,
                'code' => $d['code'],
                'nbPassages' => count($d['retards']),
                'retardMoyen' => $moyenne($d['retards']),
                'tauxALheure' => $tauxALheure($d['retards']),
            ];
        }
        usort($lignes, static fn ($a, $b) => $b['retardMoyen'] <=> $a['retardMoyen']);

        // ── Évolution du retard le long du trajet (par position d'arrêt) ──
        ksort($parOrdre);
        $evolution = [];
        foreach ($parOrdre as $ordre => $d) {
            $evolution[] = [
                'ordre' => $ordre,
                'nbPassages' => count($d['retards']),
                'retardMoyen' => $moyenne($d['retards']),
            ];
        }

        return new JsonResponse([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'seuilALheureMinutes' => $seuil,
            'global' => [
                'nbPassages' => count($tousRetards),
                'retardMoyen' => $moyenne($tousRetards),
                'tauxALheure' => $tauxALheure($tousRetards),
                'tempsArretMoyen' => $moyenne($tempsArrets),
                'nbEnRetard' => count(array_filter($tousRetards, static fn ($x) => $x > $seuil)),
            ],
            'parGare' => $gares,
            'parLigne' => $lignes,
            'evolution' => $evolution,
        ]);
    }
}
