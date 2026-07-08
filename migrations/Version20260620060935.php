<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620060935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de l\'entité Beneficiaire et des colonnes ticket.remise / ticket.beneficiaire_id (remise + bénéficiaire)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE beneficiaire (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, categorie VARCHAR(50) NOT NULL, contact VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ticket ADD remise INT DEFAULT 0 NOT NULL, ADD beneficiaire_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA35AF81F68 FOREIGN KEY (beneficiaire_id) REFERENCES beneficiaire (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA35AF81F68 ON ticket (beneficiaire_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE beneficiaire');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA35AF81F68');
        $this->addSql('DROP INDEX IDX_97A0ADA35AF81F68 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP remise, DROP beneficiaire_id');
    }
}
