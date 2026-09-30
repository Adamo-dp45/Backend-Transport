<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La CAISSE d'un agent : table 'sessioncaisse', et rattachement des ventes de guichet.
 *
 * La colonne 'agentsessionouverte' porte l'index UNIQUE avec 'identreprise' : elle vaut
 * l'identifiant de l'agent tant que sa caisse est ouverte, et retombe à NULL à la clôture. MySQL
 * ignorant les NULL dans un index unique, un agent ne peut avoir qu'UNE caisse ouverte tout en
 * gardant autant de caisses clôturées qu'il a travaillé de journées. C'est la BASE qui tient la
 * règle, et non une relecture applicative que deux ventes simultanées prendraient en défaut.
 *
 * AUCUN BACKFILL, et c'est délibéré : fabriquer un rattachement rétroactif sur des ventes passées
 * serait une falsification sur une pièce de preuve. Le module démarre le jour du déploiement, les
 * ventes antérieures restent à 'sessioncaisse_id' NULL — c'est-à-dire hors caisse, ce qu'elles sont.
 */
final class Version20260929170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute sessioncaisse (la caisse d\'un agent) et sessioncaisse_id sur ticket, bagage et courrier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessioncaisse (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, datedebut DATETIME NOT NULL, datefin DATETIME DEFAULT NULL, fondsouverture BIGINT DEFAULT 0 NOT NULL, ouvertureautomatique TINYINT DEFAULT 0 NOT NULL, totalbillets BIGINT DEFAULT NULL, totalbagages BIGINT DEFAULT NULL, totalcourriers BIGINT DEFAULT NULL, totalfraissuivi BIGINT DEFAULT NULL, totalreservations BIGINT DEFAULT NULL, totalpenalites BIGINT DEFAULT NULL, totalcomplements BIGINT DEFAULT NULL, totalremboursements BIGINT DEFAULT NULL, montanttheorique BIGINT DEFAULT NULL, montantcompte BIGINT DEFAULT NULL, ecart BIGINT DEFAULT NULL, motifecart VARCHAR(255) DEFAULT NULL, statut VARCHAR(20) DEFAULT \'OUVERTE\' NOT NULL, agentsessionouverte INT DEFAULT NULL, identreprise INT DEFAULT NULL, agent_id INT NOT NULL, gare_id INT NOT NULL, INDEX IDX_E3E607823414710B (agent_id), INDEX IDX_E3E6078263FD956 (gare_id), INDEX idx_sessioncaisse_ent_gare_debut (identreprise, gare_id, datedebut), INDEX idx_sessioncaisse_agent_statut (agent_id, statut), UNIQUE INDEX uniq_sessioncaisse_agent_ouverte (identreprise, agentsessionouverte), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE sessioncaisse ADD CONSTRAINT FK_E3E607823414710B FOREIGN KEY (agent_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE sessioncaisse ADD CONSTRAINT FK_E3E6078263FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE bagage ADD sessioncaisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C571582D8DBB7 FOREIGN KEY (sessioncaisse_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_A82C571582D8DBB7 ON bagage (sessioncaisse_id)');
        $this->addSql('ALTER TABLE courrier ADD sessioncaisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAA82D8DBB7 FOREIGN KEY (sessioncaisse_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAA82D8DBB7 ON courrier (sessioncaisse_id)');
        $this->addSql('ALTER TABLE ticket ADD sessioncaisse_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA382D8DBB7 FOREIGN KEY (sessioncaisse_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA382D8DBB7 ON ticket (sessioncaisse_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sessioncaisse DROP FOREIGN KEY FK_E3E607823414710B');
        $this->addSql('ALTER TABLE sessioncaisse DROP FOREIGN KEY FK_E3E6078263FD956');
        $this->addSql('DROP TABLE sessioncaisse');
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C571582D8DBB7');
        $this->addSql('DROP INDEX IDX_A82C571582D8DBB7 ON bagage');
        $this->addSql('ALTER TABLE bagage DROP sessioncaisse_id');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAA82D8DBB7');
        $this->addSql('DROP INDEX IDX_BEF47CAA82D8DBB7 ON courrier');
        $this->addSql('ALTER TABLE courrier DROP sessioncaisse_id');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA382D8DBB7');
        $this->addSql('DROP INDEX IDX_97A0ADA382D8DBB7 ON ticket');
        $this->addSql('ALTER TABLE ticket DROP sessioncaisse_id');
    }
}
