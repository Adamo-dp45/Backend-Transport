<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Réservation : scinde l'unique 'delai_expiration_minutes' en DEUX délais aux sens distincts.
 *
 *  - 'delai_presentation_minutes' : minutes AVANT LE DÉPART, dernière limite pour se présenter au
 *    guichet (et pour réserver). C'est le sens qu'avait déjà l'ancienne colonne → on la RENOMME,
 *    ce qui préserve la valeur réglée par chaque entreprise.
 *  - 'delai_paiement_minutes' : minutes À PARTIR DE LA CRÉATION pour payer une réservation en
 *    attente. Nouvelle colonne (30 min par défaut) : l'ancien modèle ancrait ce délai sur le
 *    départ, ce qui laissait parfois des semaines pour payer.
 */
final class Version20260719120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réservation : délai unique scindé en délai de présentation (renommé) + délai de paiement (nouveau)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_reservation RENAME COLUMN delai_expiration_minutes TO delai_presentation_minutes');
        $this->addSql('ALTER TABLE parametre_reservation ALTER COLUMN delai_presentation_minutes SET DEFAULT 15');
        $this->addSql('ALTER TABLE parametre_reservation ADD delai_paiement_minutes INT DEFAULT 30 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_reservation DROP COLUMN delai_paiement_minutes');
        $this->addSql('ALTER TABLE parametre_reservation ALTER COLUMN delai_presentation_minutes SET DEFAULT 120');
        $this->addSql('ALTER TABLE parametre_reservation RENAME COLUMN delai_presentation_minutes TO delai_expiration_minutes');
    }
}
