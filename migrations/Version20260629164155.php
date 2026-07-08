<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629164155 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réservation : table reservation (place sur tronçon, sans siège, statut/expiration/paiement) + voyage.placesprevues (capacité prévisionnelle avant affectation du car).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, nomclient VARCHAR(255) DEFAULT NULL, contactclient VARCHAR(255) DEFAULT NULL, prix INT NOT NULL, statut VARCHAR(20) DEFAULT \'EN_ATTENTE\' NOT NULL, dateexpiration DATETIME NOT NULL, source VARCHAR(20) DEFAULT \'GUICHET\' NOT NULL, etatpaiement VARCHAR(30) DEFAULT \'EN_ATTENTE_PAIEMENT\' NOT NULL, referencepaiement VARCHAR(255) DEFAULT NULL, datepaiement DATETIME DEFAULT NULL, identreprise INT DEFAULT NULL, client_id INT DEFAULT NULL, voyage_id INT NOT NULL, gare_id INT NOT NULL, garedescente_id INT NOT NULL, ticket_id INT DEFAULT NULL, INDEX IDX_42C8495519EB6921 (client_id), INDEX IDX_42C8495568C9E5AF (voyage_id), INDEX IDX_42C8495563FD956 (gare_id), INDEX IDX_42C849554734D724 (garedescente_id), INDEX IDX_42C84955700047D2 (ticket_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495519EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495568C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495563FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849554734D724 FOREIGN KEY (garedescente_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955700047D2 FOREIGN KEY (ticket_id) REFERENCES ticket (id)');
        $this->addSql('ALTER TABLE voyage ADD placesprevues INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495519EB6921');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495568C9E5AF');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495563FD956');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849554734D724');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955700047D2');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('ALTER TABLE voyage DROP placesprevues');
    }
}
