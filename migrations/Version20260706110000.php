<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bagage : canal de vente COMMERCIAL propre (snapshot du vendeur à bord au moment de l'enregistrement),
 * comme Ticket.commercial. On ne dérive PAS du billet : le commercial pourrait rattacher un billet qui
 * n'est pas le sien. Un bagage enregistré à bord par le commercial va dans SA recette (pas la gare).
 * Backfill best-effort depuis le billet (commercial du ticket) pour l'historique.
 */
final class Version20260706110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage : commercial_id (canal commercial propre, snapshot)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD commercial_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_BAGAGE_COMMERCIAL FOREIGN KEY (commercial_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_BAGAGE_COMMERCIAL ON bagage (commercial_id)');

        // Backfill historique : reprend le commercial du billet lié (meilleure estimation rétroactive).
        $this->addSql('UPDATE bagage b INNER JOIN ticket t ON b.ticket_id = t.id SET b.commercial_id = t.commercial_id WHERE t.commercial_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_BAGAGE_COMMERCIAL');
        $this->addSql('DROP INDEX IDX_BAGAGE_COMMERCIAL ON bagage');
        $this->addSql('ALTER TABLE bagage DROP commercial_id');
    }
}
