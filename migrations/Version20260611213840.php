<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611213840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE approvisionnement (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, dateappro DATETIME NOT NULL, identreprise INT DEFAULT NULL, verrouille TINYINT DEFAULT NULL, fournisseur_id INT NOT NULL, INDEX IDX_516C3FAA670C757F (fournisseur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE arret (id INT AUTO_INCREMENT NOT NULL, ordre INT NOT NULL, identreprise INT DEFAULT NULL, ligne_id INT NOT NULL, gare_id INT NOT NULL, INDEX IDX_BBD1570E5A438E76 (ligne_id), INDEX IDX_BBD1570E63FD956 (gare_id), UNIQUE INDEX UNIQ_arret_ligne_gare (ligne_id, gare_id), UNIQUE INDEX UNIQ_arret_ligne_ordre (ligne_id, ordre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE bagage (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, codebagage VARCHAR(255) NOT NULL, nomclient VARCHAR(255) NOT NULL, contactclient VARCHAR(255) NOT NULL, nature VARCHAR(255) NOT NULL, type VARCHAR(255) DEFAULT NULL, poids INT NOT NULL, montant INT NOT NULL, montantforce TINYINT NOT NULL, statut VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, voyage_id INT DEFAULT NULL, garedescente_id INT DEFAULT NULL, tarifbagage_id INT DEFAULT NULL, INDEX IDX_A82C571568C9E5AF (voyage_id), INDEX IDX_A82C57154734D724 (garedescente_id), INDEX IDX_A82C5715938B927E (tarifbagage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE car (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, matricule VARCHAR(255) NOT NULL, nbrsiege INT DEFAULT NULL, datearrivee DATETIME NOT NULL, etat VARCHAR(20) NOT NULL, identreprise INT DEFAULT NULL, sieges_gauche INT NOT NULL, sieges_droite INT NOT NULL, marque_id INT DEFAULT NULL, typevehicule_id INT DEFAULT NULL, modelvehicule_id INT DEFAULT NULL, INDEX IDX_773DE69D4827B9B2 (marque_id), INDEX IDX_773DE69D3402134C (typevehicule_id), INDEX IDX_773DE69D403ECEFE (modelvehicule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE courrier (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, codecourrier VARCHAR(255) NOT NULL, nomexpediteur VARCHAR(255) NOT NULL, contactexpediteur VARCHAR(255) NOT NULL, nomdestinataire VARCHAR(255) NOT NULL, contactdestinataire VARCHAR(255) NOT NULL, fraissuivi INT DEFAULT NULL, montant INT NOT NULL, statut VARCHAR(50) NOT NULL, identreprise INT DEFAULT NULL, modepaiement VARCHAR(50) NOT NULL, etatpaiement VARCHAR(50) NOT NULL, datepaiement DATETIME DEFAULT NULL, garedepart_id INT NOT NULL, garearrivee_id INT NOT NULL, voyage_id INT DEFAULT NULL, INDEX IDX_BEF47CAA16887400 (garedepart_id), INDEX IDX_BEF47CAAB466CD0 (garearrivee_id), INDEX IDX_BEF47CAA68C9E5AF (voyage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE depannage (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, datedepannage DATETIME NOT NULL, lieudepannage VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, identreprise INT DEFAULT NULL, couttotal INT DEFAULT NULL, statut VARCHAR(255) NOT NULL, car_id INT NOT NULL, typepanne_id INT NOT NULL, INDEX IDX_F3C7E6B1C3C6F69F (car_id), INDEX IDX_F3C7E6B139DE3B35 (typepanne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detailapprovisionnement (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prixunitaire NUMERIC(10, 2) NOT NULL, couttotal INT DEFAULT NULL, approvisionnement_id INT NOT NULL, piece_id INT NOT NULL, INDEX IDX_DAA21B32AE741A98 (approvisionnement_id), INDEX IDX_DAA21B32C40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detailcourrier (id INT AUTO_INCREMENT NOT NULL, nature VARCHAR(255) NOT NULL, designation VARCHAR(255) DEFAULT NULL, emballage VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, poids INT DEFAULT NULL, valeur INT NOT NULL, montant INT NOT NULL, courrier_id INT NOT NULL, tarifcourrier_id INT DEFAULT NULL, INDEX IDX_AD731D028BF41DC7 (courrier_id), INDEX IDX_AD731D02B952E85D (tarifcourrier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detaildepannage (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prixunitaire INT DEFAULT NULL, depannage_id INT NOT NULL, piece_id INT NOT NULL, INDEX IDX_2BD94A0AAFF9529D (depannage_id), INDEX IDX_2BD94A0AC40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE detailpersonnel (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(255) NOT NULL, personnel_id INT NOT NULL, depannage_id INT DEFAULT NULL, voyage_id INT DEFAULT NULL, INDEX IDX_7EA25F651C109075 (personnel_id), INDEX IDX_7EA25F65AFF9529D (depannage_id), INDEX IDX_7EA25F6568C9E5AF (voyage_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE entreprise (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, contact1 VARCHAR(255) NOT NULL, contact2 VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, anneecreation DATE DEFAULT NULL, sigle VARCHAR(255) DEFAULT NULL, siteweb VARCHAR(255) DEFAULT NULL, rccm VARCHAR(255) DEFAULT NULL, banque VARCHAR(255) DEFAULT NULL, type VARCHAR(255) DEFAULT NULL, centreimpot VARCHAR(255) DEFAULT NULL, tauxtva INT DEFAULT NULL, statut VARCHAR(255) DEFAULT NULL, image_id INT DEFAULT NULL, INDEX IDX_D19FA603DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fournisseur (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, adresse VARCHAR(255) NOT NULL, pays VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE gare (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, chefgare VARCHAR(255) NOT NULL, ville VARCHAR(255) NOT NULL, libelle VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, contact1 VARCHAR(255) NOT NULL, contact2 VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, statut VARCHAR(255) NOT NULL, datecreation DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventaire (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, typemouvement VARCHAR(255) NOT NULL, quantite INT NOT NULL, datemouvement DATETIME NOT NULL, identreprise INT DEFAULT NULL, reference_type VARCHAR(255) NOT NULL, referenceid INT DEFAULT NULL, piece_id INT NOT NULL, INDEX IDX_338920E0C40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ligne (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, codeligne VARCHAR(255) NOT NULL, libelle VARCHAR(255) DEFAULT NULL, heuredepart TIME DEFAULT NULL, identreprise INT DEFAULT NULL, gareorigine_id INT NOT NULL, gareterminus_id INT NOT NULL, INDEX IDX_57F0DB83E1318B1C (gareorigine_id), INDEX IDX_57F0DB8364815E19 (gareterminus_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marque (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marquepiece (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media_object (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE model (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE modelvehicule (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE password_reset_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_6B7BA4B6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE permission (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, entity VARCHAR(255) NOT NULL, action VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, role_id INT DEFAULT NULL, INDEX IDX_E04992AAD60322AC (role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personnel (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, contact VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, statut VARCHAR(50) DEFAULT NULL, dateembauche DATETIME DEFAULT NULL, typepersonnel_id INT NOT NULL, image_id INT DEFAULT NULL, INDEX IDX_A6BCF3DE9B4F51A (typepersonnel_id), INDEX IDX_A6BCF3DE3DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE piece (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, stockinitial INT NOT NULL, prixunitaire INT NOT NULL, seuilstock INT DEFAULT NULL, typepiece_id INT DEFAULT NULL, marquepiece_id INT DEFAULT NULL, model_id INT DEFAULT NULL, image_id INT DEFAULT NULL, INDEX IDX_44CA0B2398664120 (typepiece_id), INDEX IDX_44CA0B23699D8DC0 (marquepiece_id), INDEX IDX_44CA0B237975B7E7 (model_id), INDEX IDX_44CA0B233DA5256D (image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_tokens (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, typerole VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE siege (id INT AUTO_INCREMENT NOT NULL, numero INT NOT NULL, rangee INT DEFAULT NULL, colonne INT DEFAULT NULL, cote VARCHAR(255) DEFAULT NULL, identreprise INT DEFAULT NULL, car_id INT NOT NULL, INDEX IDX_6706B4F7C3C6F69F (car_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarif_ligne (id INT AUTO_INCREMENT NOT NULL, montant INT NOT NULL, identreprise INT DEFAULT NULL, ligne_id INT NOT NULL, garedepart_id INT NOT NULL, garearrivee_id INT NOT NULL, INDEX IDX_8EC440735A438E76 (ligne_id), INDEX IDX_8EC4407316887400 (garedepart_id), INDEX IDX_8EC44073B466CD0 (garearrivee_id), UNIQUE INDEX UNIQ_tarifligne (ligne_id, garedepart_id, garearrivee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarifbagage (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, poidsmin INT NOT NULL, poidsmax INT DEFAULT NULL, montant INT NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tarifcourrier (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, valeurmin INT NOT NULL, valeurmax INT DEFAULT NULL, montanttaxe INT NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ticket (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, identreprise INT DEFAULT NULL, nomclient VARCHAR(255) DEFAULT NULL, contactclient VARCHAR(255) DEFAULT NULL, prix INT NOT NULL, codeticket VARCHAR(255) NOT NULL, voyage_id INT NOT NULL, siege_id INT NOT NULL, gare_id INT NOT NULL, garedescente_id INT DEFAULT NULL, INDEX IDX_97A0ADA368C9E5AF (voyage_id), INDEX IDX_97A0ADA3BF006E8B (siege_id), INDEX IDX_97A0ADA363FD956 (gare_id), INDEX IDX_97A0ADA34734D724 (garedescente_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE typepanne (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE typepersonnel (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE typepiece (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE typevehicule (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, identreprise INT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, statut VARCHAR(50) NOT NULL, is_founder TINYINT DEFAULT NULL, entreprise_id INT DEFAULT NULL, gare_id INT DEFAULT NULL, INDEX IDX_8D93D649A4AEAFEA (entreprise_id), INDEX IDX_8D93D64963FD956 (gare_id), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, identreprise INT DEFAULT NULL, usere_id INT DEFAULT NULL, role_id INT DEFAULT NULL, INDEX IDX_2DE8C6A312C1BC7E (usere_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE voyage (created_at DATETIME DEFAULT NULL, created_from_ip VARCHAR(255) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, updated_from_ip VARCHAR(255) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, deleted_from_ip VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, deleted_by INT DEFAULT NULL, etatdelete TINYINT DEFAULT 0, id INT AUTO_INCREMENT NOT NULL, codevoyage VARCHAR(255) NOT NULL, provenance VARCHAR(255) NOT NULL, destination VARCHAR(255) NOT NULL, datedebut DATETIME NOT NULL, datefin DATETIME DEFAULT NULL, identreprise INT DEFAULT NULL, placestotal INT DEFAULT NULL, placesoccupees INT DEFAULT NULL, ligne_id INT NOT NULL, car_id INT DEFAULT NULL, INDEX IDX_3F9D89555A438E76 (ligne_id), INDEX IDX_3F9D8955C3C6F69F (car_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE approvisionnement ADD CONSTRAINT FK_516C3FAA670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id)');
        $this->addSql('ALTER TABLE arret ADD CONSTRAINT FK_BBD1570E5A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE arret ADD CONSTRAINT FK_BBD1570E63FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C571568C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C57154734D724 FOREIGN KEY (garedescente_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE bagage ADD CONSTRAINT FK_A82C5715938B927E FOREIGN KEY (tarifbagage_id) REFERENCES tarifbagage (id)');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D4827B9B2 FOREIGN KEY (marque_id) REFERENCES marque (id)');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D3402134C FOREIGN KEY (typevehicule_id) REFERENCES typevehicule (id)');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D403ECEFE FOREIGN KEY (modelvehicule_id) REFERENCES modelvehicule (id)');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAA16887400 FOREIGN KEY (garedepart_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAAB466CD0 FOREIGN KEY (garearrivee_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE courrier ADD CONSTRAINT FK_BEF47CAA68C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE depannage ADD CONSTRAINT FK_F3C7E6B1C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)');
        $this->addSql('ALTER TABLE depannage ADD CONSTRAINT FK_F3C7E6B139DE3B35 FOREIGN KEY (typepanne_id) REFERENCES typepanne (id)');
        $this->addSql('ALTER TABLE detailapprovisionnement ADD CONSTRAINT FK_DAA21B32AE741A98 FOREIGN KEY (approvisionnement_id) REFERENCES approvisionnement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detailapprovisionnement ADD CONSTRAINT FK_DAA21B32C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id)');
        $this->addSql('ALTER TABLE detailcourrier ADD CONSTRAINT FK_AD731D028BF41DC7 FOREIGN KEY (courrier_id) REFERENCES courrier (id)');
        $this->addSql('ALTER TABLE detailcourrier ADD CONSTRAINT FK_AD731D02B952E85D FOREIGN KEY (tarifcourrier_id) REFERENCES tarifcourrier (id)');
        $this->addSql('ALTER TABLE detaildepannage ADD CONSTRAINT FK_2BD94A0AAFF9529D FOREIGN KEY (depannage_id) REFERENCES depannage (id)');
        $this->addSql('ALTER TABLE detaildepannage ADD CONSTRAINT FK_2BD94A0AC40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id)');
        $this->addSql('ALTER TABLE detailpersonnel ADD CONSTRAINT FK_7EA25F651C109075 FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE detailpersonnel ADD CONSTRAINT FK_7EA25F65AFF9529D FOREIGN KEY (depannage_id) REFERENCES depannage (id)');
        $this->addSql('ALTER TABLE detailpersonnel ADD CONSTRAINT FK_7EA25F6568C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE entreprise ADD CONSTRAINT FK_D19FA603DA5256D FOREIGN KEY (image_id) REFERENCES media_object (id)');
        $this->addSql('ALTER TABLE inventaire ADD CONSTRAINT FK_338920E0C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id)');
        $this->addSql('ALTER TABLE ligne ADD CONSTRAINT FK_57F0DB83E1318B1C FOREIGN KEY (gareorigine_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE ligne ADD CONSTRAINT FK_57F0DB8364815E19 FOREIGN KEY (gareterminus_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AAD60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DE9B4F51A FOREIGN KEY (typepersonnel_id) REFERENCES typepersonnel (id)');
        $this->addSql('ALTER TABLE personnel ADD CONSTRAINT FK_A6BCF3DE3DA5256D FOREIGN KEY (image_id) REFERENCES media_object (id)');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B2398664120 FOREIGN KEY (typepiece_id) REFERENCES typepiece (id)');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B23699D8DC0 FOREIGN KEY (marquepiece_id) REFERENCES marquepiece (id)');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B237975B7E7 FOREIGN KEY (model_id) REFERENCES model (id)');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B233DA5256D FOREIGN KEY (image_id) REFERENCES media_object (id)');
        $this->addSql('ALTER TABLE siege ADD CONSTRAINT FK_6706B4F7C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)');
        $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC440735A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC4407316887400 FOREIGN KEY (garedepart_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC44073B466CD0 FOREIGN KEY (garearrivee_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA368C9E5AF FOREIGN KEY (voyage_id) REFERENCES voyage (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA3BF006E8B FOREIGN KEY (siege_id) REFERENCES siege (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA363FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE ticket ADD CONSTRAINT FK_97A0ADA34734D724 FOREIGN KEY (garedescente_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649A4AEAFEA FOREIGN KEY (entreprise_id) REFERENCES entreprise (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D64963FD956 FOREIGN KEY (gare_id) REFERENCES gare (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A312C1BC7E FOREIGN KEY (usere_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D89555A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
        $this->addSql('ALTER TABLE voyage ADD CONSTRAINT FK_3F9D8955C3C6F69F FOREIGN KEY (car_id) REFERENCES car (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE approvisionnement DROP FOREIGN KEY FK_516C3FAA670C757F');
        $this->addSql('ALTER TABLE arret DROP FOREIGN KEY FK_BBD1570E5A438E76');
        $this->addSql('ALTER TABLE arret DROP FOREIGN KEY FK_BBD1570E63FD956');
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C571568C9E5AF');
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C57154734D724');
        $this->addSql('ALTER TABLE bagage DROP FOREIGN KEY FK_A82C5715938B927E');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D4827B9B2');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D3402134C');
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D403ECEFE');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAA16887400');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAAB466CD0');
        $this->addSql('ALTER TABLE courrier DROP FOREIGN KEY FK_BEF47CAA68C9E5AF');
        $this->addSql('ALTER TABLE depannage DROP FOREIGN KEY FK_F3C7E6B1C3C6F69F');
        $this->addSql('ALTER TABLE depannage DROP FOREIGN KEY FK_F3C7E6B139DE3B35');
        $this->addSql('ALTER TABLE detailapprovisionnement DROP FOREIGN KEY FK_DAA21B32AE741A98');
        $this->addSql('ALTER TABLE detailapprovisionnement DROP FOREIGN KEY FK_DAA21B32C40FCFA8');
        $this->addSql('ALTER TABLE detailcourrier DROP FOREIGN KEY FK_AD731D028BF41DC7');
        $this->addSql('ALTER TABLE detailcourrier DROP FOREIGN KEY FK_AD731D02B952E85D');
        $this->addSql('ALTER TABLE detaildepannage DROP FOREIGN KEY FK_2BD94A0AAFF9529D');
        $this->addSql('ALTER TABLE detaildepannage DROP FOREIGN KEY FK_2BD94A0AC40FCFA8');
        $this->addSql('ALTER TABLE detailpersonnel DROP FOREIGN KEY FK_7EA25F651C109075');
        $this->addSql('ALTER TABLE detailpersonnel DROP FOREIGN KEY FK_7EA25F65AFF9529D');
        $this->addSql('ALTER TABLE detailpersonnel DROP FOREIGN KEY FK_7EA25F6568C9E5AF');
        $this->addSql('ALTER TABLE entreprise DROP FOREIGN KEY FK_D19FA603DA5256D');
        $this->addSql('ALTER TABLE inventaire DROP FOREIGN KEY FK_338920E0C40FCFA8');
        $this->addSql('ALTER TABLE ligne DROP FOREIGN KEY FK_57F0DB83E1318B1C');
        $this->addSql('ALTER TABLE ligne DROP FOREIGN KEY FK_57F0DB8364815E19');
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_6B7BA4B6A76ED395');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AAD60322AC');
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DE9B4F51A');
        $this->addSql('ALTER TABLE personnel DROP FOREIGN KEY FK_A6BCF3DE3DA5256D');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B2398664120');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B23699D8DC0');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B237975B7E7');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B233DA5256D');
        $this->addSql('ALTER TABLE siege DROP FOREIGN KEY FK_6706B4F7C3C6F69F');
        $this->addSql('ALTER TABLE tarif_ligne DROP FOREIGN KEY FK_8EC440735A438E76');
        $this->addSql('ALTER TABLE tarif_ligne DROP FOREIGN KEY FK_8EC4407316887400');
        $this->addSql('ALTER TABLE tarif_ligne DROP FOREIGN KEY FK_8EC44073B466CD0');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA368C9E5AF');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA3BF006E8B');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA363FD956');
        $this->addSql('ALTER TABLE ticket DROP FOREIGN KEY FK_97A0ADA34734D724');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649A4AEAFEA');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D64963FD956');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A312C1BC7E');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D89555A438E76');
        $this->addSql('ALTER TABLE voyage DROP FOREIGN KEY FK_3F9D8955C3C6F69F');
        $this->addSql('DROP TABLE approvisionnement');
        $this->addSql('DROP TABLE arret');
        $this->addSql('DROP TABLE bagage');
        $this->addSql('DROP TABLE car');
        $this->addSql('DROP TABLE courrier');
        $this->addSql('DROP TABLE depannage');
        $this->addSql('DROP TABLE detailapprovisionnement');
        $this->addSql('DROP TABLE detailcourrier');
        $this->addSql('DROP TABLE detaildepannage');
        $this->addSql('DROP TABLE detailpersonnel');
        $this->addSql('DROP TABLE entreprise');
        $this->addSql('DROP TABLE fournisseur');
        $this->addSql('DROP TABLE gare');
        $this->addSql('DROP TABLE inventaire');
        $this->addSql('DROP TABLE ligne');
        $this->addSql('DROP TABLE marque');
        $this->addSql('DROP TABLE marquepiece');
        $this->addSql('DROP TABLE media_object');
        $this->addSql('DROP TABLE model');
        $this->addSql('DROP TABLE modelvehicule');
        $this->addSql('DROP TABLE password_reset_token');
        $this->addSql('DROP TABLE permission');
        $this->addSql('DROP TABLE personnel');
        $this->addSql('DROP TABLE piece');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE siege');
        $this->addSql('DROP TABLE tarif_ligne');
        $this->addSql('DROP TABLE tarifbagage');
        $this->addSql('DROP TABLE tarifcourrier');
        $this->addSql('DROP TABLE ticket');
        $this->addSql('DROP TABLE typepanne');
        $this->addSql('DROP TABLE typepersonnel');
        $this->addSql('DROP TABLE typepiece');
        $this->addSql('DROP TABLE typevehicule');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_role');
        $this->addSql('DROP TABLE voyage');
    }
}
