<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Plan de sièges EXPLICITE (Phase 2) : colonne `plansieges` (JSON) sur `car` = grille de numéros + trous
 * pour les bus atypiques. NULL = pas de plan explicite (on retombe sur le schéma paramétrique Phase 1).
 */
final class Version20260711130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Car : plansieges (JSON) — plan de sièges explicite pour bus atypiques';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car ADD plansieges JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE car DROP plansieges');
    }
}
