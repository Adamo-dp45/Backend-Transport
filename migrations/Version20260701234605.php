<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Référentiel Ville : la gare passe du champ texte libre 'ville' à une relation 'ville_id' vers la
 * table 'ville'. Backfill des villes distinctes existantes (par entreprise) puis rattachement des gares.
 */
final class Version20260701234605 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Référentiel Ville + FK gare.ville_id (remplace le champ texte gare.ville, avec backfill)';
    }

    public function up(Schema $schema): void
    {
        // 1. Table du référentiel
        $this->addSql('CREATE TABLE ville (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        // 2. Colonne FK (nullable) — on garde encore l'ancien champ texte le temps du backfill
        $this->addSql('ALTER TABLE gare ADD ville_id INT DEFAULT NULL');

        // 3. Backfill : une Ville par couple (entreprise, nom de ville) distinct, puis rattachement des gares
        $this->addSql("INSERT INTO ville (nom, identreprise, created_at, etatdelete) SELECT g.ville, g.identreprise, NOW(), 0 FROM gare g WHERE g.ville IS NOT NULL AND g.ville <> '' GROUP BY g.identreprise, g.ville");
        $this->addSql('UPDATE gare g JOIN ville v ON v.nom = g.ville AND v.identreprise <=> g.identreprise SET g.ville_id = v.id');

        // 4. Contrainte + index, puis suppression de l'ancien champ texte
        $this->addSql('ALTER TABLE gare ADD CONSTRAINT FK_EE713F12A73F0036 FOREIGN KEY (ville_id) REFERENCES ville (id)');
        $this->addSql('CREATE INDEX IDX_EE713F12A73F0036 ON gare (ville_id)');
        $this->addSql('ALTER TABLE gare DROP ville');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gare ADD ville VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE gare g JOIN ville v ON v.id = g.ville_id SET g.ville = v.nom');
        $this->addSql('ALTER TABLE gare DROP FOREIGN KEY FK_EE713F12A73F0036');
        $this->addSql('DROP INDEX IDX_EE713F12A73F0036 ON gare');
        $this->addSql('ALTER TABLE gare DROP ville_id');
        $this->addSql('DROP TABLE ville');
    }
}
