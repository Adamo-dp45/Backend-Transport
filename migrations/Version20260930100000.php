<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le COURRIER repris avant le départ est remboursé lui aussi (30/09/2026).
 *
 * Aligné sur le billet désisté et le bagage annulé : les trois gestes rendent l'argent au client,
 * et n'en chiffrer qu'une partie ferait dépendre le remboursement du guichet où l'on se trouve —
 * en fabriquant à l'agent un manquant du montant de ce qu'on n'a pas compté.
 *
 * 'montantrembourse' porte ici la TAXE ET LES FRAIS DE SUIVI ensemble : deux encaissements
 * distincts à l'entrée, un seul geste à la sortie.
 */
final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute montantrembourse et sessioncaisseremboursement sur courrier';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier ADD montantrembourse BIGINT DEFAULT NULL, ADD sessioncaisseremboursement_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAAB0291FC FOREIGN KEY (sessioncaisseremboursement_id) REFERENCES sessioncaisse (id)');
        $this->addSql('CREATE INDEX IDX_BEF47CAAAB0291FC ON courrier (sessioncaisseremboursement_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAAAB0291FC');
        $this->addSql('DROP INDEX IDX_BEF47CAAAB0291FC ON courrier');
        $this->addSql('ALTER TABLE courrier DROP montantrembourse, DROP sessioncaisseremboursement_id');
    }
}
