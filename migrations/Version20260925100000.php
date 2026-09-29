<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MAIN D'ŒUVRE EXTERNE sur un dépannage : le soudeur du village, le garage appelé au bord de la
 * route, le remorquage.
 *
 * C'est une charge, et elle se range avec le DÉPANNAGE — pas dans `Depense`. La verser là scinderait
 * le coût d'une même panne en deux postes (pièces d'un côté, main d'œuvre de l'autre) : le poste
 * « dépannages » du bénéfice sous-estimerait ce qu'une panne coûte, et la fiche d'un car réparé par
 * des mains externes le ferait passer pour bon marché — les deux chiffres restant individuellement
 * corrects. Les trois postes du bénéfice restent DISJOINTS.
 *
 * `Depannage::$couttotal` devient « pièces + main d'œuvre ». AUCUN BACK-REMPLISSAGE n'est nécessaire :
 * la table part vide, donc la main d'œuvre de tous les dépannages existants vaut zéro et leur coût
 * total reste exactement celui de leurs pièces.
 */
final class Version20260925100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Main d\'œuvre externe des dépannages (detailmaindoeuvre)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE detailmaindoeuvre (
                id INT AUTO_INCREMENT NOT NULL,
                depannage_id INT NOT NULL,
                intervenant VARCHAR(255) NOT NULL,
                prestation VARCHAR(255) DEFAULT NULL,
                montant BIGINT NOT NULL,
                INDEX idx_maindoeuvre_depannage (depannage_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE detailmaindoeuvre
                ADD CONSTRAINT FK_D8D727EEAFF9529D FOREIGN KEY (depannage_id) REFERENCES depannage (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE detailmaindoeuvre DROP FOREIGN KEY FK_D8D727EEAFF9529D');
        $this->addSql('DROP TABLE detailmaindoeuvre');
    }
}
