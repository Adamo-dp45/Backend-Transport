<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grille tarifaire GLOBALE : la table `tarif` (prix unique par couple de gares, par entreprise)
 * remplace `tarif_ligne` (prix par ligne). On recopie les couples DISTINCTS (dédoublonnage) puis
 * on supprime `tarif_ligne`.
 */
final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grille tarifaire globale (tarif gare→gare) ; suppression de tarif_ligne';
    }

    public function up(Schema $schema): void
    {
        // 1) Table tarif (globale)
        $this->addSql('CREATE TABLE tarif (
            id INT AUTO_INCREMENT NOT NULL,
            garedepart_id INT NOT NULL,
            garearrivee_id INT NOT NULL,
            montant INT NOT NULL,
            identreprise INT DEFAULT NULL,
            created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_from_ip VARCHAR(255) DEFAULT NULL,
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_from_ip VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            deleted_from_ip VARCHAR(255) DEFAULT NULL,
            created_by INT DEFAULT NULL,
            updated_by INT DEFAULT NULL,
            deleted_by INT DEFAULT NULL,
            etatdelete TINYINT(1) DEFAULT 0,
            INDEX IDX_tarif_garedepart (garedepart_id),
            INDEX IDX_tarif_garearrivee (garearrivee_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT FK_tarif_garedepart FOREIGN KEY (garedepart_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT FK_tarif_garearrivee FOREIGN KEY (garearrivee_id) REFERENCES gare (id)');

        // 2) Recopie des couples DISTINCTS depuis tarif_ligne (si la table existe)
        $db = $this->connection->getDatabase();
        $tarifLigneExiste = ((int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$db, 'tarif_ligne']
        )) > 0;

        if ($tarifLigneExiste) {
            // Un couple (depart, arrivee) pouvait avoir plusieurs prix selon la ligne -> on garde le MAX
            $this->addSql('INSERT INTO tarif (garedepart_id, garearrivee_id, montant, identreprise, created_at, updated_at, etatdelete)
                SELECT garedepart_id, garearrivee_id, MAX(montant), identreprise, NOW(), NOW(), 0
                FROM tarif_ligne
                GROUP BY garedepart_id, garearrivee_id, identreprise');

            $this->addSql('DROP TABLE tarif_ligne');
        }
    }

    public function down(Schema $schema): void
    {
        // Recréation best-effort de tarif_ligne (les données par ligne sont perdues)
        $this->addSql('CREATE TABLE IF NOT EXISTS tarif_ligne (
            id INT AUTO_INCREMENT NOT NULL, ligne_id INT NOT NULL,
            garedepart_id INT NOT NULL, garearrivee_id INT NOT NULL,
            montant INT NOT NULL, identreprise INT DEFAULT NULL,
            INDEX IDX_tl_ligne (ligne_id), INDEX IDX_tl_garedepart (garedepart_id), INDEX IDX_tl_garearrivee (garearrivee_id),
            UNIQUE INDEX UNIQ_tarifligne (ligne_id, garedepart_id, garearrivee_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');

        $this->addSql('ALTER TABLE tarif DROP FOREIGN KEY FK_tarif_garedepart');
        $this->addSql('ALTER TABLE tarif DROP FOREIGN KEY FK_tarif_garearrivee');
        $this->addSql('DROP TABLE tarif');
    }
}
