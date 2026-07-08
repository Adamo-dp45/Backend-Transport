<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bagage : le BILLET devient OBLIGATOIRE (fin du modèle « pesée sans billet »). Un bagage suit
 * désormais toujours son billet (voyage, gares, identité, canal de vente). Les bagages historiques
 * sans billet — non rattachables — sont purgés avant de passer la colonne en NOT NULL.
 */
final class Version20260706100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage : ticket_id devient NOT NULL (billet obligatoire)';
    }

    public function up(Schema $schema): void
    {
        // Purge des bagages sans billet (impossibles sous le nouveau modèle).
        $this->addSql('DELETE FROM bagage WHERE ticket_id IS NULL');
        $this->addSql('ALTER TABLE bagage CHANGE ticket_id ticket_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage CHANGE ticket_id ticket_id INT DEFAULT NULL');
    }
}
