<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques « par gare » (analyses inter-gares, vue propriétaire/admin) — groupe « Recettes par gare » :
 *  - classement des gares (recette + volume d'opérations),
 *  - panier moyen par gare (recette / opérations),
 *  - recette par gare × jour (séries / sparklines),
 *  - recette par gare × agent (croisement caisse).
 * Recette encaissée à la gare : montée pour le billet, garedepart pour courrier/bagage. Période ?debut&fin.
 */
final class GareStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/gares', name: 'api_stats_gares', methods: ['GET'])]
    public function gares(
        Request $request,
        Security $security,
        TicketRepository $ticketRepository,
        CourrierRepository $courrierRepository,
        BagageRepository $bagageRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN'); // analyses cross-gare réservées à l'admin entreprise

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Totaux par gare (classement + base du panier moyen) ──
        $gares = [];
        $mergeTotal = function (array $rows, string $recetteKey, string $nbKey) use (&$gares) {
            foreach ($rows as $r) {
                $id = $r['gareid'];
                $gares[$id]['libelle'] = $gares[$id]['libelle'] ?? $r['garelibelle'];
                $gares[$id][$recetteKey] = (int) $r['recette'];
                $gares[$id]['nbOperations'] = ($gares[$id]['nbOperations'] ?? 0) + (int) $r[$nbKey];
            }
        };
        $mergeTotal($ticketRepository->recetteParGare($debut, $fin, $ent), 'recetteBillets', 'nbtickets');
        $mergeTotal($courrierRepository->recetteParGare($debut, $fin, $ent), 'recetteCourriers', 'nbcourriers');
        $mergeTotal($bagageRepository->recetteParGare($debut, $fin, $ent), 'recetteBagages', 'nbbagages');

        // ── Désistements par gare (billets annulés / reportés) ──
        $desist = [];
        foreach ($ticketRepository->desistementsParGare($debut, $fin, $ent) as $r) {
            $id = $r['gareid'];
            $desist[$id] = ['annules' => (int) $r['nbannules'], 'reportes' => (int) $r['nbreportes']];
            $gares[$id]['libelle'] = $gares[$id]['libelle'] ?? $r['garelibelle']; // gare listée même sans recette
        }

        // ── Par gare × jour (séries) ──
        $serie = [];     // gareId => jour => recette
        $joursSet = [];
        $mergeJour = function (array $rows) use (&$serie, &$joursSet) {
            foreach ($rows as $r) {
                $jour = $r['jour'] instanceof \DateTimeInterface ? $r['jour']->format('Y-m-d') : substr((string) $r['jour'], 0, 10);
                $serie[$r['gareid']][$jour] = ($serie[$r['gareid']][$jour] ?? 0) + (int) $r['recette'];
                $joursSet[$jour] = true;
            }
        };
        $mergeJour($ticketRepository->recetteParGareEtJour($debut, $fin, $ent));
        $mergeJour($courrierRepository->recetteParGareEtJour($debut, $fin, $ent));
        $mergeJour($bagageRepository->recetteParGareEtJour($debut, $fin, $ent));
        $joursAxis = array_keys($joursSet);
        sort($joursAxis);

        // ── Par gare × agent ──
        $parAgent = [];  // gareId => agentId => [recette, nb]
        $agentIds = [];
        $mergeAgent = function (array $rows) use (&$parAgent, &$agentIds) {
            foreach ($rows as $r) {
                $aid = $r['agentid'];
                if ($aid === null) {
                    continue;
                }
                $parAgent[$r['gareid']][$aid]['recette'] = ($parAgent[$r['gareid']][$aid]['recette'] ?? 0) + (int) $r['recette'];
                $parAgent[$r['gareid']][$aid]['nb'] = ($parAgent[$r['gareid']][$aid]['nb'] ?? 0) + (int) $r['nb'];
                $agentIds[$aid] = true;
            }
        };
        $mergeAgent($ticketRepository->recetteParGareEtAgent($debut, $fin, $ent));
        $mergeAgent($courrierRepository->recetteParGareEtAgent($debut, $fin, $ent));
        $mergeAgent($bagageRepository->recetteParGareEtAgent($debut, $fin, $ent));
        $noms = empty($agentIds) ? [] : $userRepository->findInfosByIds(array_keys($agentIds));

        // ── Agents actifs rattachés à chaque gare (headcount) ──
        $nbAgents = [];
        foreach ($userRepository->countActifsParGare($ent) as $r) {
            $nbAgents[$r['gareid']] = (int) $r['nb'];
        }

        // ── Assemblage ──
        $parGare = [];
        foreach ($gares as $id => $g) {
            $rb = $g['recetteBillets'] ?? 0;
            $rc = $g['recetteCourriers'] ?? 0;
            $rba = $g['recetteBagages'] ?? 0;
            $total = $rb + $rc + $rba;
            $nbOps = $g['nbOperations'] ?? 0;

            $agents = [];
            foreach (($parAgent[$id] ?? []) as $aid => $vals) {
                $agents[] = [
                    'nom' => trim((($noms[$aid]['prenom'] ?? '') . ' ' . ($noms[$aid]['nom'] ?? ''))) ?: '—',
                    'recette' => $vals['recette'],
                    'nb' => $vals['nb'],
                ];
            }
            usort($agents, fn ($a, $b) => $b['recette'] <=> $a['recette']);

            $parGare[] = [
                'gareId' => $id,
                'libelle' => $g['libelle'] ?? '—',
                'recetteBillets' => $rb,
                'recetteCourriers' => $rc,
                'recetteBagages' => $rba,
                'recetteTotale' => $total,
                'nbOperations' => $nbOps,
                'panierMoyen' => $nbOps > 0 ? (int) round($total / $nbOps) : 0,
                'nbAgents' => $nbAgents[$id] ?? 0,
                'ticketsAnnules' => $desist[$id]['annules'] ?? 0,
                'ticketsReportes' => $desist[$id]['reportes'] ?? 0,
                'serieJour' => array_map(fn ($j) => $serie[$id][$j] ?? 0, $joursAxis),
                'agents' => $agents,
            ];
        }
        usort($parGare, fn ($a, $b) => $b['recetteTotale'] <=> $a['recetteTotale']);

        // ── Recette par COMMERCIAL (ventes à bord) — complément de la recette par gare.
        //    parGare (guichet) + commerciaux (à bord) + réservation (compte admin) partitionnent la
        //    recette billetterie (gare XOR commercial XOR réservation). ──
        $commerciaux = array_map(fn ($r) => [
            'commercialId' => (int) $r['commercialid'],
            'nom' => trim((($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''))) ?: '—',
            'recetteBillets' => (int) $r['recette'],
            'nbtickets' => (int) $r['nbtickets'],
        ], $ticketRepository->recetteParCommercial($debut, $fin, $ent));
        usort($commerciaux, fn ($a, $b) => $b['recetteBillets'] <=> $a['recetteBillets']);

        // Recette du 3e canal (billets issus de réservations, payés sur le compte admin).
        $recetteReservation = (int) $ticketRepository->recettesReservation($debut, $fin, $ent);

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'joursAxis' => $joursAxis,
            'parGare' => $parGare,
            'commerciaux' => $commerciaux,
            'recetteReservation' => $recetteReservation,
        ]);
    }

    /**
     * Trafic & flux passagers : montées (gare de montée) vs descentes (garedescente) par gare,
     * gares les plus fréquentées (montées + descentes), top tronçons (gare → gare) par volume.
     */
    #[Route('/api/stats/gares/trafic', name: 'api_stats_gares_trafic', methods: ['GET'])]
    public function trafic(
        Request $request,
        Security $security,
        TicketRepository $ticketRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        $gares = [];
        foreach ($ticketRepository->recetteParGare($debut, $fin, $ent) as $r) {
            $gares[$r['gareid']]['libelle'] = $r['garelibelle'];
            $gares[$r['gareid']]['montees'] = (int) $r['nbtickets'];
        }
        foreach ($ticketRepository->descentesParGare($debut, $fin, $ent) as $r) {
            $gares[$r['gareid']]['libelle'] = $gares[$r['gareid']]['libelle'] ?? $r['garelibelle'];
            $gares[$r['gareid']]['descentes'] = (int) $r['nb'];
        }

        $parGare = [];
        foreach ($gares as $id => $g) {
            $m = $g['montees'] ?? 0;
            $d = $g['descentes'] ?? 0;
            $parGare[] = [
                'gareId' => $id,
                'libelle' => $g['libelle'] ?? '—',
                'montees' => $m,
                'descentes' => $d,
                'trafic' => $m + $d,
            ];
        }
        usort($parGare, fn ($a, $b) => $b['trafic'] <=> $a['trafic']);

        $topTroncons = array_map(fn ($t) => [
            'de' => $t['de'],
            'vers' => $t['vers'],
            'nb' => (int) $t['nb'],
            'recette' => (int) $t['recette'],
        ], $ticketRepository->topTroncons($debut, $fin, $ent, 12));

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'parGare' => $parGare,
            'topTroncons' => $topTroncons,
        ]);
    }

    /**
     * Exploitation par gare de DÉPART : taux de remplissage (billets vendus / capacité) et
     * places vendues vs restantes, sur les voyages partant de chaque gare dans la période (par datedepartprevue).
     * NB : avec la vente par tronçon un siège peut être revendu sur des segments disjoints → le taux est
     * un indicateur « billets / capacité » (peut dépasser 100 %), pas l'occupation pic.
     */
    #[Route('/api/stats/gares/exploitation', name: 'api_stats_gares_exploitation', methods: ['GET'])]
    public function exploitation(
        Request $request,
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        $gares = [];
        foreach ($voyageRepository->capaciteParGareDepart($debut, $fin, $ent) as $r) {
            $gares[$r['gareid']]['libelle'] = $r['garelibelle'];
            $gares[$r['gareid']]['nbVoyages'] = (int) $r['nbvoyages'];
            $gares[$r['gareid']]['capacite'] = (int) $r['capacite'];
        }
        foreach ($ticketRepository->billetsParGareDepart($debut, $fin, $ent) as $r) {
            $gares[$r['gareid']]['libelle'] = $gares[$r['gareid']]['libelle'] ?? '—';
            $gares[$r['gareid']]['billets'] = (int) $r['billets'];
            $gares[$r['gareid']]['recette'] = (int) $r['recette']; // total des départs (3 canaux réunis)
            $gares[$r['gareid']]['recetteGare'] = (int) $r['recette_gare']; // guichet (caisse gare)
            $gares[$r['gareid']]['recetteCommercial'] = (int) $r['recette_commercial']; // vente à bord
            $gares[$r['gareid']]['recetteReservation'] = (int) $r['recette_reservation']; // compte admin
        }

        $parGare = [];
        foreach ($gares as $id => $g) {
            $cap = $g['capacite'] ?? 0;
            $vendus = $g['billets'] ?? 0;
            $parGare[] = [
                'gareId' => $id,
                'libelle' => $g['libelle'] ?? '—',
                'nbVoyages' => $g['nbVoyages'] ?? 0,
                'capacite' => $cap,
                'placesVendues' => $vendus,
                'placesRestantes' => max(0, $cap - $vendus),
                'recette' => $g['recette'] ?? 0, // recette TOTALE des départs (guichet + à bord + réservation)
                'recetteGare' => $g['recetteGare'] ?? 0, // dont caisse gare (guichet)
                'recetteCommercial' => $g['recetteCommercial'] ?? 0, // dont commercial (à bord)
                'recetteReservation' => $g['recetteReservation'] ?? 0, // dont réservation (compte admin)
                'taux' => $cap > 0 ? (int) round($vendus / $cap * 100) : 0,
            ];
        }
        usort($parGare, fn ($a, $b) => $b['taux'] <=> $a['taux']);

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'parGare' => $parGare,
        ]);
    }

    /**
     * Courrier & Bagage par gare : courriers émis (garedepart) / reçus (garearrivee) / en attente de
     * récupération (RECEPTIONNE) ; bagages émis (garedepart, + poids total) / livrés (garedescente LIVRE).
     */
    #[Route('/api/stats/gares/colis', name: 'api_stats_gares_colis', methods: ['GET'])]
    public function colis(
        Request $request,
        Security $security,
        CourrierRepository $courrierRepository,
        BagageRepository $bagageRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Courriers ──
        $cg = [];
        foreach ($courrierRepository->recetteParGare($debut, $fin, $ent) as $r) { // émis = garedepart
            $cg[$r['gareid']]['libelle'] = $r['garelibelle'];
            $cg[$r['gareid']]['emis'] = (int) $r['nbcourriers'];
        }
        foreach ($courrierRepository->recusParGare($debut, $fin, $ent) as $r) {
            $cg[$r['gareid']]['libelle'] = $cg[$r['gareid']]['libelle'] ?? $r['garelibelle'];
            $cg[$r['gareid']]['recus'] = (int) $r['nb'];
        }
        foreach ($courrierRepository->enAttenteParGare($debut, $fin, $ent) as $r) {
            $cg[$r['gareid']]['libelle'] = $cg[$r['gareid']]['libelle'] ?? $r['garelibelle'];
            $cg[$r['gareid']]['enAttente'] = (int) $r['nb'];
        }
        $courriersParGare = [];
        foreach ($cg as $id => $g) {
            $courriersParGare[] = [
                'gareId' => $id,
                'libelle' => $g['libelle'] ?? '—',
                'emis' => $g['emis'] ?? 0,
                'recus' => $g['recus'] ?? 0,
                'enAttente' => $g['enAttente'] ?? 0,
            ];
        }
        usort($courriersParGare, fn ($a, $b) => ($b['emis'] + $b['recus']) <=> ($a['emis'] + $a['recus']));

        // ── Bagages ──
        $bg = [];
        foreach ($bagageRepository->emisParGare($debut, $fin, $ent) as $r) {
            $bg[$r['gareid']]['libelle'] = $r['garelibelle'];
            $bg[$r['gareid']]['emis'] = (int) $r['nb'];
            $bg[$r['gareid']]['poids'] = round((float) $r['poids'], 1);
        }
        foreach ($bagageRepository->livresParGare($debut, $fin, $ent) as $r) {
            $bg[$r['gareid']]['libelle'] = $bg[$r['gareid']]['libelle'] ?? $r['garelibelle'];
            $bg[$r['gareid']]['livres'] = (int) $r['nb'];
        }
        $bagagesParGare = [];
        foreach ($bg as $id => $g) {
            $bagagesParGare[] = [
                'gareId' => $id,
                'libelle' => $g['libelle'] ?? '—',
                'emis' => $g['emis'] ?? 0,
                'livres' => $g['livres'] ?? 0,
                'poids' => $g['poids'] ?? 0,
            ];
        }
        usort($bagagesParGare, fn ($a, $b) => $b['emis'] <=> $a['emis']);

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'courriersParGare' => $courriersParGare,
            'bagagesParGare' => $bagagesParGare,
        ]);
    }

    /**
     * Pilotage : recette par gare sur la période N comparée à la période précédente N-1 de même durée
     * (juste avant), avec la variation en %. Recette = billets + courriers + bagages encaissés à la gare.
     */
    #[Route('/api/stats/gares/pilotage', name: 'api_stats_gares_pilotage', methods: ['GET'])]
    public function pilotage(
        Request $request,
        Security $security,
        TicketRepository $ticketRepository,
        CourrierRepository $courrierRepository,
        BagageRepository $bagageRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // Période précédente de même durée, juste avant la période courante
        $dur = $fin->getTimestamp() - $debut->getTimestamp();
        $prevFin = $debut->modify('-1 second');
        $prevDebut = $prevFin->setTimestamp($prevFin->getTimestamp() - $dur);

        $cur = $this->recetteMap($debut, $fin, $ent, $ticketRepository, $courrierRepository, $bagageRepository);
        $prev = $this->recetteMap($prevDebut, $prevFin, $ent, $ticketRepository, $courrierRepository, $bagageRepository);

        $ids = array_unique(array_merge(array_keys($cur), array_keys($prev)));
        $parGare = [];
        foreach ($ids as $id) {
            $n = $cur[$id]['recette'] ?? 0;
            $n1 = $prev[$id]['recette'] ?? 0;
            $parGare[] = [
                'gareId' => $id,
                'libelle' => $cur[$id]['libelle'] ?? $prev[$id]['libelle'] ?? '—',
                'recetteN' => $n,
                'recetteN1' => $n1,
                'variation' => $n1 > 0 ? (int) round(($n - $n1) / $n1 * 100) : ($n > 0 ? 100 : 0),
            ];
        }
        usort($parGare, fn ($a, $b) => $b['recetteN'] <=> $a['recetteN']);

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'periodePrecedente' => ['debut' => $prevDebut->format('Y-m-d'), 'fin' => $prevFin->format('Y-m-d')],
            'parGare' => $parGare,
        ]);
    }

    /** Recette totale (billets + courriers + bagages) par gare sur une période → [gareid => [libelle, recette]]. */
    private function recetteMap(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $ent, TicketRepository $tr, CourrierRepository $cr, BagageRepository $br): array
    {
        $map = [];
        $add = function (array $rows) use (&$map) {
            foreach ($rows as $r) {
                $map[$r['gareid']]['libelle'] = $map[$r['gareid']]['libelle'] ?? $r['garelibelle'];
                $map[$r['gareid']]['recette'] = ($map[$r['gareid']]['recette'] ?? 0) + (int) $r['recette'];
            }
        };
        $add($tr->recetteParGare($debut, $fin, $ent));
        $add($cr->recetteParGare($debut, $fin, $ent));
        $add($br->recetteParGare($debut, $fin, $ent));

        return $map;
    }
}
