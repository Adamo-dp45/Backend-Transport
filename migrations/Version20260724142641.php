<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260724142641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alerte (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, type VARCHAR(60) NOT NULL, severite VARCHAR(20) NOT NULL, portee VARCHAR(20) NOT NULL, famille VARCHAR(30) NOT NULL, idgare INT DEFAULT NULL, cle VARCHAR(255) NOT NULL, titre VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, sourcetype VARCHAR(50) DEFAULT NULL, sourceid INT DEFAULT NULL, donnees JSON DEFAULT NULL, statut VARCHAR(20) NOT NULL, lue_par INT DEFAULT NULL, lue_le DATETIME DEFAULT NULL, resolue_le DATETIME DEFAULT NULL, identreprise INT DEFAULT NULL, INDEX idx_alerte_ent_cle (identreprise, cle), INDEX idx_alerte_ent_statut (identreprise, statut), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE alerte');
    }
}
