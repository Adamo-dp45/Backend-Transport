<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Courrier : la gare de départ ET la gare d'arrivée redeviennent OBLIGATOIRES (NOT NULL).
 * Un colis est défini par son origine et sa destination dès la création ; c'est le VOYAGE qui est
 * optionnel (affecté plus tard). Inverse Version20260617120000.
 *
 * Nettoyage préalable : les courriers incomplets (sans gare de départ ou d'arrivée), créés sous
 * l'ancienne logique « EN_ATTENTE sans itinéraire », n'ont plus de sens et sont supprimés
 * (avec leurs colis) — aucune valeur de destination sensée ne pouvant être devinée.
 */
final class Version20260704120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Courrier : garedepart_id et garearrivee_id redeviennent NOT NULL (destination obligatoire)';
    }

    public function up(Schema $schema): void
    {
        // 1. Purge des colis rattachés à des courriers incomplets
        $this->addSql('DELETE dc FROM detailcourrier dc
            INNER JOIN courrier c ON dc.courrier_id = c.id
            WHERE c.garedepart_id IS NULL OR c.garearrivee_id IS NULL');

        // 2. Purge des courriers incomplets eux-mêmes
        $this->addSql('DELETE FROM courrier WHERE garedepart_id IS NULL OR garearrivee_id IS NULL');

        // 3. Remise en NOT NULL
        $this->addSql('ALTER TABLE courrier CHANGE garedepart_id garedepart_id INT NOT NULL, CHANGE garearrivee_id garearrivee_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Les gares redeviennent nullable (les lignes supprimées ne sont pas restaurables).
        $this->addSql('ALTER TABLE courrier CHANGE garedepart_id garedepart_id INT DEFAULT NULL, CHANGE garearrivee_id garearrivee_id INT DEFAULT NULL');
    }
}
