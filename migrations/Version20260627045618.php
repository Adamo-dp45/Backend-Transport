<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627045618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Isolation tenant : Detailpersonnel et Detailcourrier deviennent EntrepriseOwned (colonnes EntityBase + identreprise) et backfill de identreprise depuis le parent.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detailcourrier ADD created_at DATETIME DEFAULT NULL, ADD created_from_ip VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD updated_from_ip VARCHAR(255) DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_from_ip VARCHAR(255) DEFAULT NULL, ADD created_by INT DEFAULT NULL, ADD updated_by INT DEFAULT NULL, ADD deleted_by INT DEFAULT NULL, ADD etatdelete TINYINT DEFAULT 0, ADD identreprise INT DEFAULT NULL');
        $this->addSql('ALTER TABLE detailpersonnel ADD created_at DATETIME DEFAULT NULL, ADD created_from_ip VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD updated_from_ip VARCHAR(255) DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_from_ip VARCHAR(255) DEFAULT NULL, ADD created_by INT DEFAULT NULL, ADD updated_by INT DEFAULT NULL, ADD deleted_by INT DEFAULT NULL, ADD etatdelete TINYINT DEFAULT 0, ADD identreprise INT DEFAULT NULL');

        // Backfill de l'entreprise depuis le parent pour les lignes existantes (sinon invisibles car
        // EntrepriseScopeExtension filtre identreprise = :entreprise).
        $this->addSql('UPDATE detailcourrier dc INNER JOIN courrier c ON dc.courrier_id = c.id SET dc.identreprise = c.identreprise WHERE dc.identreprise IS NULL');
        $this->addSql('UPDATE detailpersonnel dp INNER JOIN personnel p ON dp.personnel_id = p.id SET dp.identreprise = p.identreprise WHERE dp.identreprise IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detailcourrier DROP created_at, DROP created_from_ip, DROP updated_at, DROP updated_from_ip, DROP deleted_at, DROP deleted_from_ip, DROP created_by, DROP updated_by, DROP deleted_by, DROP etatdelete, DROP identreprise');
        $this->addSql('ALTER TABLE detailpersonnel DROP created_at, DROP created_from_ip, DROP updated_at, DROP updated_from_ip, DROP deleted_at, DROP deleted_from_ip, DROP created_by, DROP updated_by, DROP deleted_by, DROP etatdelete, DROP identreprise');
    }
}
