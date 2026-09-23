<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NUMÉRO DE DÉPART DU JOUR sur le voyage — « DÉPART 4 », la case que le passager lit sur son billet,
 * face à son siège. Compteur par entreprise + ligne + gare de provenance effective + JOUR : le Nième
 * car que CETTE gare lance sur CETTE ligne ce jour-là (cf. 'NumeroDepartService').
 *
 * DEUX colonnes, pas une. 'jourdepart' est le jour de 'datedepartprevue', donc un DÉRIVÉ STOCKÉ —
 * contre la règle maison, et pour une seule raison : porter l'unicité du numéro par un INDEX plutôt
 * que par une vérification PHP. Une expression ('CAST(datedepartprevue AS DATE)') ne se déclare pas
 * dans le mapping Doctrine, donc n'existerait pas dans la base de TEST, construite par
 * 'doctrine:schema:update' (cf. 'make test-db') et non par les migrations : les tests auraient
 * validé une règle que la production seule aurait tenue. Sans index, deux créations simultanées sur
 * la même ligne produiraient deux « Départ 2 » EN SILENCE — le défaut corrigé sur 'codeticket'.
 *
 * NUMÉROTATION DE L'EXISTANT dans l'ordre des HEURES DE DÉPART, et non de la création : en
 * production le numéro est figé à l'ouverture du départ, mais aucun billet n'a encore été imprimé
 * avec — il n'y a donc rien à préserver, et l'ordre des heures est celui qui se lit sur un tableau
 * de départs. Les voyages SUPPRIMÉS sont numérotés eux aussi : leur ligne reste en base, donc sous
 * l'index, et un numéro rendu au suivant ferait imprimer deux fois « Départ 2 » pour une journée.
 *
 * AVANT DE DÉPLOYER, vérifier qu'aucune journée ne porte déjà deux départs impossibles à départager :
 *   SELECT identreprise, ligne_id, gareprovenance_id, DATE(datedepartprevue) j, datedepartprevue, COUNT(*)
 *   FROM voyage GROUP BY 1,2,3,4,5 HAVING COUNT(*) > 1;
 * (base de développement au moment de l'écriture : 36 voyages, tous avec une provenance, aucune
 * journée à plusieurs départs — tous passent donc à « Départ 1 »)
 */
final class Version20260922110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Numéro de départ du jour sur le voyage, son jour dérivé et leur unicité';
    }

    public function up(Schema $schema): void
    {
        // 1. Les colonnes, d'abord NULLABLES : les voyages déjà en base n'ont pas encore de numéro.
        $this->addSql('ALTER TABLE voyage ADD numerodepart INT DEFAULT NULL, ADD jourdepart DATE DEFAULT NULL');

        // 2. Le jour suit la date de départ prévue. En service courant c'est le setter de l'entité
        //    qui le tient — ici, une fois, pour l'existant.
        $this->addSql('UPDATE voyage SET jourdepart = DATE(datedepartprevue)');

        /*
            3. Numérotation, groupe par groupe. LEFT JOIN et COALESCE pour qu'AUCUN voyage ne reste
               sans numéro (un voyage sans ligne, ou sans provenance — legacy d'avant le départ
               partiel — retombe sur ce qu'il porte), sans quoi le passage en NOT NULL échouerait.
        */
        $this->addSql(<<<'SQL'
            UPDATE voyage v
            JOIN (
                SELECT vv.id,
                       ROW_NUMBER() OVER (
                           PARTITION BY vv.identreprise,
                                        vv.ligne_id,
                                        COALESCE(vv.gareprovenance_id, l.gareorigine_id),
                                        vv.jourdepart
                           ORDER BY vv.datedepartprevue, vv.id
                       ) AS rang
                FROM voyage vv
                LEFT JOIN ligne l ON l.id = vv.ligne_id
            ) r ON r.id = v.id
            SET v.numerodepart = r.rang
        SQL);

        // 4. Tout est numéroté : on ferme. NOT NULL d'abord (un numéro manquant n'a aucun sens),
        //    l'index ensuite — il refuse désormais le doublon au lieu de le laisser passer.
        $this->addSql('ALTER TABLE voyage CHANGE numerodepart numerodepart INT NOT NULL, CHANGE jourdepart jourdepart DATE NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_voyage_numerodepart ON voyage (identreprise, ligne_id, gareprovenance_id, jourdepart, numerodepart)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_voyage_numerodepart ON voyage');
        $this->addSql('ALTER TABLE voyage DROP numerodepart, DROP jourdepart');
    }
}
