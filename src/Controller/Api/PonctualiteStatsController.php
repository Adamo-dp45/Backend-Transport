<?php

namespace App\Controller\Api;

use App\Domain\Service\ReservationEcheanceService;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\PassageRepository;
use App\Repository\VoyageRepository;
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
        VoyageRepository $voyageRepository,
        ReservationEcheanceService $echeance
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);
        $seuil = max(0, (int) $request->query->get('seuil', 10));
        // Restreint la ventilation par personnel à un type (ex. ?typepersonnel=chauffeur), le libellé
        // étant propre à chaque entreprise. Null = tous les membres d'équipage.
        $filtreType = trim((string) $request->query->get('typepersonnel'));
        $filtreType = $filtreType === '' ? null : mb_strtolower($filtreType);

        $passages = $passageRepository->findAvecArriveePourPeriode($debut, $fin, $ent);

        $tousRetards = [];
        $tempsArrets = [];
        $parGare = [];   // gareId => ['libelle', 'retards'=>[], 'arrets'=>[]]
        $parLigne = [];  // ligneId => ['code', 'retards'=>[]]
        $parOrdre = [];  // ordre => ['retards'=>[]]
        $ordreParLigneGare = []; // cache "ligneId:gareId" => ordre
        $parCar = [];       // carId => ['matricule', 'retards'=>[]]
        $parPersonnel = []; // personnelId => ['nom', 'type', 'retards'=>[]]

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

            // ── Par VÉHICULE : isole un car qui traîne (usure, motorisation, conduite) ──
            $car = $voyage->getCar();
            if ($car !== null) {
                $cid = $car->getId();
                $parCar[$cid]['matricule'] ??= $car->getMatricule();
                $parCar[$cid]['retards'][] = $retard;
            }

            /*
                ── Par PERSONNEL affecté ──
                Le modèle n'a PAS de notion figée de « chauffeur » : 'Typepersonnel' est un libellé
                libre, propre à chaque entreprise. Filtrer sur un mot-clé serait donc fragile. On
                agrège par personnel en EXPOSANT son type, et on laisse '?typepersonnel=' restreindre.
                Un même passage compte pour chaque membre d'équipage — c'est voulu : le retard est
                celui du voyage qu'ils ont fait ensemble.
            */
            foreach ($voyage->getDetailpersonnels() as $detail) {
                $personnel = $detail->getPersonnel();
                if ($personnel === null) {
                    continue;
                }
                $type = $personnel->getTypepersonnel()?->getLibelle();
                if ($filtreType !== null && mb_strtolower((string) $type) !== $filtreType) {
                    continue;
                }
                $pid = $personnel->getId();
                $parPersonnel[$pid]['nom'] ??= trim(($personnel->getPrenom() ?? '') . ' ' . ($personnel->getNom() ?? ''));
                $parPersonnel[$pid]['type'] ??= $type;
                $parPersonnel[$pid]['retards'][] = $retard;
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

        // ── Par véhicule (trié par retard moyen décroissant) ──
        $cars = [];
        foreach ($parCar as $cid => $d) {
            $cars[] = [
                'carId' => $cid,
                'matricule' => $d['matricule'],
                'nbPassages' => count($d['retards']),
                'retardMoyen' => $moyenne($d['retards']),
                'tauxALheure' => $tauxALheure($d['retards']),
            ];
        }
        usort($cars, static fn ($a, $b) => $b['retardMoyen'] <=> $a['retardMoyen']);

        // ── Par personnel d'équipage ──
        $personnels = [];
        foreach ($parPersonnel as $pid => $d) {
            $personnels[] = [
                'personnelId' => $pid,
                'nom' => $d['nom'],
                'type' => $d['type'],
                'nbPassages' => count($d['retards']),
                'retardMoyen' => $moyenne($d['retards']),
                'tauxALheure' => $tauxALheure($d['retards']),
            ];
        }
        usort($personnels, static fn ($a, $b) => $b['retardMoyen'] <=> $a['retardMoyen']);

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

        /*
            ── QUALITÉ DE LA DONNÉE D'EXPLOITATION ──
            Tout ce qui précède (retards, temps d'arrêt, ponctualité) repose sur des passages
            horodatés PAR LES AGENTS. Si le terrain ne marque pas, les statistiques sont fausses
            SANS le dire — elles paraissent juste meilleures, puisqu'un passage non marqué est un
            retard non compté. Cet indicateur mesure donc la confiance à accorder au reste.

            Attendus = arrêts RÉELLEMENT DESSERVIS, c'est-à-dire à partir de l'origine effective du
            voyage : sur un départ partiel, les gares en amont ne sont jamais visitées et n'ont donc
            rien à horodater. Un arrêt compte comme couvert dès qu'il porte une arrivée OU un départ
            (l'origine n'a pas d'arrivée, le terminus pas de départ).
        */
        $passagesAttendus = 0;
        $passagesHorodates = 0;
        $voyagesComplets = 0;
        $voyagesSansAucun = 0;
        $nbVoyagesPartis = 0;

        foreach ($voyageRepository->findPartisAvecPassagesPourPeriode($debut, $fin, $ent) as $voyage) {
            $ligne = $voyage->getLigne();
            if ($ligne === null) {
                continue;
            }

            $origine = $voyage->getOrigineEffective();
            $ordreOrigine = 0;
            foreach ($ligne->getArrets() as $arret) {
                if ($origine !== null && $arret->getGare()?->getId() === $origine->getId()) {
                    $ordreOrigine = (int) $arret->getOrdre();
                }
            }

            $garesDesservies = [];
            foreach ($ligne->getArrets() as $arret) {
                $g = $arret->getGare();
                if ($g !== null && (int) $arret->getOrdre() >= $ordreOrigine) {
                    $garesDesservies[$g->getId()] = true;
                }
            }
            if ($garesDesservies === []) {
                continue;
            }

            $horodates = 0;
            foreach ($voyage->getPassages() as $passage) {
                $gid = $passage->getGare()?->getId();
                if ($gid !== null && isset($garesDesservies[$gid])
                    && ($passage->getArriveeReelle() !== null || $passage->getDepartReelle() !== null)
                ) {
                    $horodates++;
                }
            }

            $attendu = count($garesDesservies);
            $nbVoyagesPartis++;
            $passagesAttendus += $attendu;
            $passagesHorodates += min($horodates, $attendu);
            if ($horodates >= $attendu) {
                $voyagesComplets++;
            }
            if ($horodates === 0) {
                $voyagesSansAucun++;
            }
        }

        $pourcent = static fn (int $part, int $total): int => $total === 0 ? 0 : (int) round(100 * $part / $total);

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
            'parCar' => $cars,
            'parPersonnel' => $personnels,
            'evolution' => $evolution,
            // Fiabilité des chiffres ci-dessus : un taux de couverture bas les rend optimistes.
            'qualiteDonnee' => [
                'nbVoyagesPartis' => $nbVoyagesPartis,
                'passagesAttendus' => $passagesAttendus,
                'passagesHorodates' => $passagesHorodates,
                'tauxCouverture' => $pourcent($passagesHorodates, $passagesAttendus),
                'voyagesComplets' => $voyagesComplets,
                'tauxVoyagesComplets' => $pourcent($voyagesComplets, $nbVoyagesPartis),
                'voyagesSansAucunPassage' => $voyagesSansAucun,
            ],
        ]);
    }
}
