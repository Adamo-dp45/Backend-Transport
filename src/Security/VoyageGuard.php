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

    /**
     * Le car a-t-il déjà ATTEINT (ou dépassé) la gare de MONTÉE d'un billet ?
     *
     * Intermédiaire-aware : la position réelle du car est 'garecourante' (elle avance au fil des
     * réceptions), à défaut l'origine effective du voyage. Tant que le voyage n'est pas PARTI
     * ('datedepartreelle' nul), rien n'est atteint — un billet reste librement traitable avant le départ.
     * Sert aux gardes « service en cours/rendu » : ni annulation-remboursement, ni modification d'un
     * billet dont le car est déjà passé à sa gare de montée.
     */
    public function monteeAtteinte(Voyage $voyage, ?Gare $garemontee): bool
    {
        $ordres = $this->ordresPosition($voyage, $garemontee);

        return $ordres !== null && $ordres['position'] >= $ordres['montee'];
    }

    /**
     * Le car a-t-il DÉFINITIVEMENT quitté la gare de montée ? Plus strict que {@see monteeAtteinte}.
     *
     * Être À la gare de montée n'est pas trop tard — c'est même le moment où l'on vend et où l'on
     * émet les billets : 'garecourante' avance à la RÉCEPTION, donc à l'ARRIVÉE du car. Le départ,
     * lui, n'est tracé que pour l'origine ('datedepartreelle') : c'est le seul endroit où
     * « position == montée » signifie déjà reparti.
     *
     * Sert aux gardes de RÉSERVATION (créer, encaisser, émettre) : on ne vend pas une place sur un
     * car qui a quitté la gare où le client devait monter.
     */
    public function monteeDepassee(Voyage $voyage, ?Gare $garemontee): bool
    {
        /*
            DÉPART HORODATÉ de cette gare : la preuve la plus directe, et la SEULE qui vaille pour
            une gare INTERMÉDIAIRE.

            'garecourante' n'avance qu'à la RÉCEPTION, c'est-à-dire à l'ARRIVÉE du car. Une fois
            reparti de Bouaké, le voyage garde donc « Bouaké » comme position : les deux tests
            d'ordre ci-dessous répondaient faux et la gare continuait de vendre, de modifier et de
            désister sur un car qu'elle avait vu partir. Le départ, lui, est consigné dans 'Passage'
            (par 'RepartirVoyageProcessor' à l'escale, par 'VoyageDepartService' à l'origine).
        */
        if ($garemontee !== null && $this->aQuitteLaGare($voyage, $garemontee)) {
            return true;
        }

        $ordres = $this->ordresPosition($voyage, $garemontee);
        if ($ordres === null) {
            return false;
        }

        return $ordres['position'] > $ordres['montee'] || $ordres['montee'] <= $ordres['origine'];
    }

    /** Le départ du car de cette gare est-il consigné ? */
    private function aQuitteLaGare(Voyage $voyage, Gare $gare): bool
    {
        foreach ($voyage->getPassages() as $passage) {
            if ($passage->getGare()?->getId() === $gare->getId()) {
                return $passage->getDepartReelle() !== null;
            }
        }

        return false;
    }

    /**
     * Le car est-il ENCORE POSITIONNÉ sur la gare de montée de ce billet ?
     *
     * C'est la borne du COMMERCIAL (vendeur à bord), et elle est volontairement DIFFÉRENTE de
     * {@see monteeDepassee}, qui est celle de la gare. Le commercial voyage AVEC le car : la gare de
     * montée d'un billet qu'il vend n'est pas sa gare d'attache, c'est la position du véhicule au
     * moment de la vente. Il encaisse d'ailleurs APRÈS le départ (passagers montés sans avoir payé,
     * cf. TicketProcessor) — un billet qu'il vient d'émettre est donc 'monteedepassee', et le juger
     * avec la borne de la gare lui interdirait de corriger sa propre saisie dans la foulée.
     *
     * 'garecourante' n'avance qu'à la RÉCEPTION : elle reste sur la gare quittée pendant tout le
     * trajet jusqu'à l'escale suivante, ce qui laisse au vendeur le tronçon en cours pour se relire.
     * À l'arrivée suivante, la position change et la correction se ferme d'elle-même.
     */
    public function surLaGareDeMontee(?Voyage $voyage, ?Gare $garemontee): bool
    {
        if ($voyage === null || $garemontee === null) {
            return false;
        }

        $position = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();

        return $position?->getId() === $garemontee->getId();
    }

    /** Variante levant une 400, avec un message adapté à l'action refusée. */
    public function assertMonteeNonAtteinte(Voyage $voyage, ?Gare $garemontee, string $message): void
    {
        if ($this->monteeAtteinte($voyage, $garemontee)) {
            throw new BadRequestHttpException($message);
        }
    }

    /** Variante levant une 400 pour les gardes de réservation. */
    public function assertMonteeNonDepassee(Voyage $voyage, ?Gare $garemontee, string $message): void
    {
        if ($this->monteeDepassee($voyage, $garemontee)) {
            throw new BadRequestHttpException($message);
        }
    }

    /**
     * Ordres (position du car, gare de montée, origine effective) sur la ligne, ou null tant que le
     * voyage n'est PAS PARTI — avant le départ réel, aucune gare n'est ni atteinte ni dépassée.
     *
     * @return array{position:int, montee:int, origine:int}|null
     */
    private function ordresPosition(Voyage $voyage, ?Gare $garemontee): ?array
    {
        $ligne = $voyage->getLigne();
        if ($voyage->getDatedepartreelle() === null || $ligne === null) {
            return null;
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = (int) $arret->getOrdre();
        }
        $position = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        $origine = $voyage->getOrigineEffective();

        return [
            'position' => $position ? ($ordreParGare[$position->getId()] ?? 0) : 0,
            'montee' => $ordreParGare[$garemontee?->getId()] ?? PHP_INT_MAX,
            'origine' => $origine ? ($ordreParGare[$origine->getId()] ?? 0) : 0,
        ];
    }
}
