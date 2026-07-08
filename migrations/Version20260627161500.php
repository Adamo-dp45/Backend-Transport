<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le bagage est désormais toujours rattaché au billet du client : son identité (nom/contact) provient
 * du billet. On supprime les colonnes dupliquées nomclient/contactclient de la table bagage.
 *
 * À jouer APRÈS Version20260627160818 (ajout de bagage.ticket_id).
 */
final class Version20260627161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bagage : suppression des colonnes nomclient/contactclient (identité reprise du billet rattaché).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage DROP nomclient, DROP contactclient');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bagage ADD nomclient VARCHAR(255) NOT NULL, ADD contactclient VARCHAR(255) NOT NULL');
    }
}
