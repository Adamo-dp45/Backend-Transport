<?php

namespace App\Security;

use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\User;
use App\Entity\Voyage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Droits sur un voyage selon la POSITION de la gare de l'agent sur la ligne.
 *
 * Doctrine : « l'ORIGINE prépare · l'INTERMÉDIAIRE réceptionne · le TERMINUS clôture ». On distingue
 * deux natures d'action :
 *  - PLANIFICATION / propriété (création, modification, commercial, suppression du voyage) →
 *    {@see assertPeutPlanifier} : réservée à la gare d'ORIGINE (+ admin/central).
 *  - EXPLOITATION / incident (affecter/changer le car, le chauffeur/personnel — ex. panne en route) →
 *    {@see assertPeutGerer} : ouverte à toute gare de la ligne SAUF le terminus, pour que la gare où
 *    se trouve le car puisse intervenir.
 * Clôture → gare de DESTINATION (terminus). Réception → gare INTERMÉDIAIRE.
 */
class VoyageGuard
{
    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    /** EXPLOITATION (car / chauffeur) : tout le monde sauf le terminus — couvre l'incident en route (panne). */
    public function assertPeutGerer(User $user, ?Voyage $voyage): void
    {
        if($this->isAdmin($user)) {
            return;
        }
        $gare = $user->getGare();
        $ligne = $voyage?->getLigne();
        if ($gare === null || $ligne === null) {
            return;
        }
        $terminus = $ligne->getGareterminus();
        if($terminus !== null && $gare->getId() === $terminus->getId()) {
            throw new BadRequestHttpException('La gare de destination ne peut pas préparer ce voyage : elle ne fait que le clôturer');
        }
        // L'exploitation se fait sur la ROUTE EFFECTIVE du voyage : une gare EN AMONT de la provenance
        // (pas sur le trajet d'un départ partiel) n'y intervient pas.
        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $origine = $voyage->getOrigineEffective();
        $ordreProvenance = $origine !== null ? ($ordreParGare[$origine->getId()] ?? 0) : 0;
        if (isset($ordreParGare[$gare->getId()]) && $ordreParGare[$gare->getId()] < $ordreProvenance) {
            throw new BadRequestHttpException('Votre gare est située avant la provenance de ce voyage (' . ($origine?->getLibelle() ?? '?') . ') : elle n\'intervient pas dessus.');
        }
    }

    /**
     * PLANIFICATION / propriété (création, modification, commercial, suppression) : réservée à la gare
     * d'ORIGINE de la ligne (celle qui lance le départ) et aux admins/centraux. Une gare intermédiaire
     * ne prépare pas — elle réceptionne.
     */
    public function assertPeutPlanifier(User $user, ?Voyage $voyage): void
    {
        if($this->isAdmin($user)) {
            return;
        }
        $gare = $user->getGare();
        if($gare === null) {
            return; // utilisateur central sans gare : autorisé
        }
        // Origine EFFECTIVE = gareprovenance (départ partiel) sinon origine de la ligne (voyage normal).
        $origine = $voyage?->getOrigineEffective();
        if($origine === null) {
            return; // voyage/ligne sans origine (legacy) : pas de restriction
        }
        if($gare->getId() !== $origine->getId()) {
            throw new BadRequestHttpException(
                'Seule la gare d\'origine (' . $origine->getLibelle() . ') peut préparer ce voyage (création, modification, commercial). Votre gare intervient en réception.'
            );
        }
    }

    /**
     * CRÉATION d'un voyage : détermine et valide sa gare de PROVENANCE, puis la renvoie.
     *  - Admin / utilisateur central (sans gare) → départ NORMAL : provenance = origine de la ligne.
     *  - Agent de gare → provenance = SA gare, qui doit être un arrêt de la ligne et NE PAS être le
     *    terminus. Si sa gare == origine de la ligne → départ normal ; sinon → DÉPART PARTIEL (la gare
     *    intermédiaire lance son propre car, le voyage ne desservant que [sa gare → terminus]).
     */
    public function assertPeutCreerDepart(User $user, Ligne $ligne): Gare
    {
        $origine = $ligne->getGareorigine();
        if ($this->isAdmin($user) || $user->getGare() === null) {
            return $origine; // départ normal depuis l'origine de la ligne
        }
        $gare = $user->getGare();

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        if (!isset($ordreParGare[$gare->getId()])) {
            throw new BadRequestHttpException('Votre gare (' . $gare->getLibelle() . ') n\'est pas desservie par cette ligne : vous ne pouvez pas y lancer de départ.');
        }
        $terminus = $ligne->getGareterminus();
        if ($terminus !== null && $gare->getId() === $terminus->getId()) {
            throw new BadRequestHttpException('La gare de destination ne lance pas de départ : elle ne fait que clôturer.');
        }
        return $gare;
    }

    public function assertPeutCloturer(User $user, Voyage $voyage): void
    {
        if ($this->isAdmin($user)) {
            return;
        }
        $terminus = $voyage->getLigne()?->getGareterminus();
        if ($terminus === null) {
            return; // voyage sans ligne (legacy) : pas de restriction
        }
        if ($user->getGare()?->getId() !== $terminus->getId()) {
            throw new BadRequestHttpException('Seule la gare de destination peut clôturer ce voyage');
        }
    }

    public function assertPeutReceptionner(User $user, Voyage $voyage): void
    {
        $gare = $user->getGare();
        if ($gare === null) {
            throw new BadRequestHttpException('Vous devez être rattaché à une gare pour réceptionner un voyage');
        }
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : il ne peut plus être réceptionné');
        }
        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        if (!isset($ordreParGare[$gare->getId()])) {
            throw new BadRequestHttpException('Votre gare (' . $gare->getLibelle() . ') n\'est pas desservie par ce voyage');
        }

        // Réception = pour les gares que le car TRAVERSE, donc STRICTEMENT après la provenance
        // (= origine effective : gareprovenance pour un départ partiel) et avant le terminus.
        $origine = $voyage->getOrigineEffective();
        $ordreProvenance = $origine !== null ? ($ordreParGare[$origine->getId()] ?? 0) : 0;
        if ($ordreParGare[$gare->getId()] <= $ordreProvenance) {
            throw new BadRequestHttpException('La gare de provenance (ou en amont du départ) ne réceptionne pas un voyage : elle le lance');
        }
        if ($gare->getId() === $ligne->getGareterminus()?->getId()) {
            throw new BadRequestHttpException('La gare de destination ne réceptionne pas le voyage : elle le clôture');
        }
    }
}
