<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Voyage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture de la FICHE d'un voyage, enrichie de la frise des HORAIRES par gare
 * (cf. {@see Voyage::$horaires}).
 *
 * On se BRANCHE au pipeline natif d'API Platform (on ne le remplace pas) : sécurité, extensions de
 * périmètre et sérialisation s'appliquent normalement — on se contente de poser un champ dérivé sur
 * l'entité renvoyée, comme le fait TicketProvider pour le repère 'evince'.
 *
 * Le calcul reprend celui du manifeste (VoyageManifesteController) : heure PRÉVUE = somme des
 * tronçons depuis l'origine effective ; heures RÉELLES = entité Passage ; retard = réel − prévu,
 * mesuré sur l'ARRIVÉE (ou sur le départ à l'origine, qui n'a pas d'arrivée). Rien n'est stocké :
 * la frise suit d'elle-même une replanification du départ ou une correction des durées.
 */
final class VoyageProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly ReservationEcheanceService $echeance
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $voyage = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($voyage instanceof Voyage) {
            $voyage->setHoraires($this->horaires($voyage));
        }

        return $voyage;
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
