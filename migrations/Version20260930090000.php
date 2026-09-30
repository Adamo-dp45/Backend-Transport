<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Les SORTIES de caisse et les ENCAISSEMENTS DE RÉSERVATION (palier 3 du chantier Caisse).
 *
 * DEUX colonnes de caisse par entité, et non une, parce que ce sont DEUX ÉVÉNEMENTS distincts sur
 * la même ligne : la vente lundi ('sessioncaisse'), le remboursement jeudi
 * ('sessioncaisseremboursement'). Les confondre ferait retomber la sortie de jeudi dans une caisse
 * déjà clôturée et signée — et l'agent de jeudi aurait un manquant que rien n'expliquerait. Même
 * raison pour 'reservation' : l'encaissement du bon au guichet et la régularisation d'un no-show
 * sont séparés de plusieurs semaines, et souvent le fait de deux agents différents.
 *
 * 'montantrembourse' est CHIFFRÉ alors que la règle existait déjà, implicite, dans le code
 * (« remboursement intégral = prix ») : sans colonne, la recette baissait rétroactivement au jour
 * de la VENTE pendant que le tiroir se vidait le jour du REMBOURSEMENT.
 *
 * AUCUN BACKFILL, comme au palier 1 : reconstituer après coup qui a remboursé quoi serait une
 * falsification sur une pièce de preuve.
 */
final class Version20260930090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute montantrembourse et les colonnes de caisse sur ticket, bagage et reservation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD montantrembourse BIGINT DEFAULT NULL, ADD sessioncaisseremboursement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C5715AB0291FC FOREIGN KEY (sessioncaisseremboursement_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_A82C5715AB0291FC ON bagage (sessioncaisseremboursement_id)');
        $this->addSql('ALTER TABLE reservation ADD sessioncaisse_id INT DEFAULT NULL, ADD sessioncaisseregul_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495582D8DBB7 FOREIGN KEY (sessioncaisse_id) REFERENCES sessioncaisse (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955E53AD7BB FOREIGN KEY (sessioncaisseregul_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_42C8495582D8DBB7 ON reservation (sessioncaisse_id)');
        $this->addSql('CREATE INDEX IDX_42C84955E53AD7BB ON reservation (sessioncaisseregul_id)');
        $this->addSql('ALTER TABLE ticket ADD montantrembourse BIGINT DEFAULT NULL, ADD sessioncaisseremboursement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3AB0291FC FOREIGN KEY (sessioncaisseremboursement_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_97A0ADA3AB0291FC ON ticket (sessioncaisseremboursement_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C5715AB0291FC');
        $this->addSql('DROP INDEX IDX_A82C5715AB0291FC ON bagage');
        $this->addSql('ALTER TABLE bagage DROP montantrembourse, DROP sessioncaisseremboursement_id');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495582D8DBB7');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955E53AD7BB');
        $this->addSql('DROP INDEX IDX_42C8495582D8DBB7 ON reservation');
        $this->addSql('DROP INDEX IDX_42C84955E53AD7BB ON reservation');
        $this->addSql('ALTER TABLE reservation DROP sessioncaisse_id, DROP sessioncaisseregul_id');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3AB0291FC');
        $this->addSql('DROP INDEX IDX_97A0ADA3AB0291FC ON ticket');
        $this->addSql('ALTER TABLE ticket DROP montantrembourse, DROP sessioncaisseremboursement_id');
    }
}
