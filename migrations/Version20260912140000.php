<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bagage HORS LIGNE : référence d'idempotence, montant encaissé, et l'unicité du code de bagage —
 * qui manquait, exactement comme celle du code de billet (cf. Version20260912090000).
 *
 * UNICITÉ DU CODE : `codebagage` n'avait aucun index. Le générateur compte les bagages non supprimés
 * (`COUNT(*) + 1`), donc une mise en corbeille faisait déjà réutiliser un code imprimé sur une
 * étiquette remise au client. L'unicité est portée PAR ENTREPRISE parce que le code l'est aussi :
 * « BAG-2026-7 » existe légitimement chez chaque compagnie.
 *
 * AVANT DE DÉPLOYER, vérifier qu'aucune compagnie n'en porte de doublon :
 *   SELECT identreprise, codebagage, COUNT(*) FROM bagage
 *   GROUP BY identreprise, codebagage HAVING COUNT(*) > 1;
 * (base de développement au moment de l'écriture : 66 bagages, 66 codes distincts, 1 compagnie)
 */
final class Version20260912140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage hors ligne : reference_offline, montant_encaisse, et unicité de codebagage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD reference_offline VARCHAR(64) DEFAULT NULL, ADD montant_encaisse INT DEFAULT NULL');

        // Nullable + unique : MySQL autorise autant de NULL qu'on veut sous un index unique, donc les
        // enregistrements au GUICHET (sans référence) ne se gênent pas entre eux.
        $this->addSql('CREATE UNIQUE INDEX uniq_bagage_reference_offline ON bagage (reference_offline)');
        $this->addSql('CREATE UNIQUE INDEX uniq_bagage_codebagage ON bagage (identreprise, codebagage)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_bagage_codebagage ON bagage');
        $this->addSql('DROP INDEX uniq_bagage_reference_offline ON bagage');
        $this->addSql('ALTER TABLE bagage DROP reference_offline, DROP montant_encaisse');
    }
}
