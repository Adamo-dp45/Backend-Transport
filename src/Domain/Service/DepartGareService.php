<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Voyage;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Départ du car d'une gare INTERMÉDIAIRE, déclaré par le vendeur à bord.
 *
 * Le pendant de {@see AvanceePositionService} : celui-ci horodate l'ARRIVÉE, celui-là le DÉPART.
 * Ensemble ils donnent le temps d'arrêt en gare — la seule mesure dont on dispose pour recaler les
 * durées de tronçon. Extrait de {@see App\State\RepartirVoyageProcessor} pour que la voie EN LIGNE et
 * le REJEU d'une file hors ligne appliquent la même règle : les dupliquer les ferait dériver.
 *
 * Le service ne porte QUE les règles métier. L'autorisation reste chez l'appelant, parce qu'elle
 * diffère selon la voie : en ligne, le commercial, l'agent de la gare où est le car ou un admin ;
 * hors ligne, seul le commercial du voyage — le contrôleur de synchronisation l'a déjà établi.
 *
 * IDEMPOTENT : rend `true` si le départ vient d'être enregistré, `false` s'il l'était déjà. Un
 * commercial a pu déclarer le départ, perdre le réseau avant la réponse, et le redéclarer.
 */
class DepartGareService
{
    public function __construct(
        private readonly PassageService $passageService,
        private readonly ActiviteLogger $activiteLogger
    )
    {
    }

    /**
     * @param DateTimeImmutable|null $instant moment RÉEL du départ. Hors ligne, c'est celui que le
     *                                       téléphone a horodaté : prendre l'heure de la
     *                                       synchronisation donnerait un temps d'arrêt fantaisiste,
     *                                       et c'est précisément ce que ce geste sert à mesurer.
     *
     * @return bool true si le départ vient d'être enregistré, false s'il l'était déjà (rejeu)
     */
    public function repartir(Voyage $voyage, ?DateTimeImmutable $instant = null): bool
    {
        if ($voyage->getDatedepartreelle() === null) {
            throw new BadRequestHttpException('Le voyage n\'est pas encore parti.');
        }
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage est clôturé.');
        }

        $courante = $voyage->getGarecourante();
        $origine = $voyage->getOrigineEffective();
        if ($courante === null || $origine === null) {
            throw new BadRequestHttpException('La position courante du car est inconnue.');
        }

        /*
            L'origine et le terminus sont exclus, et pour des raisons distinctes : le départ de
            l'origine a déjà été horodaté au démarrage du voyage, et d'un terminus le car ne repart
            pas — c'est la fin de la course.
        */
        if ($courante->getId() === $origine->getId()) {
            throw new BadRequestHttpException('Le car est encore à l\'origine : son départ a été enregistré au démarrage.');
        }
        $terminus = $voyage->getLigne()?->getGareterminus();
        if ($terminus !== null && $courante->getId() === $terminus->getId()) {
            throw new BadRequestHttpException('Le car est au terminus : il n\'en repart pas.');
        }

        /*
            Le passage est lu VIA LE SERVICE, jamais par une requête directe. Dans un lot de
            synchronisation, l'arrivée vient d'être posée par l'opération précédente et n'est pas
            encore flushée : une requête ne la verrait pas, et ce départ serait refusé au motif que le
            car n'est jamais arrivé.
        */
        $passage = $this->passageService->connu($voyage, $courante);
        if ($passage === null || $passage->getArriveeReelle() === null) {
            throw new BadRequestHttpException('L\'arrivée du car à ' . $courante->getLibelle() . ' n\'a pas encore été enregistrée.');
        }

        /*
            Départ déjà horodaté : ce n'est PAS une erreur, c'est un rejeu. L'appelant en ligne le
            traduira en refus (le commercial croit déclarer, il ne déclare rien de neuf), l'appelant
            hors ligne en « déjà synchronisé ».
        */
        if ($passage->getDepartReelle() !== null) {
            return false;
        }

        $this->passageService->marquerDepart($voyage, $courante, $instant ?? new DateTimeImmutable());

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_POSITION,
            sprintf('Départ du car de %s', (string) $courante->getLibelle()),
            (int) $voyage->getId()
        );

        return true;
    }

    /** La gare d'où le car repart — sa position courante. Utile à l'appelant pour son message. */
    public function gareDeDepart(Voyage $voyage): ?Gare
    {
        return $voyage->getGarecourante();
    }
}
