<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627152917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal d\'activité : table activite (qui a fait quoi — type, libellé, cible, auteur).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activite (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, type VARCHAR(60) NOT NULL, libelle VARCHAR(255) NOT NULL, cibletype VARCHAR(50) DEFAULT NULL, cibleid INT DEFAULT NULL, auteurnom VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, INDEX idx_activite_cible (cibletype, cibleid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activite');
    }
}
