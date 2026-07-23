<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Arrêt : durée de trajet depuis l'origine de la ligne (minutes).
 *
 * Elle donne l'heure de passage prévue du car à chaque arrêt ('datedepartprevue + durée'). Sans
 * elle, l'échéance de présentation de TOUS les passagers se calculait sur le départ de l'origine :
 * celui qui monte à un arrêt intermédiaire voyait son bon expirer alors que le car roulait encore
 * vers lui. Nullable : les lignes existantes gardent l'ancien comportement tant qu'elle n'est pas
 * renseignée (cf. ReservationEcheanceService::heurePassage).
 */
final class Version20260720140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Arrêt : durée de trajet depuis l\'origine (heure de passage par arrêt)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE arret ADD duree_depuis_origine_minutes INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE arret DROP COLUMN duree_depuis_origine_minutes');
    }
}
