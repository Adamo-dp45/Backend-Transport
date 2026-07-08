<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Passe les colonnes "couttotal" en BIGINT (detailapprovisionnement, depannage)
 * pour éviter le dépassement de la limite INT (~2,1 milliards) avec des montants
 * élevés en FCFA (prix conséquent × quantité).
 */
final class Version20260619120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'couttotal en BIGINT (detailapprovisionnement, depannage) pour les gros montants FCFA';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE detailapprovisionnement CHANGE couttotal couttotal BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE depannage CHANGE couttotal couttotal BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE detailapprovisionnement CHANGE couttotal couttotal INT DEFAULT NULL');
        $this->addSql('ALTER TABLE depannage CHANGE couttotal couttotal INT DEFAULT NULL');
    }
}
