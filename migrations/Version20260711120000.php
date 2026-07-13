<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Disposition des sièges configurable par CAR (schéma de numérotation + banquette arrière).
 * NB : ces deux colonnes ont ensuite été SUPPRIMÉES (voir Version20260711140000) suite à la consolidation
 * vers un modèle unique « plan de sièges » (grille). Fichier conservé pour l'historique des migrations.
 */
final class Version20260711120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Car : dispositionschema + banquettearriere (remplacés ensuite par le plan unique)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE car ADD dispositionschema VARCHAR(20) DEFAULT 'STANDARD' NOT NULL, ADD banquettearriere INT DEFAULT 0 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car DROP dispositionschema, DROP banquettearriere');
    }
}
