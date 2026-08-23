<?php

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * Isolation des tests par la base : mise à plat UNE fois par exécution, puis une transaction
 * annulée après chaque test.
 *
 * Vider les 50 tables au TRUNCATE avant chaque test était correct mais coûtait 3,6 s à chaque
 * fois — la quasi-totalité du temps de la suite. La transaction ramène ce coût à presque rien.
 *
 * Les transactions imbriquées sont converties en POINTS DE SAUVEGARDE : plusieurs services ouvrent
 * la leur ('ReservationCreationService::wrapInTransaction'), et sans savepoints leur commit interne
 * terminerait la transaction du test — plus rien ne serait annulé ensuite.
 *
 * Conséquence acceptée : les auto-increment ne reviennent pas en arrière. Aucun test ne doit donc
 * dépendre d'une valeur d'identifiant précise.
 */
trait BaseDeDonneesTestTrait
{
    /** @var list<string>|null noms des tables, résolus une fois pour toute la suite */
    private static ?array $tablesConnues = null;

    /** La mise à plat n'a lieu qu'au premier test du processus. */
    private static bool $baseMiseAPlat = false;

    private function ouvrirTransactionDeTest(Connection $connection): void
    {
        if (!self::$baseMiseAPlat) {
            $this->viderLaBase($connection);
            self::$baseMiseAPlat = true;
        }

        $connection->setNestTransactionsWithSavepoints(true);
        $connection->beginTransaction();
    }

    private function annulerTransactionDeTest(Connection $connection): void
    {
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    private function viderLaBase(Connection $connection): void
    {
        self::$tablesConnues ??= array_values(array_filter(
            $connection->createSchemaManager()->listTableNames(),
            // La table des migrations décrit l'état du SCHÉMA, pas des données applicatives : la
            // vider ferait croire à Doctrine qu'aucune migration n'a été jouée.
            static fn (string $table): bool => $table !== 'doctrine_migration_versions'
        ));

        // Le schéma contient un cycle assumé entre 'ticket' et 'reservation' : aucun ordre de
        // suppression ne satisfait les deux contraintes, on les suspend le temps de l'opération.
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::$tablesConnues as $table) {
                $connection->executeStatement('TRUNCATE TABLE ' . $connection->quoteSingleIdentifier($table));
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
