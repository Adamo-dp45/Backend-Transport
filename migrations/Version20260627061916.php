<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260627061916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voyage : ajout du commercial (vendeur à bord, FK user) et de garecourante (position courante du car d\'où le commercial vend).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage ADD commercial_id INT DEFAULT NULL, ADD garecourante_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D89557854071C FOREIGN KEY (commercial_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D89554E693E42 FOREIGN KEY (garecourante_id) REFERENCES gare (id)');
        $this->addSql('CREATE INDEX IDX_3F9D89557854071C ON voyage (commercial_id)');
        $this->addSql('CREATE INDEX IDX_3F9D89554E693E42 ON voyage (garecourante_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D89557854071C');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D89554E693E42');
        $this->addSql('DROP INDEX IDX_3F9D89557854071C ON voyage');
        $this->addSql('DROP INDEX IDX_3F9D89554E693E42 ON voyage');
        $this->addSql('ALTER TABLE voyage DROP commercial_id, DROP garecourante_id');
    }
}
