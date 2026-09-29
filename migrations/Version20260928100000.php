<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `approvisionnement.couttotal` — le coût sur l'entête, comme pour un dépannage.
 *
 * POURQUOI : l'incohérence avec `Depannage::$couttotal`, qui portait déjà son coût sur l'entête, et
 * trois gains concrets — des agrégats sur UNE table au lieu de deux, un coût lisible par le client sans
 * resommer les lignes, et un `COUNT(DISTINCT a.id)` qui cesse de sous-compter un approvisionnement sans
 * ligne (l'INNER JOIN l'écartait ; sur le MONTANT il ne faussait rien, sa contribution étant nulle —
 * vérifié).
 *
 * LE BACKFILL EST LA MOITIÉ DE LA MIGRATION. Sans lui, toutes les lignes existantes valent NULL et le
 * coût des approvisionnements tomberait à ZÉRO sur toute la période antérieure — un bénéfice
 * brusquement embelli, sur des données qui n'ont pas changé. Mesuré avant migration, à retrouver
 * après : 4 966 000 pour l'entreprise 9, 126 000 pour l'entreprise 10.
 *
 * Il porte sur TOUTES les lignes, annulées et en corbeille comprises : le champ dit ce que
 * l'approvisionnement a coûté, ce sont les REQUÊTES qui décident de le compter ou non (statut et
 * corbeille). Mettre zéro sur un annulé le rendrait impossible à auditer.
 */
final class Version20260928100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute approvisionnement.couttotal (bigint) et le renseigne depuis ses lignes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE approvisionnement ADD couttotal BIGINT DEFAULT NULL');

        // Backfill : la somme des lignes de chaque approvisionnement (0 s'il n'en a aucune).
        $this->addSql(<<<'SQL'
            UPDATE approvisionnement a
            SET a.couttotal = COALESCE((
                SELECT SUM(da.couttotal)
                FROM detailapprovisionnement da
                WHERE da.approvisionnement_id = a.id
            ), 0)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE approvisionnement DROP couttotal');
    }
}
