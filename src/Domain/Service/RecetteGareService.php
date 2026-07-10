<?php

namespace App\Domain\Service;

use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;

/**
 * Calcul CENTRALISÉ de la recette PAR GARE, ventilée par CANAL de vente (guichet / commercial /
 * réservation) et par type (billets / bagages / courriers). Source unique de vérité pour les surfaces
 * « recette d'une gare » (tableau de bord gare, caisse).
 *
 * Règles (2026-07-06) :
 *  - Billets guichet : t.gare (gare de montée = gare de l'agent), hors commercial & hors réservation.
 *  - Billets commerciaux : regroupés par la GARE D'AFFECTATION du commercial (User.gare), pas la montée.
 *  - Réservations : reconnues AU PAIEMENT, par gare de provenance (r.gare) ; le billet émis ne recompte pas.
 *  - Bagages guichet : garedepart ; bagages commerciaux : gare d'affectation du commercial.
 *  - Courriers : garedepart.
 */
class RecetteGareService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private BagageRepository $bagageRepository,
        private CourrierRepository $courrierRepository,
        private ReservationRepository $reservationRepository
    )
    {
    }

    /**
     * @return array<int, array<string, int|string>> indexé par gareId, chaque entrée agrégée + ventilée.
     */
    public function parGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $gares = [];
        $init = function (int $id, ?string $libelle) use (&$gares): void {
            if (!isset($gares[$id])) {
                $gares[$id] = [
                    'gareId' => $id, 'libelle' => $libelle ?? '—',
                    'billetsGuichet' => 0, 'nbBilletsGuichet' => 0,
                    'billetsCommercial' => 0, 'nbBilletsCommercial' => 0,
                    'reservation' => 0, 'nbReservations' => 0,
                    'bagagesGuichet' => 0, 'nbBagagesGuichet' => 0,
                    'bagagesCommercial' => 0, 'nbBagagesCommercial' => 0,
                    'courriers' => 0, 'nbCourriers' => 0,
                ];
            } elseif ($libelle !== null && $gares[$id]['libelle'] === '—') {
                $gares[$id]['libelle'] = $libelle;
            }
        };

        // Billets guichet (t.gare, hors commercial & réservation)
        foreach ($this->ticketRepository->recetteParGare($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['billetsGuichet'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbBilletsGuichet'] = (int) $r['nbtickets'];
        }
        // Billets commerciaux (gare d'affectation du commercial)
        foreach ($this->ticketRepository->recetteCommercialeParGareAffectation($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['billetsCommercial'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbBilletsCommercial'] = (int) $r['nbtickets'];
        }
        // Réservations payées (r.gare = provenance)
        foreach ($this->reservationRepository->recettePayeeParGare($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['reservation'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbReservations'] = (int) $r['nbreservations'];
        }
        // Bagages guichet (garedepart)
        foreach ($this->bagageRepository->recetteParGare($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['bagagesGuichet'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbBagagesGuichet'] = (int) $r['nbbagages'];
        }
        // Bagages commerciaux (gare d'affectation du commercial)
        foreach ($this->bagageRepository->recetteCommercialeParGareAffectation($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['bagagesCommercial'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbBagagesCommercial'] = (int) $r['nbbagages'];
        }
        // Courriers (garedepart)
        foreach ($this->courrierRepository->recetteParGare($debut, $fin, $identreprise) as $r) {
            $init((int) $r['gareid'], $r['garelibelle'] ?? null);
            $gares[(int) $r['gareid']]['courriers'] = (int) $r['recette'];
            $gares[(int) $r['gareid']]['nbCourriers'] = (int) $r['nbcourriers'];
        }

        // Agrégats + ventilation par canal
        foreach ($gares as &$g) {
            $g['recetteBillets'] = $g['billetsGuichet'] + $g['billetsCommercial'] + $g['reservation'];
            $g['recetteBagages'] = $g['bagagesGuichet'] + $g['bagagesCommercial'];
            $g['recetteCourriers'] = $g['courriers'];
            $g['recetteTotale'] = $g['recetteBillets'] + $g['recetteBagages'] + $g['recetteCourriers'];
            // Canal (tous types) : guichet = billets guichet + bagages guichet + courriers ;
            // commercial = billets + bagages commerciaux ; réservation = réservations payées.
            $g['canalGuichet'] = $g['billetsGuichet'] + $g['bagagesGuichet'] + $g['courriers'];
            $g['canalCommercial'] = $g['billetsCommercial'] + $g['bagagesCommercial'];
            $g['canalReservation'] = $g['reservation'];
            $g['nbBagages'] = $g['nbBagagesGuichet'] + $g['nbBagagesCommercial'];
            $g['nbOperations'] = $g['nbBilletsGuichet'] + $g['nbBilletsCommercial'] + $g['nbReservations'] + $g['nbBagages'] + $g['nbCourriers'];
        }
        unset($g);

        return $gares;
    }
}
