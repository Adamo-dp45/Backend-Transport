<?php

namespace App\Domain\Service;

use App\Entity\Ligne;
use App\Entity\Voyage;
use App\Repository\VoyageRepository;

/**
 * RECALAGE des durées de tronçon d'une ligne à partir des passages RÉELS.
 *
 * Les 'Arret::dureeTronconMinutes' sont SAISIS à la main : ils vieillissent (route refaite, trafic,
 * saison) et, comme tout le calcul d'heure de passage en dépend (échéances, retards, ponctualité),
 * une durée fausse fait dériver le référentiel SANS le dire — le « retard » affiché n'est alors plus
 * qu'un écart avec une prévision périmée. Ce service compare, pour chaque tronçon, la durée déclarée
 * à la MÉDIANE des durées réellement observées, et PROPOSE un recalage.
 *
 * Il ne fait que PROPOSER : appliquer déplacerait les heures de passage annoncées (donc les échéances
 * des réservations en cours) — c'est une décision d'exploitation, validée à la main via le formulaire
 * de ligne. La médiane (et non la moyenne) rend la mesure robuste : une panne isolée de 3 h ne doit
 * pas déplacer l'horaire officiel.
 *
 * Mesure d'un tronçon menant à l'arrêt S : temps réel à S − temps réel à l'arrêt précédent, où le
 * temps réel est l'ARRIVÉE horodatée, ou le DÉPART réel quand l'arrêt précédent est le point de départ
 * du voyage (il n'a pas d'arrivée). C'est EXACTEMENT la façon dont 'heurePassage' enchaîne les
 * tronçons depuis 'datedepartprevue' → recaler sur ces médianes ramène le retard médian à zéro.
 */
final class RecalageService
{
    public function __construct(private VoyageRepository $voyageRepository)
    {
    }

    /**
     * @return array<int, array{gareId:int, libelle:?string, ordre:int, dureeDeclaree:?int,
     *     nbObservations:int, dureeMediane:?int, ecartMinutes:?int, minObserve:?int, maxObserve:?int}>
     */
    public function suggestionsPourLigne(Ligne $ligne, int $minObservations = 3): array
    {
        $arrets = $ligne->getArrets()->toArray();
        usort($arrets, static fn ($a, $b) => (int) $a->getOrdre() <=> (int) $b->getOrdre());

        // Observations (minutes) par gare de DESTINATION du tronçon.
        /** @var array<int, list<int>> $observations */
        $observations = [];

        $voyages = $this->voyageRepository->findPartisAvecPassagesPourLigne(
            (int) $ligne->getId(),
            (int) $ligne->getIdentreprise()
        );

        foreach ($voyages as $voyage) {
            $this->collecterObservations($voyage, $arrets, $observations);
        }

        // Une suggestion par tronçon (tout arrêt sauf l'origine de la ligne : ordre 0 n'a pas de tronçon).
        $suggestions = [];
        foreach ($arrets as $arret) {
            $ordre = (int) $arret->getOrdre();
            $gare = $arret->getGare();
            if ($ordre === 0 || $gare === null) {
                continue;
            }
            $gareId = $gare->getId();
            $liste = $observations[$gareId] ?? [];
            $n = count($liste);
            $mediane = $n >= $minObservations ? $this->mediane($liste) : null;
            $declaree = $arret->getDureeTronconMinutes();

            $suggestions[] = [
                'gareId' => $gareId,
                'libelle' => $gare->getLibelle(),
                'ordre' => $ordre,
                'dureeDeclaree' => $declaree,
                'nbObservations' => $n,
                'dureeMediane' => $mediane,
                'ecartMinutes' => ($mediane !== null && $declaree !== null) ? $mediane - $declaree : null,
                'minObserve' => $n > 0 ? min($liste) : null,
                'maxObserve' => $n > 0 ? max($liste) : null,
            ];
        }

        return $suggestions;
    }

    /**
     * Parcourt les arrêts d'UN voyage depuis son origine effective et empile, par gare de destination,
     * la durée réelle du tronçon menant à elle.
     *
     * @param array<int, \App\Entity\Arret> $arrets    arrêts de la ligne, ordonnés
     * @param array<int, list<int>>         $observations accumulateur (par gareId), modifié par référence
     */
    private function collecterObservations(Voyage $voyage, array $arrets, array &$observations): void
    {
        // Passages du voyage indexés par gare.
        $passages = [];
        foreach ($voyage->getPassages() as $passage) {
            $gid = $passage->getGare()?->getId();
            if ($gid !== null) {
                $passages[$gid] = $passage;
            }
        }

        $origine = $voyage->getOrigineEffective();
        $ordreOrigine = 0;
        if ($origine !== null) {
            foreach ($arrets as $arret) {
                if ($arret->getGare()?->getId() === $origine->getId()) {
                    $ordreOrigine = (int) $arret->getOrdre();
                    break;
                }
            }
        }

        $tempsPrecedent = null; // temps réel à l'arrêt précédent
        foreach ($arrets as $arret) {
            $ordre = (int) $arret->getOrdre();
            if ($ordre < $ordreOrigine) {
                continue; // en amont de l'origine effective : le car n'y passe pas
            }
            $gareId = $arret->getGare()?->getId();
            $passage = $gareId !== null ? ($passages[$gareId] ?? null) : null;

            // Temps réel à CET arrêt : arrivée horodatée, ou (à l'origine) départ réel du voyage.
            if ($ordre === $ordreOrigine) {
                $temps = $passage?->getDepartReelle() ?? $voyage->getDatedepartreelle();
            } else {
                $temps = $passage?->getArriveeReelle();
            }

            if ($tempsPrecedent !== null && $temps !== null && $gareId !== null) {
                $delta = (int) round(($temps->getTimestamp() - $tempsPrecedent->getTimestamp()) / 60);
                if ($delta > 0) { // un delta ≤ 0 trahit un horodatage erroné : on l'écarte
                    $observations[$gareId][] = $delta;
                }
            }

            // Un arrêt non horodaté ($temps null) rompt la chaîne : le tronçon suivant n'est pas mesurable.
            $tempsPrecedent = $temps;
        }
    }

    /** @param list<int> $valeurs */
    private function mediane(array $valeurs): int
    {
        sort($valeurs);
        $n = count($valeurs);
        $milieu = intdiv($n, 2);

        return $n % 2 === 1
            ? $valeurs[$milieu]
            : (int) round(($valeurs[$milieu - 1] + $valeurs[$milieu]) / 2);
    }
}
