<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626230912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ticket : ajout de garedescentereelle (gare de descente réelle, passager descendu en route) pour libérer/revendre un siège en aval sans toucher au prix.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket ADD garedescentereelle_id INT DEFAULT NULL, CHANGE garedescente_id garedescente_id INT NOT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA328DC8408 FOREIGN KEY (garedescentereelle_id) REFERENCES gare (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA328DC8408 ON ticket (garedescentereelle_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA328DC8408');
        $this->addSql('DROP INDEX IDX_97A0ADA328DC8408 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP garedescentereelle_id, CHANGE garedescente_id garedescente_id INT DEFAULT NULL');
    }
}
