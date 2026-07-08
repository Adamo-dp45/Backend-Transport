<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627222937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventaire : relation auteur_id (User) pour remplacer le lookup sur createdBy.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventaire ADD auteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inventaire ADD CONSTRAINT FK_338920E060BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_338920E060BB6FE6 ON inventaire (auteur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inventaire DROP FOREIGN KEY FK_338920E060BB6FE6');
        $this->addSql('DROP INDEX IDX_338920E060BB6FE6 ON inventaire');
        $this->addSql('ALTER TABLE inventaire DROP auteur_id');
    }
}
