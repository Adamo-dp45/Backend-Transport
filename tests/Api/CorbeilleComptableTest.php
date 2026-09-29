<?php

namespace App\Tests\Api;

use App\Entity\Approvisionnement;
use App\Entity\Depannage;
use App\Entity\Piece;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use App\Repository\DepannageRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * ON NE SUPPRIME PAS UN DOCUMENT COMPTABLE VIVANT — ET UN COÛT NE SURVIT PAS À LA CORBEILLE.
 *
 * Deux règles posées le 28/09/2026, qui ne tiennent QU'ENSEMBLE :
 *
 *  1. les coûts d'un approvisionnement et d'un dépannage filtrent désormais `deletedAt IS NULL`, en
 *     plus de leur `statut != 'ANNULE'` — par symétrie avec les recettes : un total ne doit pas
 *     contenir de ligne qu'aucun écran ne montre ;
 *  2. une ligne encore VALIDE ne peut plus partir à la corbeille : il faut l'ANNULER d'abord.
 *
 * POURQUOI LA SECONDE EST INDISPENSABLE À LA PREMIÈRE : l'annulation d'un approvisionnement RETIRE les
 * pièces du stock, la corbeille ne touche à rien. Filtrer la corbeille dans le coût SANS ce garde
 * produirait du STOCK SANS COÛT — des pièces gratuites en inventaire et un bénéfice trop beau, les
 * deux chiffres restant individuellement plausibles.
 *
 * C'est aussi la règle dont parlent les systèmes comptables : une écriture ne s'efface pas, elle se
 * contre-passe. L'entité le documentait déjà — « suppression d'un document comptable : admin
 * d'entreprise UNIQUEMENT (la sortie normale est l'annulation, tracée) » — sans que rien ne l'applique.
 */
