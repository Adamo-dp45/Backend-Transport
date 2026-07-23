<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Passage RÉEL d'un voyage à une gare : horodate l'arrivée et le départ effectifs du car (nouvelle
 * entité App\Entity\Passage). Un enregistrement par (voyage, gare) — unique. Alimente le suivi
 * d'exploitation, les bordereaux et les statistiques de ponctualité.
 */
final class Version20260722140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exploitation : table passage (arrivée / départ réels du car par gare)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE passage (id INT AUTO_INCREMENT NOT NULL, arrivee_reelle DATETIME DEFAULT NULL, depart_reelle DATETIME DEFAULT NULL, identreprise INT DEFAULT NULL, created_at DATETIME NOT NULL, voyage_id INT NOT NULL, gare_id INT NOT NULL, INDEX IDX_2B258F6768C9E5AF (voyage_id), INDEX IDX_2B258F6763FD956 (gare_id), UNIQUE INDEX UNIQ_passage_voyage_gare (voyage_id, gare_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE passage ADD CONSTRAINT FK_2B258F6768C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE passage ADD CONSTRAINT FK_2B258F6763FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE passage');
    }
}
