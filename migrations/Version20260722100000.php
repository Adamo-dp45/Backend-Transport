<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Arrêt : la durée passe du CUMUL depuis l'origine à une durée PAR TRONÇON (minutes depuis l'arrêt
 * précédent). Renommage de la colonne AVEC conversion des données — un simple rename réinterpréterait
 * les cumuls (0, 50, 60) comme des tronçons, ce qui serait faux.
 *
 * Conversion : troncon(arrêt) = cumul(arrêt) − cumul(arrêt précédent) ; NULL à l'origine (aucun tronçon
 * avant elle). L'heure de passage prévue (SOMME des tronçons, cf. ReservationEcheanceService::heurePassage)
 * reste donc identique. Requiert MySQL 8 (fonctions de fenêtrage LAG/SUM OVER) — vérifié : 8.0.46.
 */
final class Version20260722100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Arrêt : durée par TRONÇON (remplace le cumul depuis l\'origine), avec conversion des données';
    }

    public function up(Schema $schema): void
    {
        // 1. Renommer la colonne : elle contient ENCORE les cumuls.
        $this->addSql('ALTER TABLE arret CHANGE duree_depuis_origine_minutes duree_troncon_minutes INT DEFAULT NULL');

        // 2. Convertir en place : troncon = cumul − cumul(précédent). La sous-requête fige les cumuls
        //    (évaluée avant l'UPDATE), donc pas de lecture de valeurs déjà modifiées.
        $this->addSql(<<<'SQL'
            UPDATE arret a
            JOIN (
                SELECT id,
                       duree_troncon_minutes - LAG(duree_troncon_minutes) OVER (PARTITION BY ligne_id ORDER BY ordre) AS troncon
                FROM arret
            ) c ON c.id = a.id
            SET a.duree_troncon_minutes = c.troncon
            WHERE a.ordre > 0
            SQL);

        // 3. L'origine (ordre 0) n'a pas de tronçon.
        $this->addSql('UPDATE arret SET duree_troncon_minutes = NULL WHERE ordre = 0');
    }

    public function down(Schema $schema): void
    {
        // Reconstituer le cumul (somme des tronçons) pour les lignes renseignées ; NULL pour les autres.
        $this->addSql(<<<'SQL'
            UPDATE arret a
            JOIN (
                SELECT id,
                       SUM(COALESCE(duree_troncon_minutes, 0)) OVER (PARTITION BY ligne_id ORDER BY ordre) AS cumul,
                       MAX(CASE WHEN duree_troncon_minutes IS NOT NULL THEN 1 ELSE 0 END) OVER (PARTITION BY ligne_id) AS renseignee
                FROM arret
            ) c ON c.id = a.id
            SET a.duree_troncon_minutes = CASE WHEN c.renseignee = 1 THEN c.cumul ELSE NULL END
            SQL);

        $this->addSql('ALTER TABLE arret CHANGE duree_troncon_minutes duree_depuis_origine_minutes INT DEFAULT NULL');
    }
}
