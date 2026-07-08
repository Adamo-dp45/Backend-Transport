<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627160818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage : rattachement optionnel au billet du client (ticket_id) — provenance/destination héritées du billet, annulation en cascade.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD ticket_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C5715700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('CREATE INDEX IDX_A82C5715700047D2 ON bagage (ticket_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C5715700047D2');
        $this->addSql('DROP INDEX IDX_A82C5715700047D2 ON bagage');
        $this->addSql('ALTER TABLE bagage DROP ticket_id');
    }
}
