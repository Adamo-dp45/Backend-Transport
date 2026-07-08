<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Approche par statut pour l'annulation d'un approvisionnement : ajout de la colonne 'approvisionnement.statut'
 * (VALIDE | ANNULE). Un approvisionnement annulé reste visible mais est exclu des coûts ; l'annulation retire
 * du stock les pièces entrées. Les approvisionnements existants prennent le statut 'VALIDE' par défaut.
 */
final class Version20260620090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la colonne approvisionnement.statut (VALIDE | ANNULE) pour l\'annulation d\'un approvisionnement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE approvisionnement ADD statut VARCHAR(20) DEFAULT 'VALIDE' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE approvisionnement DROP statut');
    }
}
