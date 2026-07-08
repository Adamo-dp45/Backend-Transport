<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Billet issu d'une RÉSERVATION : nouvelle relation Ticket.reservation (nullable).
 * Un billet émis depuis une réservation a été payé sur le compte (mobile/bancaire) de l'administrateur,
 * PAS dans le tiroir de la gare → il doit être exclu de la recette gare (3e canal, cf. commercial).
 * Backfill depuis reservation.ticket_id (lien inverse déjà existant).
 */
final class Version20260705100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ticket.reservation (canal réservation, exclu de la recette gare)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD reservation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_TICKET_RESERVATION FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('CREATE INDEX IDX_TICKET_RESERVATION ON ticket (reservation_id)');

        // Backfill : rattache les billets déjà émis à leur réservation d'origine.
        $this->addSql('UPDATE ticket t INNER JOIN reservation r ON r.ticket_id = t.id SET t.reservation_id = r.id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_TICKET_RESERVATION');
        $this->addSql('DROP INDEX IDX_TICKET_RESERVATION ON ticket');
        $this->addSql('ALTER TABLE ticket DROP reservation_id');
    }
}
