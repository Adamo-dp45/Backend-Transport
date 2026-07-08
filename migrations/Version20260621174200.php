<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621174200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute role.gare_id (rôle rattaché à une gare ; null = rôle entreprise)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role ADD gare_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6A63FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('CREATE INDEX IDX_57698A6A63FD956 ON role (gare_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6A63FD956');
        $this->addSql('DROP INDEX IDX_57698A6A63FD956 ON role');
        $this->addSql('ALTER TABLE role DROP gare_id');
    }
}
