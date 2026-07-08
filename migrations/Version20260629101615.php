<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629101615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entité Client (identité durable du passager, dédupliquée par téléphone) + FK ticket.client ; backfill depuis le texte libre existant.';
    }

    public function up(Schema $schema): void
    {
        // -- Schéma : nouvelle table client + FK nullable sur le ticket (snapshot texte libre conservé)
        $this->addSql('CREATE TABLE client (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE ticket ADD client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA319EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA319EB6921 ON ticket (client_id)');

        // -- Backfill : crée les clients à partir du texte libre existant des tickets,
        //    dédupliqués par (entreprise, téléphone normalisé = sans espaces). Le téléphone est la clé ;
        //    les tickets sans téléphone restent sans client (snapshot seul). Nom : un représentant
        //    non vide, à défaut le téléphone (la colonne nom est NOT NULL).
        $this->addSql(<<<'SQL'
            INSERT INTO client (nom, contact, identreprise, created_at, updated_at, etatdelete)
            SELECT COALESCE(MAX(nom), contact) AS nom, contact, identreprise, NOW(), NOW(), 0
            FROM (
                SELECT t.identreprise AS identreprise,
                       REPLACE(t.contactclient, ' ', '') AS contact,
                       NULLIF(TRIM(t.nomclient), '') AS nom
                FROM ticket t
                WHERE t.contactclient IS NOT NULL AND REPLACE(t.contactclient, ' ', '') <> '' AND t.deleted_at IS NULL
            ) src
            WHERE src.identreprise IS NOT NULL
            GROUP BY src.identreprise, src.contact
            SQL);

        // -- Liaison de la FK sur les tickets actifs (match par entreprise + téléphone normalisé)
        $this->addSql(<<<'SQL'
            UPDATE ticket t
            JOIN client c ON c.identreprise = t.identreprise AND c.contact = REPLACE(t.contactclient, ' ', '')
            SET t.client_id = c.id
            WHERE t.contactclient IS NOT NULL AND REPLACE(t.contactclient, ' ', '') <> '' AND t.deleted_at IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // FK d'abord, puis la table (sinon la contrainte bloque le DROP TABLE)
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA319EB6921');
        $this->addSql('DROP INDEX IDX_97A0ADA319EB6921 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP client_id');
        $this->addSql('DROP TABLE client');
    }
}
