<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la gare d'origine (garedepart) au bagage : tracée et forcée à la gare de l'agent
 * lorsqu'il y est rattaché (cf. BagageProcessor::resoudreGares).
 */
final class Version20260617130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage : ajout de garedepart_id (gare d\'origine)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD garedepart_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_BAGAGE_GAREDEPART FOREIGN KEY (garedepart_id) REFERENCES gare (id)');
        $this->addSql('CREATE INDEX IDX_BAGAGE_GAREDEPART ON bagage (garedepart_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_BAGAGE_GAREDEPART');
        $this->addSql('DROP INDEX IDX_BAGAGE_GAREDEPART ON bagage');
        $this->addSql('ALTER TABLE bagage DROP garedepart_id');
    }
}
