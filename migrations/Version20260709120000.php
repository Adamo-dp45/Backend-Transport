<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Courrier : suppression du paiement (modepaiement / etatpaiement / datepaiement). Les propriétés ont été
 * retirées de l'entité (paiement toujours à l'envoi dans ce déploiement) ; ces colonnes n'étant plus
 * mappées mais restées NOT NULL en base, tout INSERT de courrier échouait (« Un champ obligatoire est
 * manquant »). On les supprime.
 */
final class Version20260709120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courrier : suppression des colonnes paiement (modepaiement, etatpaiement, datepaiement)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE courrier DROP modepaiement, DROP etatpaiement, DROP datepaiement');
    }

    public function down(Schema $schema): void
    {
        // Rétablissement best-effort (valeurs par défaut pour ne pas casser les lignes existantes).
        $this->addSql("ALTER TABLE courrier ADD modepaiement VARCHAR(50) DEFAULT 'ENVOI' NOT NULL");
        $this->addSql("ALTER TABLE courrier ADD etatpaiement VARCHAR(50) DEFAULT 'PAYE' NOT NULL");
        $this->addSql("ALTER TABLE courrier ADD datepaiement DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }
}
