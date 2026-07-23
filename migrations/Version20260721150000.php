<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Billet : désistement imputable à la compagnie (relogement d'un billet évincé par la priorité amont).
 *
 * Persisté au moment du report parce que l'information ne peut pas être re-dérivée après coup : une
 * fois le billet passé REPORTE, il sort de CapaciteService::billetsEvinces() (qui ne lit que les
 * VALIDE). Sert à isoler ces reports des désistements VOLONTAIRES dans le taux de désistement — la
 * compagnie les a provoqués, ce ne sont pas des renoncements du client.
 */
final class Version20260721150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billet : désistement imputable à la compagnie (report d\'un évincé)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD desistement_imputable_compagnie TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP COLUMN desistement_imputable_compagnie');
    }
}
