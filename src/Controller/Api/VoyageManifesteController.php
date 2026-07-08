<?php

namespace App\Controller\Api;

use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Enum\TicketStatus;
use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Manifeste / feuille de route d'un voyage : reconstitue, à partir des billets (gare de montée /
 * descente), courriers et bagages + l'ordre des arrêts de la ligne, ce qui s'est passé À CHAQUE GARE
 * et SUR CHAQUE TRONÇON (occupation par segment, même logique que 'SiegeStateProvider').
 *
 * Non borné par gare : un acteur de gare voit l'intégralité du trajet (choix produit). La visibilité
 * du voyage reste protégée par 'is_granted(VOIR, Voyage)' + le périmètre entreprise.
 */
final class VoyageManifesteController extends AbstractController
{
    #[Route('/api/voyages/{id}/manifeste', name: 'api_voyage_manifeste', methods: ['GET'], requirements: ['id' => Requirement::DIGITS])]
    public function manifeste(
        int $id,
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository,
        CourrierRepository $courrierRepository,
        BagageRepository $bagageRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('VOIR', 'Voyage');

        /** @var User $user */
        $user = $security->getUser();
        $entId = $user->getEntreprise()->getId();

        $voyage = $voyageRepository->findOneBy(['id' => $id, 'identreprise' => $entId, 'deletedAt' => null]);
        if (!$voyage) {
            throw new NotFoundHttpException('Voyage introuvable');
        }

        // Ordre des arrêts de la ligne (gareId => ordre) + liste triée
        $ligne = $voyage->getLigne();
        $arrets = [];
        $ordreParGare = [];
        if ($ligne) {
            foreach ($ligne->getArrets() as $a) {
                $arrets[] = $a;
                $ordreParGare[$a->getGare()->getId()] = $a->getOrdre();
            }
            usort($arrets, fn ($x, $y) => $x->getOrdre() <=> $y->getOrdre());
        }
        // DÉPART PARTIEL : le manifeste ne couvre que la ROUTE EFFECTIVE [provenance → terminus].
        // On retire les arrêts situés avant la provenance (le car n'y passe pas).
        $origineEff = $voyage->getGareprovenance();
        $ordreProvenance = $origineEff !== null ? ($ordreParGare[$origineEff->getId()] ?? 0) : 0;
        if ($ordreProvenance > 0) {
            $arrets = array_values(array_filter($arrets, fn ($a) => $a->getOrdre() >= $ordreProvenance));
        }
        $ordreTerminus = $arrets ? end($arrets)->getOrdre() : PHP_INT_MAX;
        // Dépôt sans gare connue (ex. ancien bagage) → rattaché à la provenance effective pour que les
        // recettes par gare se réconcilient avec le total du voyage.
        $origineId = $arrets ? $arrets[0]->getGare()->getId() : null;

        // Données ACTIVES du voyage
        $tickets = $ticketRepository->findBy([
            'voyage' => $id,
            'statut' => TicketStatus::STATUT_VALIDE->value, // reporté/annulé = ne compte plus
            'deletedAt' => null,
        ]);
        $courriers = array_filter(
            $courrierRepository->findBy(['voyage' => $id, 'deletedAt' => null]),
            fn ($c) => $c->getStatut() !== CourrierStatus::STATUT_ANNULE->value
        );
        $bagages = array_filter(
            $bagageRepository->findBy(['voyage' => $id, 'deletedAt' => null]),
            // Sur la feuille de route : tous les bagages rattachés à ce voyage SAUF annulés/perdus.
            // (Avant on excluait ENREGISTRE = « pas embarqué » ; mais désormais les bagages restent
            //  ENREGISTRE jusqu'au DÉPART RÉEL — il faut les voir AVANT le départ pour les charger.)
            fn ($b) => !in_array($b->getStatut(), [
                BagageStatus::STATUT_ANNULE->value,
                BagageStatus::STATUT_PERDU->value,
            ], true)
        );

        // --- Agrégation PAR GARE ---
        $gares = [];
        foreach ($arrets as $a) {
            $g = $a->getGare();
            $gid = $g->getId();
            $estTerminus = $a->getOrdre() === $ordreTerminus;

            $montees = array_filter($tickets, fn ($t) => $t->getGare()?->getId() === $gid);
            $descentes = array_filter($tickets, function ($t) use ($gid, $estTerminus) {
                $d = $t->getGaredescente();
                return $d ? $d->getId() === $gid : $estTerminus; // descente nulle = terminus
            });
            // Courriers/bagages déposés ICI : la recette y est encaissée (paiement à l'envoi/dépôt)
            $courriersDeposes = array_filter($courriers, fn ($c) => ($c->getGaredepart()?->getId() ?? $origineId) === $gid);
            $bagagesCharges = array_filter($bagages, fn ($b) => ($b->getGaredepart()?->getId() ?? $origineId) === $gid);

            // Recette attribuée à la gare où c'est encaissé (montée billet / dépôt colis-bagage) → pas de double-comptage.
            // Les ventes COMMERCIALES (vendeur à bord) sont exclues ici : leur recette va au commercial (bloc 'commerciaux').
            $recetteBillets = array_sum(array_map(
                fn ($t) => (int) $t->getPrix(),
                // guichet uniquement : hors ventes commerciales (recette du commercial) ET hors billets de
                // réservation (payés sur le compte admin, cf. Ticket::$reservation).
                array_filter($montees, fn ($t) => $t->getCommercial() === null && $t->getReservation() === null)
            ));
            $recetteCourriers = array_sum(array_map(fn ($c) => (int) $c->getMontant(), $courriersDeposes));
            $recetteBagages = array_sum(array_map(fn ($b) => (int) $b->getMontant(), $bagagesCharges));

            $gares[] = [
                'id' => $gid,
                'libelle' => $g->getLibelle(),
                'ville' => $g->getVille()?->getNom(),
                'ordre' => $a->getOrdre(),
                'role' => $a->getOrdre() === 0 ? 'depart' : ($estTerminus ? 'terminus' : 'intermediaire'),
                'montees' => count($montees),
                'descentes' => count($descentes),
                'courriersDeposes' => count($courriersDeposes),
                'courriersArrivee' => count(array_filter($courriers, fn ($c) => $c->getGarearrivee()?->getId() === $gid)),
                'bagagesCharges' => count($bagagesCharges),
                'bagagesArrivee' => count(array_filter($bagages, function ($b) use ($gid, $estTerminus) {
                    $d = $b->getGaredescente();
                    return $d ? $d->getId() === $gid : $estTerminus;
                })),
                'recette' => $recetteBillets,
                'recetteCourriers' => $recetteCourriers,
                'recetteBagages' => $recetteBagages,
                'recetteTotale' => $recetteBillets + $recetteCourriers + $recetteBagages,
            ];
        }

        // --- Occupation PAR TRONÇON [arret i -> arret i+1] ---
        $troncons = [];
        $n = count($arrets);
        for ($i = 0; $i < $n - 1; $i++) {
            $ordreI = $arrets[$i]->getOrdre();
            $aBord = 0;
            foreach ($tickets as $t) {
                $tm = $ordreParGare[$t->getGare()?->getId()] ?? null;
                $td = $t->getGaredescente() ? ($ordreParGare[$t->getGaredescente()->getId()] ?? null) : $ordreTerminus;
                if ($tm === null || $td === null) {
                    continue;
                }
                if ($tm <= $ordreI && $td > $ordreI) { // billet à bord sur ce tronçon
                    $aBord++;
                }
            }
            $placestotal = $voyage->getPlacestotal();
            $troncons[] = [
                'deId' => $arrets[$i]->getGare()->getId(),
                'de' => $arrets[$i]->getGare()->getLibelle(),
                'versId' => $arrets[$i + 1]->getGare()->getId(),
                'vers' => $arrets[$i + 1]->getGare()->getLibelle(),
                'ordre' => $ordreI,
                'aBord' => $aBord,
                'placestotal' => $placestotal,
                'taux' => $placestotal > 0 ? (int) round($aBord / $placestotal * 100) : 0,
            ];
        }

        // --- Ventilation des VENTES COMMERCIALES (vendeur à bord) ---
        // Recette attribuée au COMMERCIAL (et non à la gare de montée = position courante du car),
        // détaillée par TRAJET vendu (montée → descente vendue) — objectif « ventes par tronçon ».
        $commerciaux = [];
        foreach ($tickets as $t) {
            $c = $t->getCommercial();
            if ($c === null) {
                continue; // vente au guichet → déjà comptée dans la gare
            }
            $cid = $c->getId();
            if (!isset($commerciaux[$cid])) {
                $commerciaux[$cid] = [
                    'id' => $cid,
                    'nom' => trim(($c->getPrenom() ?? '') . ' ' . ($c->getNom() ?? '')) ?: ('#' . $cid),
                    'nb' => 0,
                    'recette' => 0,
                    'trajets' => [],
                ];
            }
            $prix = (int) $t->getPrix();
            $commerciaux[$cid]['nb']++;
            $commerciaux[$cid]['recette'] += $prix;

            $monteeGare = $t->getGare();
            $descenteGare = $t->getGaredescente();
            $tk = ($monteeGare?->getId() ?? 0) . '-' . ($descenteGare?->getId() ?? 0);
            if (!isset($commerciaux[$cid]['trajets'][$tk])) {
                $commerciaux[$cid]['trajets'][$tk] = [
                    'de' => $monteeGare?->getLibelle() ?? '?',
                    'vers' => $descenteGare?->getLibelle() ?? 'Terminus',
                    'ordre' => $ordreParGare[$monteeGare?->getId()] ?? PHP_INT_MAX,
                    'nb' => 0,
                    'recette' => 0,
                ];
            }
            $commerciaux[$cid]['trajets'][$tk]['nb']++;
            $commerciaux[$cid]['trajets'][$tk]['recette'] += $prix;
        }
        // Réindexe : trajets triés par ordre de montée le long de la ligne, commerciaux par recette décroissante
        $commerciaux = array_map(function ($c) {
            $trajets = array_values($c['trajets']);
            usort($trajets, fn ($x, $y) => $x['ordre'] <=> $y['ordre']);
            $c['trajets'] = $trajets;
            return $c;
        }, array_values($commerciaux));
        usort($commerciaux, fn ($a, $b) => $b['recette'] <=> $a['recette']);

        // Scission billets par CANAL (invariant : gares + commerciaux + réservation = recette billets totale)
        $recetteBilletsCommerciaux = array_sum(array_map(
            fn ($t) => (int) $t->getPrix(),
            array_filter($tickets, fn ($t) => $t->getCommercial() !== null)
        ));
        $recetteBilletsReservation = array_sum(array_map(
            fn ($t) => (int) $t->getPrix(),
            array_filter($tickets, fn ($t) => $t->getReservation() !== null)
        ));

        return $this->json([
            'voyage' => [
                'id' => $voyage->getId(),
                'codevoyage' => $voyage->getCodevoyage(),
                'provenance' => $voyage->getProvenance(),
                'destination' => $voyage->getDestination(),
                'car' => $voyage->getCar()?->getMatricule(),
                'placestotal' => $voyage->getPlacestotal(),
                'datedepartprevue' => $voyage->getDatedepartprevue()?->format('d/m/Y H:i'),
            ],
            'totaux' => [
                'passagers' => count($tickets),
                'recetteBillets' => $totalBillets = array_sum(array_map(fn ($t) => (int) $t->getPrix(), $tickets)),
                'recetteBilletsCommerciaux' => $recetteBilletsCommerciaux,
                'recetteBilletsReservation' => $recetteBilletsReservation,
                'recetteBilletsGares' => $totalBillets - $recetteBilletsCommerciaux - $recetteBilletsReservation,
                'courriers' => count($courriers),
                'recetteCourriers' => $totalCourriers = array_sum(array_map(fn ($c) => (int) $c->getMontant(), $courriers)),
                'bagages' => count($bagages),
                'recetteBagages' => $totalBagages = array_sum(array_map(fn ($b) => (int) $b->getMontant(), $bagages)),
                'recetteTotale' => $totalBillets + $totalCourriers + $totalBagages,
            ],
            'gares' => $gares,
            'troncons' => $troncons,
            'commerciaux' => $commerciaux,
        ]);
    }
}
