<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629111123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fidélité (carte à tampons) : table programme_fidelite (config par entreprise) + client.fidelite/cartefidelite/dateadhesion (adhésion) + ticket.fidelite_recompense (billet-récompense).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE programme_fidelite (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, seuil INT DEFAULT 10 NOT NULL, recompense_pourcentage INT DEFAULT 100 NOT NULL, actif TINYINT DEFAULT 1 NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE client ADD fidelite TINYINT DEFAULT 0 NOT NULL, ADD cartefidelite VARCHAR(50) DEFAULT NULL, ADD dateadhesion DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD fidelite_recompense TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE programme_fidelite');
        $this->addSql('ALTER TABLE client DROP fidelite, DROP cartefidelite, DROP dateadhesion');
        $this->addSql('ALTER TABLE ticket DROP fidelite_recompense');
    }
}
