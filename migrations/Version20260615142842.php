<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615142842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detailcourrier ADD statut VARCHAR(80) NOT NULL');
        $this->addSql('ALTER TABLE tarif CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE deleted_at deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tarif RENAME INDEX idx_tarif_garedepart TO IDX_E7189C916887400');
        $this->addSql('ALTER TABLE tarif RENAME INDEX idx_tarif_garearrivee TO IDX_E7189C9B466CD0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE detailcourrier DROP statut');
        $this->addSql('ALTER TABLE tarif CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE deleted_at deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tarif RENAME INDEX idx_e7189c916887400 TO IDX_tarif_garedepart');
        $this->addSql('ALTER TABLE tarif RENAME INDEX idx_e7189c9b466cd0 TO IDX_tarif_garearrivee');
    }
}
