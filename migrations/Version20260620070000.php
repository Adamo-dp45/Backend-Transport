<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime la colonne stockée 'voyage.placesoccupees'. Avec la billetterie PAR TRONÇON ce compteur
 * comptait des billets (pouvait dépasser 'placestotal') et dérivait à l'annulation. Le nombre de
 * billets vendus est désormais compté à la volée (Voyage::getTicketsCount() / sous-requête COUNT
 * des tickets actifs) ; la capacité réelle par tronçon reste gérée par SiegeStateProvider.
 */
final class Version20260620070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la colonne voyage.placesoccupees (compteur remplacé par un comptage à la volée des tickets actifs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage DROP placesoccupees');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage ADD placesoccupees INT DEFAULT NULL');
    }
}
