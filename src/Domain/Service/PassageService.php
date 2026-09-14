<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Passage;
use App\Entity\Voyage;
use App\Repository\PassageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écriture centralisée des passages réels (arrivée / départ d'un voyage à une gare).
 *
 * find-or-create le Passage(voyage, gare) et pose l'horodatage. Ne FLUSHE PAS : les processors appelants
 * (départ, réception, avance, repartir, clôture) écrivent dans la même unité de travail que le voyage.
 *
 * Idempotent : on ne réécrit pas un horodatage déjà posé — le PREMIER passage fait foi (une réception qui
 * repasse, un double appel, ne réhorodate pas). Un cache par requête évite de créer deux fois le même
 * Passage avant le flush (findOneBy ne verrait pas l'entité non encore flushée).
 */
class PassageService
{
    /** @var array<string, Passage> "voyageId:gareId" => Passage (cache intra-requête) */
    private array $cache = [];

    public function __construct(
        private EntityManagerInterface $em,
        private PassageRepository $passageRepository
    ) {
    }

    /** Horodate l'ARRIVÉE réelle du car à cette gare (si pas déjà arrivée). */
    public function marquerArrivee(Voyage $voyage, ?Gare $gare, \DateTimeImmutable $instant): ?Passage
    {
        $passage = $this->pour($voyage, $gare);
        if ($passage !== null && $passage->getArriveeReelle() === null) {
            $passage->setArriveeReelle($instant);
        }

        return $passage;
    }

    /** Horodate le DÉPART réel du car de cette gare (si pas déjà reparti). */
    public function marquerDepart(Voyage $voyage, ?Gare $gare, \DateTimeImmutable $instant): ?Passage
    {
        $passage = $this->pour($voyage, $gare);
        if ($passage !== null && $passage->getDepartReelle() === null) {
            $passage->setDepartReelle($instant);
        }

        return $passage;
    }

    /**
     * Le passage DÉJÀ connu pour ce couple, SANS le créer. Null s'il n'y en a pas.
     *
     * Consulte le cache de requête AVANT la base, et c'est tout son intérêt : dans un lot de
     * synchronisation, l'arrivée qui vient d'être posée n'est pas encore flushée. Une requête ne la
     * verrait pas, et le départ qui la suit dans le même lot serait refusé au motif que le car n'est
     * jamais arrivé — alors que l'opération précédente vient précisément de le déclarer.
     */
    public function connu(Voyage $voyage, ?Gare $gare): ?Passage
    {
        if ($voyage->getId() === null || $gare === null || $gare->getId() === null) {
            return null;
        }

        $key = $voyage->getId() . ':' . $gare->getId();

        return $this->cache[$key] ?? $this->passageRepository->findOneParVoyageGare(
            $voyage->getId(),
            $gare->getId()
        );
    }

    /** find-or-create (persist sans flush). Null si le voyage n'est pas persisté ou la gare manque. */
    private function pour(Voyage $voyage, ?Gare $gare): ?Passage
    {
        if ($voyage->getId() === null || $gare === null || $gare->getId() === null) {
            return null;
        }
        $key = $voyage->getId() . ':' . $gare->getId();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $passage = $this->passageRepository->findOneParVoyageGare($voyage->getId(), $gare->getId());
        if ($passage === null) {
            $passage = (new Passage())
                ->setVoyage($voyage)
                ->setGare($gare)
                ->setIdentreprise($voyage->getIdentreprise());
            $this->em->persist($passage);
        }

        return $this->cache[$key] = $passage;
    }
}
