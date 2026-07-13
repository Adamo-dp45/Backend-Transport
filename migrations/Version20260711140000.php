<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Consolidation de la disposition des sièges vers un MODÈLE UNIQUE (le plan/grille `plansieges`).
 * On retire les colonnes paramétriques `dispositionschema` et `banquettearriere` : ces cas se saisissent
 * désormais directement en plan (via le générateur du formulaire).
 */
final class Version20260711140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Car : suppression de dispositionschema + banquettearriere (modèle unique = plansieges)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car DROP dispositionschema, DROP banquettearriere');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE car ADD dispositionschema VARCHAR(20) DEFAULT 'STANDARD' NOT NULL, ADD banquettearriere INT DEFAULT 0 NOT NULL");
    }
}
