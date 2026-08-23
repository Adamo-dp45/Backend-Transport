<?php

namespace App\DataFixtures;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Antidate les enregistrements APRÈS le flush.
 *
 * POURQUOI : 'EntityBase::onPrePersist()' écrase 'createdAt' et 'updatedAt' avec l'heure courante à
 * CHAQUE insertion. Tout 'setCreatedAt()' appelé avant le flush est donc perdu, et le jeu de données
 * se retrouve intégralement daté de la seconde du chargement. Or les statistiques (recette par
 * période, désistements, actions critiques par agent, cf. 'TicketRepository') filtrent sur
 * 'createdAt' : sans antidatage, aucun graphique ni bilan de journée n'a de relief — tout tombe dans
 * le même jour.
 *
 * COMMENT : on enregistre l'intention pendant la construction, puis on repasse en DQL une fois les
 * identifiants attribués. Les UPDATE sont GROUPÉS par classe et par date (et non un par ligne).
 *
 * NB : un UPDATE DQL court-circuite l'identity map — les objets encore en mémoire gardent l'ancienne
 * date. Sans conséquence ici : les fixtures ne relisent pas ce qu'elles viennent d'écrire.
 */
trait HorodatageTrait
{
    /** @var list<array{0: object, 1: DateTimeImmutable}> */
    private array $aHorodater = [];

    /**
     * Marque une entité pour être antidatée au flush suivant. Renvoie l'entité pour rester chaînable.
     *
     * @template T of object
     * @param T $entity
     * @return T
     */
    private function daterA(object $entity, DateTimeImmutable $date): object
    {
        $this->aHorodater[] = [$entity, $date];

        return $entity;
    }

    /**
     * À appeler APRÈS '$manager->flush()' : sans identifiants, il n'y a rien à mettre à jour.
     */
    private function appliquerHorodatages(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface || $this->aHorodater === []) {
            $this->aHorodater = [];

            return;
        }

        /** @var array<class-string, array<string, list<int>>> $paquets */
        $paquets = [];
        foreach ($this->aHorodater as [$entity, $date]) {
            $id = method_exists($entity, 'getId') ? $entity->getId() : null;
            if ($id === null) {
                continue; // entité non flushée : rien à antidater
            }
            $paquets[$entity::class][$date->format('Y-m-d H:i:s')][] = $id;
        }

        foreach ($paquets as $classe => $parDate) {
            foreach ($parDate as $date => $ids) {
                $manager->createQuery(sprintf(
                    'UPDATE %s e SET e.createdAt = :date, e.updatedAt = :date WHERE e.id IN (:ids)',
                    $classe
                ))
                    ->setParameter('date', new DateTimeImmutable($date))
                    ->setParameter('ids', $ids)
                    ->execute();
            }
        }

        $this->aHorodater = [];
    }
}
