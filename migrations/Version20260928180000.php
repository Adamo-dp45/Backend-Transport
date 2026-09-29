<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * `media_object` rattaché à une entreprise, et stockage PRIVÉ des justificatifs.
 *
 * POURQUOI : un média n'appartenait à personne. Qui devinait un identifiant lisait l'URL, et l'URL
 * donnait le fichier sans authentification — justificatifs et bulletins de salaire compris. Détail au
 * docbloc de `MediaObject` et au README, module Dépense.
 *
 *  - les colonnes d'`EntityBase` : `deleted_at`, que `EntrepriseScopeExtension` filtre sur toute
 *    entité d'entreprise, et `created_by`, l'auteur de l'upload — le seul qui puisse accrocher un
 *    document privé à une dépense ;
 *  - `identreprise` ;
 *  - `document_path` : le nom du fichier dans le stockage PRIVÉ (`public/documents`, interdit au serveur
 *    web depuis le 29/09/2026 — d'abord `var/documents`). Non nul = document
 *    privé ; la portée se DÉDUIT de cette colonne, aucune colonne `prive` à côté qui pourrait la contredire.
 *
 * RATTRAPAGE : un média déjà accroché reçoit l'entreprise de la fiche qui le porte. Sans lui, tout média
 * existant deviendrait introuvable au prochain enregistrement de sa fiche (l'IRI ne se résoudrait plus).
 * Mesuré avant migration : 0 média en base de développement — le rattrapage ne sert qu'aux autres bases.
 * Les fichiers existants RESTENT PUBLICS : les déplacer relève d'une reprise de données, pas d'un
 * changement de schéma. Un justificatif public n'est simplement plus servi par la route de téléchargement.
 */
final class Version20260928180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache media_object à une entreprise (identreprise + colonnes EntityBase) et ajoute document_path (stockage privé)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object ADD created_at DATETIME DEFAULT NULL, ADD created_from_ip VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD updated_from_ip VARCHAR(255) DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD deleted_from_ip VARCHAR(255) DEFAULT NULL, ADD created_by INT DEFAULT NULL, ADD updated_by INT DEFAULT NULL, ADD deleted_by INT DEFAULT NULL, ADD etatdelete TINYINT DEFAULT 0, ADD document_path VARCHAR(255) DEFAULT NULL, ADD identreprise INT DEFAULT NULL');

        // Rattrapage par la fiche qui porte le média (l'entreprise elle-même pour son logo).
        $this->addSql('UPDATE media_object m INNER JOIN depense d ON d.justificatif_id = m.id SET m.identreprise = d.identreprise WHERE m.identreprise IS NULL');
        $this->addSql('UPDATE media_object m INNER JOIN personnel p ON p.image_id = m.id SET m.identreprise = p.identreprise WHERE m.identreprise IS NULL');
        $this->addSql('UPDATE media_object m INNER JOIN piece p ON p.image_id = m.id SET m.identreprise = p.identreprise WHERE m.identreprise IS NULL');
        $this->addSql('UPDATE media_object m INNER JOIN entreprise e ON e.image_id = m.id SET m.identreprise = e.id WHERE m.identreprise IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object DROP created_at, DROP created_from_ip, DROP updated_at, DROP updated_from_ip, DROP deleted_at, DROP deleted_from_ip, DROP created_by, DROP updated_by, DROP deleted_by, DROP etatdelete, DROP document_path, DROP identreprise');
    }
}
