<?php

namespace App\Domain\Service;

use App\Entity\Voyage;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\DepenseRepository;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;

/**
 * Le RÉSULTAT D'UN DÉPART : ce qu'il a rapporté, ce qu'il a coûté, ce qu'il reste.
 *
 * POURQUOI CE SERVICE EXISTE. La fiche d'un voyage composait ses chiffres côté frontend, en
 * additionnant les collections d'API (`/api/tickets?voyage.id=`, `/api/bagages?voyage.id=`…). Deux
 * défauts en découlaient, tous deux invisibles :
 *
 *  1. CES COLLECTIONS SONT FILTRÉES PAR GARE. `GareScopeExtension` s'applique à toute collection
 *     d'une entité de périmètre, si bien que le MÊME voyage affichait une recette DIFFÉRENTE selon
 *     le lecteur — mesuré sur `LI-ABI-KOR-0001-V3` : 38 000 pour le chef d'Adjamé, 30 000 pour celui
 *     de Korhogo, l'écart étant une réservation Adjamé → Bouaké que Korhogo ne voit pas. Or la
 *     recette d'un départ est une propriété DU DÉPART : deux personnes qui ouvrent la même fiche
 *     doivent lire le même chiffre.
 *  2. LES RÈGLES SE DUPLIQUAIENT. Le frontend comptait les bagages PERDU hors recette et oubliait
 *     `Courrier::$fraissuivi`, alors que les repositories faisaient l'inverse. Deux écrans, deux
 *     définitions, aucun moyen de s'en apercevoir.
 *
 * LES RÈGLES SONT CELLES DE {@see RecetteGareService}, sans exception :
 *  - billets : VALIDE, et `reservation IS NULL` — la recette d'une réservation est reconnue à SON
 *    paiement, le billet émis ensuite ne la recompte pas ;
 *  - réservations : toutes les PAYÉES (billet émis ou non), pénalité de report comprise ;
 *  - bagages : tout sauf ANNULE, PERDU compris (il a été payé) ;
 *  - courriers : tout sauf ANNULE, `montant + fraissuivi`.
 *
     * !! LES COURRIERS PEUVENT ÊTRE HORS CHIFFRE D'AFFAIRES (`ConfigRecette::$courriershorsca`) : une
     * compagnie qui traite le fret comme une activité à part le met à vrai. La règle, posée par
     * `RecetteGareService`, est que leur recette reste AFFICHÉE sur sa propre ligne mais ne compte dans
     * AUCUN total composite. Le résultat d'un départ ne peut donc pas les compter quand la
 * compagnie ne les compte pas : il serait plus généreux que le bénéfice auquel il contribue. La part
 * `courriers` reste servie à part, pour que l'écran puisse la montrer sans l'additionner.
 *
 * !! CE N'EST PAS UN BÉNÉFICE. Les charges non rattachées à ce départ — gasoil du parc, salaires,
 * dépannages, pièces — n'y sont pas, et ne peuvent pas y être sans clé de répartition inventée. On
 * écrit « résultat du départ », jamais « bénéfice », même prudence que le résultat d'exploitation
 * d'une gare. Et ce service ne s'ajoute à AUCUN total existant : il ne crée ni recette ni charge, il
 * relit les mêmes écritures sous un autre angle.
 */
class VoyageResultatService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private ReservationRepository $reservationRepository,
        private BagageRepository $bagageRepository,
        private CourrierRepository $courrierRepository,
        private DepenseRepository $depenseRepository,
        private CapaciteService $capaciteService,
        private ConfigRecetteService $configRecetteService
    )
    {
    }

    /**
     * @param bool $avecDepenses false quand l'acteur n'a pas le droit de lire des montants de charge :
     *                           les dépenses valent alors null, et NON zéro — « je ne sais pas » ne
     *                           s'écrit pas comme « il n'y en a pas »
     *
     * @return array{
     *     billets: array{montant: int, nb: int},
     *     reservations: array{montant: int, nb: int},
     *     bagages: array{montant: int, nb: int},
     *     courriers: array{montant: int, nb: int},
     *     recette: int,
     *     depenses: array{montant: int, nb: int}|null,
     *     resultat: int|null,
     *     lignesDepenses: list<\App\Entity\Depense>,
     *     picOccupation: int,
     *     capacite: int,
     *     placesRestantes: int,
     *     tauxRemplissage: int,
     *     troncons: list<array<string, mixed>>
     * }
     */
    public function pour(Voyage $voyage, bool $avecDepenses = true): array
    {
        $voyageId = (int) $voyage->getId();
        $identreprise = (int) $voyage->getIdentreprise();

        $billets = $this->ticketRepository->recettePourVoyage($voyageId, $identreprise);
        $reservations = $this->reservationRepository->recettePayeePourVoyage($voyageId, $identreprise);
        $bagages = $this->bagageRepository->recettePourVoyage($voyageId, $identreprise);
        $courriers = $this->courrierRepository->recettePourVoyage($voyageId, $identreprise);

        // Hors CA : le fret ne rejoint pas le total, mais 'courriers' reste servi pour l'affichage.
        $courriersDansLeCa = $this->configRecetteService->courriersHorsCa($identreprise)
            ? 0
            : $courriers['montant'];

        $recette = $billets['montant'] + $reservations['montant'] + $bagages['montant'] + $courriersDansLeCa;

        $capacite = $this->capaciteService->capaciteEffective($voyage) ?? 0;
        $pic = $this->capaciteService->occupationMaximale($voyage, $identreprise);

        $depenses = $avecDepenses
            ? $this->depenseRepository->totalPourVoyage($voyageId, $identreprise)
            : null;
        // Les lignes viennent du MÊME repository, sous le même périmètre ouvert : c'est ce qui garantit
        // que le tableau de la fiche et son total se recoupent au franc.
        $lignes = $avecDepenses
            ? $this->depenseRepository->findPourVoyage($voyageId, $identreprise)
            : [];

        return [
            'billets' => $billets,
            'reservations' => $reservations,
            'bagages' => $bagages,
            'courriers' => $courriers,
            'recette' => $recette,
            'depenses' => $depenses,
            'resultat' => $depenses !== null ? $recette - $depenses['montant'] : null,
            'lignesDepenses' => $lignes,
            /*
                L'OCCUPATION vient de 'CapaciteService', l'autorité de la capacité — jamais d'un comptage
                refait ailleurs. C'est la règle que le README désigne comme la plus facile à faire
                régresser de l'application : on compte des SIÈGES sur des intervalles, pas des
                passagers, et un billet évincé n'occupe rien.
            */
            'picOccupation' => $pic,
            'capacite' => $capacite,
            'placesRestantes' => max(0, $capacite - $pic),
            'tauxRemplissage' => $capacite > 0 ? (int) round($pic / $capacite * 100) : 0,
            'troncons' => $this->capaciteService->occupationParTroncon($voyage, $identreprise),
        ];
    }
}
