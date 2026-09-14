<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Voyage;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Avance de la POSITION du car le long de sa ligne, déclarée par le vendeur à bord.
 *
 * Extrait de {@see App\State\AvancerCommercialProcessor} pour que la voie EN LIGNE et le REJEU d'une
 * file hors ligne appliquent exactement la même règle. La dupliquer la ferait dériver — et une
 * position fausse fait mentir les trajets vendables, les réservations échues et le suivi client.
 *
 * IDEMPOTENT, sur le modèle de {@see VoyageDepartService::marquerDepart()} : rend `true` si la
 * position vient d'avancer, `false` si le car était DÉJÀ à cette gare ou au-delà. Cette distinction
 * est ce qui permet de rejouer une file sans traiter un doublon comme une erreur : hors ligne, le
 * commercial a pu déclarer une arrivée, perdre le réseau avant la réponse, et la redéclarer.
 *
 * L'avance est MONOTONE : un car ne revient pas en arrière sur sa ligne.
 */
class AvanceePositionService
{
    public function __construct(
        private readonly PassageService $passageService,
        private readonly ActiviteLogger $activiteLogger
    )
    {
    }

    /**
     * @param DateTimeImmutable|null $instant moment RÉEL de l'arrivée. Hors ligne, c'est celui que le
     *                                       téléphone a horodaté : l'heure de la synchronisation
     *                                       ferait croire à un car immobile puis téléporté.
     *
     * @return bool true si la position vient d'avancer, false si elle y était déjà (rejeu)
     */
    public function avancer(Voyage $voyage, Gare $cible, ?DateTimeImmutable $instant = null): bool
    {
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé');
        }

        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }

        $cibleId = $cible->getId();
        if (!isset($ordreParGare[$cibleId])) {
            throw new BadRequestHttpException('Cette gare n\'est pas un arrêt de la ligne du voyage');
        }

        // Position courante (défaut = origine EFFECTIVE : gareprovenance pour un départ partiel)
        $courante = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        $ordreCourant = $courante ? ($ordreParGare[$courante->getId()] ?? 0) : 0;

        /*
            Déjà à cette gare ou plus loin : ce n'est PAS une erreur, c'est un rejeu. L'appelant en
            ligne le traduira en refus (le commercial croit avancer, il n'avance pas), l'appelant hors
            ligne en « déjà synchronisé ».
        */
        if ($ordreParGare[$cibleId] <= $ordreCourant) {
            return false;
        }

        $voyage->setGarecourante($cible);

        // Le commercial déclare que le car est ARRIVÉ ici. 'PassageService' est idempotent : le
        // premier horodatage fait foi, un rejeu ne le réécrit pas.
        $this->passageService->marquerArrivee($voyage, $cible, $instant ?? new DateTimeImmutable());

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_POSITION,
            sprintf('Position du car avancée à %s', (string) $cible->getLibelle()),
            (int) $voyage->getId()
        );

        return true;
    }
}
