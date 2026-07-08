<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629205259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exploitation Voyage : renomme datedepartprevu->datedepartprevue et datefin->datearriveereelle (clôture = arrivée réelle), + ajoute datearriveeprevue (arrivée prévue) et datedepartreelle (départ réel).';
    }

    public function up(Schema $schema): void
    {
        // CHANGE = rename de colonne (conserve les données) ; datefin devient l'arrivée RÉELLE (= clôture).
        $this->addSql('ALTER TABLE voyage CHANGE datedepartprevu datedepartprevue DATETIME NOT NULL, CHANGE datefin datearriveereelle DATETIME DEFAULT NULL, ADD datearriveeprevue DATETIME DEFAULT NULL, ADD datedepartreelle DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage CHANGE datedepartprevue datedepartprevu DATETIME NOT NULL, CHANGE datearriveereelle datefin DATETIME DEFAULT NULL, DROP datearriveeprevue, DROP datedepartreelle');
    }
}
