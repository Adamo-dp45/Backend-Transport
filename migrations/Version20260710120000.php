<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Anti-remise-abusive : plafond de remise MANUELLE (% du tarif) configurable par entreprise. null = pas
 * de plafond. N'affecte pas les récompenses fidélité (chemin séparé dans TicketProcessor).
 */
final class Version20260710120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Entreprise : remisemaxpourcentage (plafond de remise manuelle)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise ADD remisemaxpourcentage INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE entreprise DROP remisemaxpourcentage');
    }
}
