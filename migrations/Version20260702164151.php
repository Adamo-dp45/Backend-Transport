<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702164151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réservation : politique de pénalité/fenêtre de régularisation (parametre_reservation) + montants pénalité/complément (reservation)';
    }

    public function up(Schema $schema): void
    {
        // Politique de régularisation par entreprise (pénalité no-show + fenêtre de report)
        $this->addSql('ALTER TABLE parametre_reservation ADD penalite_type VARCHAR(20) DEFAULT \'AUCUNE\' NOT NULL, ADD penalite_valeur INT DEFAULT 0 NOT NULL, ADD fenetre_regularisation_jours INT DEFAULT 7 NOT NULL');
        // Montants encaissés à la régularisation d'une réservation
        $this->addSql('ALTER TABLE reservation ADD penalitemontant INT DEFAULT 0 NOT NULL, ADD montantcomplement INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_reservation DROP penalite_type, DROP penalite_valeur, DROP fenetre_regularisation_jours');
        $this->addSql('ALTER TABLE reservation DROP penalitemontant, DROP montantcomplement');
    }
}
