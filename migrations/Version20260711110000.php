<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mode maintenance global (super admin) : table maintenance (singleton) avec le drapeau actif, un
 * message optionnel et l'horodatage d'activation.
 */
final class Version20260711110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table maintenance (mode maintenance global piloté par le super admin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, actif TINYINT DEFAULT 0 NOT NULL, message LONGTEXT DEFAULT NULL, depuis DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE maintenance');
    }
}
