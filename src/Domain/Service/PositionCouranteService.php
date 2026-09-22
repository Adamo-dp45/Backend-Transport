<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Voyage;
use DateTimeImmutable;

/**
 * Avance de la POSITION COURANTE du car déclenchée par une GARE — réception ordinaire, ou rattrapage
 * d'un passage oublié par un administrateur.
 *
 * Extrait de {@see App\State\ReceptionnerVoyageProcessor} au moment où le rattrapage est apparu : les
 * deux chemins doivent produire exactement le même état, sans quoi une correction d'administrateur
 * laisserait le voyage dans une situation qu'aucune réception n'aurait pu créer.
 *
 * !! À ne pas confondre avec {@see AvanceePositionService}, qui sert la voie du COMMERCIAL à bord et
 * ne ferme PAS les réservations. La différence est assumée : le commercial signale où se trouve le
 * véhicule, la gare CONSTATE son passage — et c'est ce constat, opposable, qui décide qu'un client
 * ne sera pas embarqué. Fusionner les deux ferait fermer des réservations sur une simple déclaration
 * de position, y compris rejouée depuis une file hors ligne.
 */
class PositionCouranteService
{
    public function __construct(
        private readonly ReservationEcheanceService $reservationEcheance
    )
    {
    }

    /**
     * Avance la position à cette gare si elle est située PLUS LOIN, et ferme les réservations dont
     * la montée vient d'être dépassée.
     *
     * MONOTONE : un car ne revient pas en arrière sur sa ligne. Rendre `false` quand la position y
     * était déjà n'est pas une erreur — un rattrapage porte souvent sur une gare située DERRIÈRE la
     * position courante, et c'est précisément le cas qu'il sert à réparer.
     *
     * @return bool true si la position vient d'avancer
     */
    public function avancerA(Voyage $voyage, ?Gare $gare, DateTimeImmutable $instant): bool
    {
        $ligne = $voyage->getLigne();
        if ($ligne === null || $gare === null) {
            return false;
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }

        $cibleOrdre = $ordreParGare[$gare->getId()] ?? null;
        $courante = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        $courantOrdre = $courante ? ($ordreParGare[$courante->getId()] ?? 0) : 0;

        if ($cibleOrdre === null || $cibleOrdre <= $courantOrdre) {
            return false;
        }

        $voyage->setGarecourante($gare);

        /*
            La position vient d'avancer : les réservations des gares désormais DÉPASSÉES ne seront pas
            honorées (le car n'y repassera pas) — on libère leurs places et on ferme le paiement.
            Celles de CETTE gare sont épargnées : le car y est, on embarque.
        */
        $this->reservationEcheance->cloturerMonteesDepassees($voyage, $instant);

        return true;
    }
}
