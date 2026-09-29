<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Voyage;
use App\Repository\DepenseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture d'un voyage — FICHE ou LISTE —, enrichie de deux champs dérivés : la frise des HORAIRES par
 * gare (fiche seule, cf. {@see Voyage::$horaires}) et le TOTAL DES CHARGES rattachées au départ
 * (fiche ET liste, cf. {@see Voyage::$depensestotal}).
 *
 * On se BRANCHE au pipeline natif d'API Platform (on ne le remplace pas) : sécurité, extensions de
 * périmètre et sérialisation s'appliquent normalement — on se contente de poser un champ dérivé sur
 * l'entité renvoyée, comme le fait TicketProvider pour le repère 'evince'.
 *
 * Le calcul des horaires reprend celui du manifeste (VoyageManifesteController) : heure PRÉVUE = somme
 * des tronçons depuis l'origine effective ; heures RÉELLES = entité Passage ; retard = réel − prévu,
 * mesuré sur l'ARRIVÉE (ou sur le départ à l'origine, qui n'a pas d'arrivée). Rien n'est stocké :
 * la frise suit d'elle-même une replanification du départ ou une correction des durées.
 *
 * LE TOTAL DES CHARGES COÛTE UNE REQUÊTE PAR PAGE, pas une par ligne. On rassemble les identifiants
 * de la page, puis `DepenseRepository::totauxParVoyages()` ramène tous les totaux d'un coup. Écrire
 * naïvement `SUM` par voyage aurait produit un N+1 de 25 requêtes — le genre de coût qu'on ne voit
 * jamais en développement et qui se paie en production.
 *
 * Le paginator est renvoyé INTACT : on ne fait que poser un champ sur des entités déjà chargées, qui
 * restent celles de l'identity map lors de la sérialisation (même procédé que TicketProvider).
 */
final class VoyageProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        private readonly ReservationEcheanceService $echeance,
        private readonly DepenseRepository $depenseRepository,
        private readonly Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);

            $voyages = [];
            foreach ($data as $voyage) {
                if ($voyage instanceof Voyage) {
                    $voyages[] = $voyage;
                }
            }
            $this->poserLesCharges($voyages);

            return $data;
        }

        $voyage = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($voyage instanceof Voyage) {
            $voyage->setHoraires($this->horaires($voyage));
            $this->poserLesCharges([$voyage]);
        }

        return $voyage;
    }

    /**
     * Pose le total des charges sur un lot de voyages, en UNE requête.
     *
     * La permission est vérifiée une fois pour tout le lot : sans `DEPENSE_VOIR`, on ne lance même pas
     * la requête et le champ reste NUL — « je n'ai pas le droit de savoir » et non « zéro ».
     *
     * @param list<Voyage> $voyages
     */
    private function poserLesCharges(array $voyages): void
    {
        if ($voyages === [] || !$this->security->isGranted('VOIR', 'Depense')) {
            return;
        }

        $ids = [];
        foreach ($voyages as $voyage) {
            if ($voyage->getId() !== null) {
                $ids[] = (int) $voyage->getId();
            }
        }

        $identreprise = (int) ($voyages[0]->getIdentreprise() ?? 0);
        $totaux = $this->depenseRepository->totauxParVoyages($ids, $identreprise);

        foreach ($voyages as $voyage) {
            // Le repository omet les voyages sans charge : ici, « rien » s'écrit 0 — l'acteur a le
            // droit de savoir, et la réponse est qu'il n'y en a pas.
            $voyage->setDepensestotal($totaux[(int) $voyage->getId()] ?? 0);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function horaires(Voyage $voyage): array
    {
        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            return []; // sans ligne, aucun arrêt : pas de frise
        }

        $arrets = $ligne->getArrets()->toArray();
        usort($arrets, static fn ($a, $b) => (int) $a->getOrdre() <=> (int) $b->getOrdre());

        // Passages réels indexés par gare (au plus un par gare, cf. contrainte unique).
        $passages = [];
        foreach ($voyage->getPassages() as $passage) {
            $gareId = $passage->getGare()?->getId();
            if ($gareId !== null) {
                $passages[$gareId] = $passage;
            }
        }

        $dernierOrdre = $arrets === [] ? null : (int) end($arrets)->getOrdre();
        $gareCouranteId = $voyage->getGarecourante()?->getId();

        $horaires = [];
        foreach ($arrets as $arret) {
            $gare = $arret->getGare();
            if ($gare === null) {
                continue;
            }
            $gareId = $gare->getId();
            $ordre = (int) $arret->getOrdre();

            $heurePrevue = $this->echeance->heurePassage($voyage, $gare);
            $passage = $passages[$gareId] ?? null;
            $arrivee = $passage?->getArriveeReelle();
            // À l'origine il n'y a pas d'arrivée : le retard se lit sur le départ réel.
            $reference = $arrivee ?? $passage?->getDepartReelle();
            $retard = ($heurePrevue !== null && $reference !== null)
                ? (int) round(($reference->getTimestamp() - $heurePrevue->getTimestamp()) / 60)
                : null;

            $horaires[] = [
                'id' => $gareId,
                'libelle' => $gare->getLibelle(),
                'ordre' => $ordre,
                'role' => $ordre === 0 ? 'depart' : ($ordre === $dernierOrdre ? 'terminus' : 'intermediaire'),
                'heurePrevue' => $heurePrevue?->format('H:i'),
                'arriveeReelle' => $arrivee?->format('H:i'),
                'departReelle' => $passage?->getDepartReelle()?->format('H:i'),
                'retardMinutes' => $retard,
                'tempsArretMinutes' => $passage?->getTempsArretMinutes(),
                // Position ACTUELLE du car : permet de situer le voyage sur la frise.
                'courante' => $gareCouranteId !== null && $gareCouranteId === $gareId,
            ];
        }

        return $horaires;
    }
}
