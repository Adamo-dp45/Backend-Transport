<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Config RECETTE dédiée (singleton par entreprise) : table config_recette avec le drapeau
 * courriershorsca (exclure les revenus courriers du chiffre d'affaires). Défaut 0 = comportement
 * historique (courriers inclus dans le CA).
 */
final class Version20260711100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Config recette dédiée (table config_recette) : drapeau courriershorsca';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE config_recette (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, courriershorsca TINYINT DEFAULT 0 NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE config_recette');
    }
}
