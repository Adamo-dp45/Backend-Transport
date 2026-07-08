<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260703153117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voyage.gareprovenance (origine effective = pivot départ partiel) + backfill = origine de la ligne';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage ADD gareprovenance_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955662BF1CE FOREIGN KEY (gareprovenance_id) REFERENCES gare (id)');
        $this->addSql('CREATE INDEX IDX_3F9D8955662BF1CE ON voyage (gareprovenance_id)');
        // Backfill : les voyages existants sont tous des départs NORMAUX → provenance = origine de leur ligne.
        $this->addSql('UPDATE voyage v INNER JOIN ligne l ON v.ligne_id = l.id SET v.gareprovenance_id = l.gareorigine_id WHERE v.gareprovenance_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955662BF1CE');
        $this->addSql('DROP INDEX IDX_3F9D8955662BF1CE ON voyage');
        $this->addSql('ALTER TABLE voyage DROP gareprovenance_id');
    }
}
