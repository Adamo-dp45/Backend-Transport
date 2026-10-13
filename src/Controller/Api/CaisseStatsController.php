<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\SessioncaisseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * LA FIABILITÉ DES CAISSES — le seul chiffre de CONTRÔLE du projet.
 *
 * Toutes les autres statistiques mesurent l'ACTIVITÉ : ce qui a été vendu, transporté, dépensé.
 * Celle-ci mesure la VÉRACITÉ — l'argent compté face à l'argent attendu. Le module Caisse produit
 * cette donnée depuis le 30/09/2026 et rien ne l'agrégeait : l'écart ne se lisait qu'une caisse à
 * la fois, si bien qu'un agent qui dérive tous les jours de 3 000 F ressemblait à un agent qui s'est
 * trompé une fois.
 *
 * !! ON NE CLASSE PAS SUR L'ÉCART NET. Un agent qui alterne +3 000 et −3 000 a un net nul et n'est
 * pourtant jamais juste. Le classement se fait sur l'écart ABSOLU cumulé ; le net reste servi parce
 * qu'il pose une autre question (un manquant qui ne se compense jamais).
 *
 * !! CE N'EST PAS UNE PRÉSOMPTION DE FAUTE, et l'écran doit le dire. Un écart peut venir d'une
 * monnaie mal rendue, d'un fonds d'ouverture mal compté, d'une vente encaissée sans être saisie.
 * C'est un signal de FORMATION avant d'être un signal de contrôle — d'où le taux de clôtures justes,
 * qui valorise celui qui tombe juste, plutôt que le seul palmarès des écarts.
 *
 * ROLE_ADMIN : comme les autres surfaces inter-gares, le classement nomme des agents et chiffre leur
 * imprécision. L'admin de gare a déjà l'écran opérationnel des sessions, borné à sa gare.
 */
final class CaisseStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/caisses', name: 'api_stats_caisses', methods: ['GET'])]
    public function caisses(
        Request $request,
        Security $security,
        SessioncaisseRepository $sessioncaisseRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        $lignes = [];
        $totaux = ['clotures' => 0, 'justes' => 0, 'ecartAbsolu' => 0, 'ecartNet' => 0, 'volume' => 0];

        foreach ($sessioncaisseRepository->fiabiliteParAgent($debut, $fin, $identreprise) as $r) {
            $clotures = (int) $r['nbclotures'];
            $avecEcart = (int) $r['nbavececart'];
            $justes = $clotures - $avecEcart;
            $volume = (int) $r['volume'];
            $absolu = (int) $r['ecartabsolu'];

            $lignes[] = [
                'agentId' => (int) $r['agentid'],
                'agent' => trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')),
                'gareId' => $r['gareid'] !== null ? (int) $r['gareid'] : null,
                'gare' => $r['garelibelle'] ?? '—',
                'clotures' => $clotures,
                'justes' => $justes,
                // Le chiffre qui valorise : la part des caisses tombées PILE.
                'tauxJuste' => $clotures > 0 ? (int) round($justes / $clotures * 100) : 0,
                'ecartNet' => (int) $r['ecartnet'],
                'ecartAbsolu' => $absolu,
                // Moyenne par clôture : sans elle, celui qui clôture 40 fois paraît toujours pire
                // que celui qui clôture 3 fois, quelle que soit sa précision.
                'ecartMoyen' => $clotures > 0 ? (int) round($absolu / $clotures) : 0,
                'pireEcart' => (int) $r['pireecart'],
                'volume' => $volume,
                /*
                    L'écart RAPPORTÉ AU VOLUME BRASSÉ, en pour mille. Deux agents à 10 000 F d'écart
                    cumulé ne se valent pas si l'un a manipulé 200 000 F et l'autre 4 millions. C'est
                    cette mesure-là qui se compare d'une gare à l'autre.
                */
                'tauxEcart' => $volume > 0 ? round($absolu / $volume * 1000, 2) : 0,
            ];

            $totaux['clotures'] += $clotures;
            $totaux['justes'] += $justes;
            $totaux['ecartAbsolu'] += $absolu;
            $totaux['ecartNet'] += (int) $r['ecartnet'];
            $totaux['volume'] += $volume;
        }

        // Classement par imprécision décroissante : l'écran s'ouvre sur ce qui demande une action.
        usort($lignes, static fn (array $a, array $b) => $b['ecartAbsolu'] <=> $a['ecartAbsolu']);

        $totaux['tauxJuste'] = $totaux['clotures'] > 0
            ? (int) round($totaux['justes'] / $totaux['clotures'] * 100)
            : 0;
        $totaux['tauxEcart'] = $totaux['volume'] > 0
            ? round($totaux['ecartAbsolu'] / $totaux['volume'] * 1000, 2)
            : 0;

        return $this->json([
            'debut' => $debut->format('Y-m-d'),
            'fin' => $fin->format('Y-m-d'),
            'parAgent' => $lignes,
            'totaux' => $totaux,
            /*
                LES CLÔTURES RETIRÉES DU CALCUL, annoncées. Ce sont celles dont l'attendu est négatif
                — un remboursement payé avec des espèces venues du coffre, que rien ne permet encore
                de saisir. Les garder mettrait un agent irréprochable en tête du classement ; les
                taire rendrait les totaux incontrôlables.
            */
            'clotureesEcartees' => $sessioncaisseRepository->clotureesEcartees($debut, $fin, $identreprise),
        ]);
    }
}
