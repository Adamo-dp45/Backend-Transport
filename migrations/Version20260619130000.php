<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Passe en BIGINT les montants liés au courrier (valeur/taxe d'un colis et total
 * du courrier) pour éviter le dépassement de la limite INT (~2,1 milliards) sur
 * des colis de très grande valeur déclarée.
 */
final class Version20260619130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'detailcourrier.valeur/montant et courrier.montant en BIGINT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE detailcourrier CHANGE valeur valeur BIGINT NOT NULL');
        $this->addSql('ALTER TABLE detailcourrier CHANGE montant montant BIGINT NOT NULL');
        $this->addSql('ALTER TABLE courrier CHANGE montant montant BIGINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE detailcourrier CHANGE valeur valeur INT NOT NULL');
        $this->addSql('ALTER TABLE detailcourrier CHANGE montant montant INT NOT NULL');
        $this->addSql('ALTER TABLE courrier CHANGE montant montant INT NOT NULL');
    }
}
