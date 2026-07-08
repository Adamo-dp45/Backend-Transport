<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Désistement d'un billet : ajout sur 'ticket' du statut métier (VALIDE | REPORTE | ANNULE), du lien
 * d'auto-référence 'ticket_origine_id' (billet d'origine d'un report) et de la traçabilité du désistement
 * (datedesistement, motifdesistement). Les billets existants prennent le statut 'VALIDE' par défaut.
 */
final class Version20260620080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes ticket.statut / ticket_origine_id / datedesistement / motifdesistement (désistement : report ou annulation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ticket ADD statut VARCHAR(20) DEFAULT 'VALIDE' NOT NULL, ADD ticket_origine_id INT DEFAULT NULL, ADD datedesistement DATETIME DEFAULT NULL, ADD motifdesistement VARCHAR(255) DEFAULT NULL");
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA396140BDA FOREIGN KEY (ticket_origine_id) REFERENCES ticket (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA396140BDA ON ticket (ticket_origine_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA396140BDA');
        $this->addSql('DROP INDEX IDX_97A0ADA396140BDA ON ticket');
        $this->addSql('ALTER TABLE ticket DROP statut, DROP ticket_origine_id, DROP datedesistement, DROP motifdesistement');
    }
}
