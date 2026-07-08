<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701154635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression du champ redondant fournisseur.nom (libelle suffit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fournisseur DROP nom');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fournisseur ADD nom VARCHAR(255) NOT NULL');
    }
}
