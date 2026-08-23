<?php

namespace App\DataFixtures\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Purge les tables en suspendant temporairement les contraintes de clé étrangère.
 *
 * POURQUOI : le schéma contient un CYCLE assumé entre 'ticket' et 'reservation' — un billet peut
 * être issu d'une réservation ('ticket.reservation_id'), et une réservation pointe vers le billet
 * qu'elle a fait émettre ('reservation.ticket_id'). Aucun ordre de suppression ne satisfait les
 * deux contraintes à la fois : le purger natif échoue au rechargement (« Cannot delete or update a
 * parent row »), et la variante TRUNCATE échoue elle aussi (« Cannot truncate a table referenced
 * in a foreign key constraint »). Le premier chargement passe seulement parce que la base est vide.
 *
 * Le cycle est légitime côté métier ; c'est donc au purger de s'adapter. On désactive les
 * contraintes le temps de la purge, puis on les rétablit — y compris si la purge échoue.
 *
 * Sans effet hors MySQL : sur une autre plateforme, la purge native s'applique telle quelle.
 */
final class PurgerSansContrainte implements ORMPurgerInterface
{
    public function __construct(
        private PurgerInterface $purger,
        private EntityManagerInterface $em
    ) {
    }

    public function purge(): void
    {
        $connection = $this->em->getConnection();
        $mysql = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;

        if (!$mysql) {
            $this->purger->purge();

            return;
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $this->purger->purge();
        } finally {
            // 'finally' : une purge interrompue ne doit jamais laisser la base sans ses contraintes.
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;

        if ($this->purger instanceof ORMPurgerInterface) {
            $this->purger->setEntityManager($em);
        }
    }
}
