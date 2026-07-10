<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Config remise SÉPARÉE (comme fidélité/réservation) : table config_remise (singleton par entreprise) et
 * suppression de la colonne entreprise.remisemaxpourcentage (déplacée hors de l'entité Entreprise).
 * S'exécute après Version20260710120000 (qui avait ajouté la colonne) → le DROP réussit dans tous les cas.
 */
final class Version20260710130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config remise dédiée (table config_remise) + suppression entreprise.remisemaxpourcentage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE config_remise (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, maxpourcentage INT DEFAULT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE entreprise DROP remisemaxpourcentage');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise ADD remisemaxpourcentage INT DEFAULT NULL');
        $this->addSql('DROP TABLE config_remise');
    }
}
