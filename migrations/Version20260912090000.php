<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Vente HORS LIGNE du vendeur à bord : référence d'idempotence, montant encaissé, et l'unicité du
 * code de billet — qui manquait.
 *
 * UNICITÉ DU CODE : `codeticket` n'avait AUCUN index unique. Le générateur compte les billets non
 * supprimés (`COUNT(*) + 1`), si bien qu'une mise en corbeille faisait déjà réutiliser un code émis —
 * en silence, sans la moindre erreur. Deux appareils vendant hors ligne auraient aggravé le défaut au
 * lieu de le révéler. L'index transforme une duplication silencieuse en refus franc.
 *
 * AVANT DE DÉPLOYER, vérifier que la base n'en porte aucun :
 *   SELECT codeticket, COUNT(*) FROM ticket GROUP BY codeticket HAVING COUNT(*) > 1;
 * (base de développement au moment de l'écriture : 261 billets, 261 codes distincts)
 */
final class Version20260912090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Vente hors ligne : reference_offline, montant_encaisse, et unicité de codeticket';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ticket ADD reference_offline VARCHAR(64) DEFAULT NULL, ADD montant_encaisse INT DEFAULT NULL');

        // Nullable + unique : MySQL autorise autant de NULL qu'on veut, donc les ventes EN LIGNE
        // (sans référence) ne se gênent pas entre elles.
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_reference_offline ON ticket (reference_offline)');
        $this->addSql('CREATE UNIQUE INDEX uniq_ticket_codeticket ON ticket (codeticket)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_ticket_codeticket ON ticket');
        $this->addSql('DROP INDEX uniq_ticket_reference_offline ON ticket');
        $this->addSql('ALTER TABLE ticket DROP reference_offline, DROP montant_encaisse');
    }
}
