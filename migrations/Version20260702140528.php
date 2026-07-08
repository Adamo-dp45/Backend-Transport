<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702140528 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ticket.commercial : canal de vente (vendeur à bord) figé sur le billet pour ventiler la recette gare / commercial';
    }

    public function up(Schema $schema): void
    {
        // Nettoyée : on ne garde QUE l'ajout de ticket.commercial_id (la dérive approvisionnement.verrouille /
        // renommage d'index entreprise détectée par le diff n'a rien à voir avec cette fonctionnalité).
        $this->addSql('ALTER TABLE ticket ADD commercial_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA37854071C FOREIGN KEY (commercial_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA37854071C ON ticket (commercial_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA37854071C');
        $this->addSql('DROP INDEX IDX_97A0ADA37854071C ON ticket');
        $this->addSql('ALTER TABLE ticket DROP commercial_id');
    }
}
