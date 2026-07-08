<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627154921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activite : remplace auteurnom (snapshot) par auteur_id (FK user) pour refléter le nom courant.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite ADD auteur_id INT DEFAULT NULL, DROP auteurnom');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_B875551560BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B875551560BB6FE6 ON activite (auteur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_B875551560BB6FE6');
        $this->addSql('DROP INDEX IDX_B875551560BB6FE6 ON activite');
        $this->addSql('ALTER TABLE activite ADD auteurnom VARCHAR(255) DEFAULT NULL, DROP auteur_id');
    }
}
