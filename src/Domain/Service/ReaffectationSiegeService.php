<?php

namespace App\Domain\Service;

use App\Domain\Enum\TicketStatus;
use App\Entity\Car;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\Voyage;
use App\Repository\SiegeRepository;
use App\Repository\TicketRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Réaffecte les sièges des billets d'un voyage lorsqu'on lui AFFECTE ou CHANGE le car.
 *
 * Les sièges appartiennent au CAR, pas au voyage : quand le car change, un billet qui pointait un
 * siège de l'ancien car doit être rattaché à un siège du NOUVEAU car, sinon il référence un siège
 * qui n'est plus dans le véhicule — invisible sur le plan (SiegeStateProvider), ignoré de l'occupation
 * (CapaciteService) et de la garde de vente : sa place serait revendue une seconde fois.
 *
 * Deux temps, dans l'esprit du surbooking amont assumé (cf. CapaciteService) :
 *  1. REPRISE DU NUMÉRO : si le nouveau car possède le même numéro de siège, on l'y rattache — le plan
 *     est préservé à l'identique (deux billets qui partageaient un numéro le partagent encore).
 *  2. RÉATTRIBUTION : si le numéro n'existe pas (véhicule plus petit / plan différent), on rassoit le
 *     passager sur un siège LIBRE du nouveau car sur SON tronçon [montée, descente). C'est ce que veut
 *     l'exploitation en cas de panne : faire monter le client dans le car de remplacement, quitte à
 *     changer son numéro de siège. On n'échoue que s'il ne reste aucun siège libre sur son tronçon.
 *
 * La réattribution suit l'ORDRE DE MONTÉE (coloration d'intervalles) : si l'occupation maximale d'un
 * segment tient dans le car (garde vérifiée en amont par l'appelant), le glouton place forcément tout
 * le monde. Le refus reste un filet de sécurité.
 *
 * La REVENTE légitime (deux billets sur un siège, tronçons DISJOINTS) est préservée : n'ayant aucun
 * chevauchement, ces billets sont recollés sur un même siège. Un CONFLIT préexistant (tronçons qui se
 * recouvrent, un billet évincé) est PRÉSERVÉ tant que le numéro est repris à l'identique (phase 1) ; il
 * n'est RÉSOLU (chacun un siège propre) que lorsque la réattribution s'en mêle — phase 2, quand le
 * numéro n'existe pas dans le nouveau car. C'est conforme au but affiché en cas de panne (faire monter
 * tout le monde), mais comme l'occupation maximale sous-compte les conflits (un siège partagé = 1), un
 * remplacement au ras de la capacité PEUT être refusé ici alors que la garde amont l'avait laissé
 * passer. Fail-closed, sans corruption possible.
 *
 * Les billets non VALIDE (REPORTE / ANNULE) ne tiennent pas de place : ils sont rattachés au numéro
 * équivalent s'il existe, sinon à un siège quelconque du nouveau car — juste pour ne pas laisser un
 * siège d'un autre car sur un billet. Ils ne consomment aucune place dans la réattribution.
 */
class ReaffectationSiegeService
{
    public function __construct(
        private SiegeRepository $siegeRepository,
        private TicketRepository $ticketRepository
    ) {
    }

    /**
     * @throws BadRequestHttpException si un passager (billet VALIDE) ne peut être rassis dans le nouveau car
     */
    public function reaffecter(Voyage $voyage, Car $nouveauCar, int $identreprise): void
    {
        $billets = $this->ticketRepository->findBy(['voyage' => $voyage, 'deletedAt' => null]);
        if ($billets === []) {
            return; // rien à réaffecter (voyage sans billet) → première affectation, on ne touche à rien
        }

        // Sièges du nouveau car, triés par numéro pour un placement déterministe.
        $sieges = $this->siegeRepository->findBy(['car' => $nouveauCar], ['numero' => 'ASC']);
        if ($sieges === []) {
            throw new BadRequestHttpException('Le véhicule choisi n\'a aucun siège paramétré : impossible d\'y rattacher les billets.');
        }
        $siegeParNumero = [];
        foreach ($sieges as $siege) {
            $siegeParNumero[$siege->getNumero()] = $siege;
        }

        $ordreParGare = $this->ordreParGare($voyage);
        $ordreTerminus = $this->ordreTerminus($voyage, $ordreParGare);

        // siegeId => liste d'intervalles [debut, fin) déjà posés sur ce siège du nouveau car
        $occupation = [];
        // Billets VALIDE dont le numéro n'existe pas dans le nouveau car → à replacer sur un siège libre
        $aReplacer = [];
        // Billets non VALIDE sans équivalent → siège quelconque (ils ne tiennent pas de place)
        $sansPlaceARattacher = [];

        // PHASE 1 — reprise du numéro à l'identique.
        foreach ($billets as $billet) {
            $ancienSiege = $billet->getSiege();
            if ($ancienSiege === null) {
                continue;
            }
            $estValide = $billet->getStatut() === TicketStatus::STATUT_VALIDE->value;
            $siegeCible = $siegeParNumero[$ancienSiege->getNumero()] ?? null;

            if ($siegeCible !== null) {
                $billet->setSiege($siegeCible);
                if ($estValide && ($iv = $this->intervalle($billet, $ordreParGare, $ordreTerminus)) !== null) {
                    $occupation[$siegeCible->getId()][] = $iv;
                }
                continue;
            }

            if ($estValide) {
                $aReplacer[] = $billet;
            } else {
                $sansPlaceARattacher[] = $billet;
            }
        }

        // PHASE 2 — réattribution des passagers sans numéro équivalent, dans l'ordre de montée.
        $debut = fn (Ticket $t) => $this->intervalle($t, $ordreParGare, $ordreTerminus)['debut'] ?? PHP_INT_MIN;
        usort($aReplacer, fn (Ticket $a, Ticket $b) => $debut($a) <=> $debut($b));

        $nonPlacables = [];
        foreach ($aReplacer as $billet) {
            $iv = $this->intervalle($billet, $ordreParGare, $ordreTerminus);
            if ($iv === null) {
                // Billet VALIDE sans tronçon exploitable : il n'immobilise pas de place → siège quelconque.
                $sansPlaceARattacher[] = $billet;
                continue;
            }

            $choisi = null;
            foreach ($sieges as $siege) {
                if ($this->estLibreSur($occupation[$siege->getId()] ?? [], $iv)) {
                    $choisi = $siege;
                    break;
                }
            }

            if ($choisi === null) {
                $nonPlacables[] = $billet;
                continue;
            }
            $billet->setSiege($choisi);
            $occupation[$choisi->getId()][] = $iv;
        }

        if ($nonPlacables !== []) {
            $numeros = array_values(array_unique(array_map(
                static fn (Ticket $t) => (int) $t->getSiege()?->getNumero(),
                $nonPlacables
            )));
            sort($numeros);
            // On tronque la liste : au-delà de quelques sièges, l'énumération n'aide plus l'agent.
            $apercu = array_slice($numeros, 0, 8);
            $liste = implode(', ', array_map(static fn (int $n) => 'n°' . $n, $apercu));
            $reste = count($numeros) - count($apercu);
            if ($reste > 0) {
                $liste .= sprintf(' … (+%d autre%s)', $reste, $reste > 1 ? 's' : '');
            }
            throw new BadRequestHttpException(sprintf(
                'Ce véhicule (%d place(s)) ne peut pas rasseoir tous les passagers déjà vendus : '
                . '%d billet(s), aux sièges %s, n\'ont pas d\'équivalent et aucun siège n\'est libre '
                . 'sur leur tronçon. Choisissez un véhicule plus grand.',
                count($sieges),
                count($nonPlacables),
                $liste
            ));
        }

        // PHASE 3 — billets non voyageants sans équivalent : rattachés à un siège quelconque du car,
        // uniquement pour ne pas laisser un siège de l'ancien car sur le billet (ils ne comptent nulle part).
        if ($sansPlaceARattacher !== []) {
            $siegeDefaut = $sieges[0];
            foreach ($sansPlaceARattacher as $billet) {
                $billet->setSiege($siegeDefaut);
            }
        }
    }

    /** Intervalle d'occupation [debut, fin) d'un billet, ou null s'il ne tient pas de place. */
    private function intervalle(Ticket $billet, array $ordreParGare, int $ordreTerminus): ?array
    {
        $mo = $ordreParGare[$billet->getGare()?->getId()] ?? null;
        if ($mo === null) {
            // Billet hors ligne : par sécurité, on le considère occupant tout le trajet (siège pour lui seul).
            return ['debut' => PHP_INT_MIN, 'fin' => PHP_INT_MAX];
        }
        $descenteEff = $billet->getGaredescenteEffective();
        $do = $descenteEff ? ($ordreParGare[$descenteEff->getId()] ?? $ordreTerminus) : $ordreTerminus;

        return $do > $mo ? ['debut' => $mo, 'fin' => $do] : null;
    }

    /** Le siège est-il libre sur l'intervalle voulu, i.e. aucun intervalle déjà posé ne le chevauche ? */
    private function estLibreSur(array $intervallesPoses, array $voulu): bool
    {
        foreach ($intervallesPoses as $pose) {
            if ($pose['debut'] < $voulu['fin'] && $pose['fin'] > $voulu['debut']) {
                return false;
            }
        }
        return true;
    }

    /** @return array<int, int> map gareId => ordre */
    private function ordreParGare(Voyage $voyage): array
    {
        $map = [];
        if ($ligne = $voyage->getLigne()) {
            foreach ($ligne->getArrets() as $arret) {
                $map[$arret->getGare()->getId()] = $arret->getOrdre();
            }
        }
        return $map;
    }

    private function ordreTerminus(Voyage $voyage, array $ordreParGare): int
    {
        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            return PHP_INT_MAX;
        }
        return $ordreParGare[$ligne->getGareterminus()->getId()] ?? PHP_INT_MAX;
    }
}
