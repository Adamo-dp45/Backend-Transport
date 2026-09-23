<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Module DÉPENSES : les charges d'exploitation saisies à la main (carburant, péage, pneus, salaires,
 * loyer, imprévus) — tout ce qui sort et ne passait par aucun module.
 *
 * L'application savait tout de ce qui RENTRE et ne connaissait que deux sorties : l'achat de pièces
 * et le dépannage. On ne pouvait donc pas répondre à « la compagnie gagne-t-elle réellement de
 * l'argent, et quelle gare ? ». Le total de ces dépenses devient le TROISIÈME poste soustrait au
 * bénéfice, à côté des deux autres qui gardent le leur.
 *
 * !! AUCUNE dépense n'est jamais DÉRIVÉE d'un dépannage ou d'un approvisionnement : ces deux-là
 * portent déjà leur coût et sont soustraits à part. Les générer ici les compterait deux fois, et le
 * double comptage serait alors technique, donc invisible. Verrouillé par 'tests/Api/DepenseTest.php'.
 *
 * DEUX PORTÉES, un seul discriminant : 'gare_id' renseigné = dépense de cette gare ; 'gare_id' NUL =
 * dépense du SIÈGE. Pas de colonne de portée à côté, qui pourrait diverger de la relation.
 *
 * 'montant' en BIGINT comme les autres montants du projet ('depannage.couttotal',
 * 'detailapprovisionnement.couttotal', 'courrier.total') : un INT plafonne à ~2,1 milliards de
 * francs, hors d'atteinte pour un péage mais pas pour un lot de salaires.
 *
 * 'voyage_id' est le CROCHET des futurs « frais de route » (forfait remis à l'équipage) : la colonne
 * est posée maintenant pour que le résultat d'un voyage se dérive un jour sans reprise de schéma.
 * Aucun écran ne l'exploite aujourd'hui.
 */
final class Version20260923090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module dépenses : typedepense et depense (charges d\'exploitation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE typedepense (
                id INT AUTO_INCREMENT NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                identreprise INT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL,
                created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL,
                etatdelete TINYINT DEFAULT 0,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        /*
            Les index métier sont aussi déclarés en attributs sur l'entité : la base de TEST est
            construite par 'doctrine:schema:update' (cf. 'make test-db'), qui ne lit que les
            mappings — un index posé ici seulement n'existerait pas en test.
        */
        $this->addSql(<<<'SQL'
            CREATE TABLE depense (
                id INT AUTO_INCREMENT NOT NULL,
                datedepense DATETIME NOT NULL,
                montant BIGINT NOT NULL,
                modereglement VARCHAR(20) DEFAULT 'ESPECES' NOT NULL,
                beneficiaire VARCHAR(255) DEFAULT NULL,
                libelle VARCHAR(255) DEFAULT NULL,
                identreprise INT DEFAULT NULL,
                typedepense_id INT NOT NULL,
                gare_id INT DEFAULT NULL,
                voyage_id INT DEFAULT NULL,
                fournisseur_id INT DEFAULT NULL,
                justificatif_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL,
                created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL,
                etatdelete TINYINT DEFAULT 0,
                INDEX IDX_34059757B882F56E (typedepense_id),
                INDEX IDX_3405975763FD956 (gare_id),
                INDEX IDX_34059757670C757F (fournisseur_id),
                INDEX IDX_340597574B85A991 (justificatif_id),
                INDEX idx_depense_ent_date (identreprise, datedepense),
                INDEX idx_depense_ent_gare_date (identreprise, gare_id, datedepense),
                INDEX idx_depense_voyage (voyage_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757B882F56E FOREIGN KEY (typedepense_id) REFERENCES typedepense (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975763FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_3405975768C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_340597574B85A991 FOREIGN KEY (justificatif_id) REFERENCES media_object (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757B882F56E');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975763FD956');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_3405975768C9E5AF');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757670C757F');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_340597574B85A991');
        $this->addSql('DROP TABLE depense');
        $this->addSql('DROP TABLE typedepense');
    }
}
