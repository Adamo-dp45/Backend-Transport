<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réservation : marqueur 'penalite_exoneree'.
 *
 * Quand la compagnie AVANCE un départ, les clients déjà payés voient leur fenêtre de présentation
 * raccourcie sans l'avoir demandé. S'ils manquent le car, le no-show ne leur est pas imputable : le
 * report doit se faire sans pénalité. Le marqueur est posé à la replanification des échéances
 * (ReservationEcheanceService) et consommé à la régularisation.
 */
final class Version20260720100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réservation : marqueur d\'exonération de pénalité (no-show causé par un départ avancé)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD penaliteexoneree TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP COLUMN penaliteexoneree');
    }
}
