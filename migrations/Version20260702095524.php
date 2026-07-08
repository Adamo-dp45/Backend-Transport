<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Identifiant public des entreprises : ajoute entreprise.slug (unique), et backfille les entreprises
 * existantes en slugifiant leur libellé, avec suffixe anti-collision (-2, -3…).
 */
final class Version20260702095524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout entreprise.slug (unique) + backfill slugifié anti-collision';
    }

    public function up(Schema $schema): void
    {
        // Exécution IMMÉDIATE (pas addSql, qui est différé après up()) pour pouvoir backfiller ensuite
        $this->connection->executeStatement('ALTER TABLE entreprise ADD slug VARCHAR(255) DEFAULT NULL');

        // Backfill : slug unique par entreprise (suffixe -2, -3… en cas de doublon)
        $slugger = new AsciiSlugger();
        $rows = $this->connection->fetchAllAssociative('SELECT id, libelle FROM entreprise ORDER BY id');
        $used = [];
        foreach ($rows as $row) {
            $base = $slugger->slug((string) $row['libelle'])->lower()->toString();
            if ($base === '') {
                $base = 'compagnie';
            }
            $slug = $base;
            $i = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base . '-' . $i;
                $i++;
            }
            $used[] = $slug;
            $this->connection->executeStatement(
                'UPDATE entreprise SET slug = :slug WHERE id = :id',
                ['slug' => $slug, 'id' => $row['id']]
            );
        }

        $this->connection->executeStatement('CREATE UNIQUE INDEX UNIQ_entreprise_slug ON entreprise (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_entreprise_slug ON entreprise');
        $this->addSql('ALTER TABLE entreprise DROP slug');
    }
}
