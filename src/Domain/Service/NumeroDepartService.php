<?php

namespace App\Domain\Service;

use App\Entity\Voyage;
use App\Repository\VoyageRepository;

/**
 * Attribue le NUMÉRO DE DÉPART DU JOUR d'un voyage — « DÉPART 4 », la case que le passager lit sur
 * son billet face à son siège.
 *
 * Le compteur repart à 1 chaque jour, POUR CHAQUE GARE et CHAQUE LIGNE : c'est le Nième car que
 * cette gare lance sur cette ligne ce jour-là. Un DÉPART PARTIEL tient donc sa propre suite — à
 * Bouaké, le premier car de la journée s'annonce « départ 1 » même si Abidjan en a déjà fait partir
 * deux sur la même ligne. C'est la lecture du GUICHET, celle que le passager entend, et son billet
 * porte déjà sa destination.
 *
 * !! L'APPELANT DOIT TENIR LE VERROU sur la ligne et écrire dans la MÊME transaction (cf.
 * VoyageProcessor) : entre le calcul du plus haut numéro et l'insertion, une création concurrente
 * prendrait le même. L'index unique 'uniq_voyage_numerodepart' est le filet — il transforme la
 * collision en refus franc plutôt qu'en deux « Départ 2 » silencieux — mais il ne remplace pas le
 * verrou, qui lui évite le refus.
 */
class NumeroDepartService
{
    public function __construct(
        private VoyageRepository $voyageRepository
    )
    {
    }

    /**
     * Pose le prochain numéro libre du jour sur le voyage et le renvoie.
     *
     * Le voyage doit déjà porter sa ligne, sa date de départ prévue et son entreprise — c'est-à-dire
     * être passé par la préparation du processor. La gare retenue est l'ORIGINE EFFECTIVE
     * (provenance réelle sur un départ partiel, origine de la ligne sinon).
     */
    public function attribuer(Voyage $voyage): int
    {
        $ligne = $voyage->getLigne();
        $gare = $voyage->getOrigineEffective();
        $jour = $voyage->getDatedepartprevue();

        if ($ligne === null || $gare === null || $jour === null) {
            // Inatteignable depuis l'API (le processor valide la ligne et la date avant d'arriver ici).
            // On le dit franchement plutôt que d'écrire un numéro calculé sur des trous.
            throw new \LogicException('Numéro de départ : ligne, gare de provenance et date de départ prévue sont requises');
        }

        $numero = $this->voyageRepository->maxNumeroDepart(
            (int) $voyage->getIdentreprise(),
            (int) $ligne->getId(),
            (int) $gare->getId(),
            $jour
        ) + 1;

        $voyage->setNumerodepart($numero);

        return $numero;
    }
}