final class CorbeilleComptableTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private int $carId;

    private int $typepanneId;

    private int $pieceId;

    private int $fournisseurId;

    private const PRIX_PIECE = 12000;

    private const QUANTITE = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->carId = (int) $this->scenario->car($this->reseau->entreprise, 30)->getId();
        $this->typepanneId = (int) $this->scenario->typepanne($this->reseau->entreprise)->getId();
        $this->pieceId = (int) $this->scenario->piece($this->reseau->entreprise, 'Filtre', self::PRIX_PIECE)->getId();
        $this->fournisseurId = (int) $this->scenario->fournisseur($this->reseau->entreprise)->getId();
    }

    private function creerAppro(): int
    {
        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $this->fournisseurId,
            'details' => [[
                'piece' => $this->pieceId,
                'quantite' => self::QUANTITE,
                'prixunitaire' => self::PRIX_PIECE,
            ]],
        ]);
        $this->assertStatut(201);

        return (int) $this->reponseJson()['id'];
    }

    private function creerDepannage(): int
    {
        $this->requete('POST', '/api/depannages', $this->admin, [
            'lieudepannage' => 'Bord de route',
            'description' => 'Panne moteur',
            'car' => $this->carId,
            'typepanne' => $this->typepanneId,
            'details' => [['piece' => $this->pieceId, 'quantite' => 1]],
        ]);
        $this->assertStatut(201);

        return (int) $this->reponseJson()['id'];
    }

    private function coutAppros(): int
    {
        return (int) static::getContainer()->get(ApprovisionnementRepository::class)->coutTotal(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
            $this->reseau->identreprise()
        );
    }

    private function coutDepannages(): int
    {
        return (int) static::getContainer()->get(DepannageRepository::class)->coutTotal(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
            $this->reseau->identreprise()
        );
    }

    #[Test]
    #[TestDox('Un approvisionnement VALIDE refuse la corbeille et dit quoi faire')]
    public function approValideRefuseLaCorbeille(): void
    {
        $id = $this->creerAppro();

        $this->requete('PATCH', '/api/approvisionnements/' . $id . '/remove', $this->admin, []);
        $this->assertStatut(400);

        /*
            LE MESSAGE COMPTE AUTANT QUE LE REFUS : un agent qui reçoit « interdit » sans la marche à
            suivre rouvre un ticket. On vérifie donc qu'il nomme l'annulation.
        */
        self::assertStringContainsString('nnul', $this->client->getResponse()->getContent(), 'le refus indique d\'annuler d\'abord');

        // Et la ligne est intacte : le coût n'a pas bougé.
        self::assertSame(self::PRIX_PIECE * self::QUANTITE, $this->coutAppros(), 'le coût reste entier');
        self::assertNull($this->relire(Approvisionnement::class, $id)->getDeletedAt(), 'rien n\'est parti à la corbeille');
    }

    #[Test]
    #[TestDox("Un dépannage non annulé refuse la corbeille")]
    public function depannageVivantRefuseLaCorbeille(): void
    {
        $id = $this->creerDepannage();

        $this->requete('PATCH', '/api/depannages/' . $id . '/remove', $this->admin, []);
        $this->assertStatut(400);

        self::assertNull($this->relire(Depannage::class, $id)->getDeletedAt());
        self::assertSame(self::PRIX_PIECE, $this->coutDepannages(), 'le coût de la panne reste engagé');
    }

    #[Test]
    #[TestDox("Annulé, il part à la corbeille — et le stock a bien été retiré AVANT")]
    public function annuleDAbordPuisCorbeille(): void
    {
        /*
            LA VOIE NORMALE, et la raison d'être du garde : c'est l'ANNULATION qui touche au stock. On
            mesure les deux dans le même test, sinon on vérifie un refus sans avoir montré ce qu'il
            protège.
        */
        $stockAvant = (int) $this->em->find(Piece::class, $this->pieceId)->getStockinitial();
        $id = $this->creerAppro();

        $this->em->clear();
        $stockApresEntree = (int) $this->em->find(Piece::class, $this->pieceId)->getStockinitial();
        self::assertSame($stockAvant + self::QUANTITE, $stockApresEntree, 'l\'approvisionnement fait entrer le stock');

        $this->requete('PATCH', '/api/approvisionnements/' . $id . '/annuler', $this->admin, []);
        $this->assertStatut(200);

        $this->em->clear();
        self::assertSame($stockAvant, (int) $this->em->find(Piece::class, $this->pieceId)->getStockinitial(), 'l\'annulation retire le stock');
        self::assertSame(0, $this->coutAppros(), 'et sort des coûts par son STATUT');

        // Maintenant la corbeille est permise : la ligne est déjà neutre des deux côtés.
        $this->requete('PATCH', '/api/approvisionnements/' . $id . '/remove', $this->admin, []);
        $this->assertStatut(200);
        self::assertNotNull($this->relire(Approvisionnement::class, $id)->getDeletedAt());
        self::assertSame(0, $this->coutAppros(), 'le coût reste à zéro, sans double effet');
    }

    #[Test]
    #[TestDox("Le filtre de corbeille protège les coûts même si le garde est contourné")]
    public function leFiltreTientSansLeGarde(): void
    {
        /*
            CE TEST CONTOURNE LE GARDE VOLONTAIREMENT, en posant 'deletedAt' directement — ce que fait
            n'importe quel chemin d'écriture qui ne passe pas par 'SoftDeleteProcessor' : une reprise de
            données, une commande, un import, une migration. Le garde est la règle ; le filtre est la
            ceinture. Sans ce test, le filtre ne serait couvert par RIEN, puisque par la voie normale
            seule une ligne déjà ANNULE (donc déjà hors coûts) peut atteindre la corbeille.
        */
        $idAppro = $this->creerAppro();
        $idDepannage = $this->creerDepannage();

        self::assertSame(self::PRIX_PIECE * self::QUANTITE, $this->coutAppros());
        self::assertSame(self::PRIX_PIECE, $this->coutDepannages());

        $this->em->clear();
        $this->em->find(Approvisionnement::class, $idAppro)->setDeletedAt(new \DateTimeImmutable());
        $this->em->find(Depannage::class, $idDepannage)->setDeletedAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->em->clear();

        self::assertSame(0, $this->coutAppros(), 'le coût de l\'appro quitte le total');
        self::assertSame(0, $this->coutDepannages(), 'celui du dépannage aussi');
    }
}
