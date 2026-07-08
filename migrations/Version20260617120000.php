<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend les gares de départ/arrivée d'un courrier nullable : elles ne sont définies que
 * lorsqu'un voyage est affecté au courrier (cf. CourrierProcessor::resoudreGares).
 */
final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courrier : garedepart_id et garearrivee_id deviennent nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier CHANGE garedepart_id garedepart_id INT DEFAULT NULL, CHANGE garearrivee_id garearrivee_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Attention : échouera si des courriers ont des gares nulles
        $this->addSql('ALTER TABLE courrier CHANGE garedepart_id garedepart_id INT NOT NULL, CHANGE garearrivee_id garearrivee_id INT NOT NULL');
    }
}
