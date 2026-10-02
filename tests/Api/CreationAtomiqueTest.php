<?php

namespace App\Tests\Api;

use App\Domain\Enum\CarStatus;
use App\Entity\Approvisionnement;
use App\Entity\Car;
use App\Entity\Courrier;
use App\Entity\Depannage;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UNE CRÉATION REFUSÉE NE LAISSE RIEN DERRIÈRE ELLE.
 *
 * Trois processors partageaient le même défaut : ils persistaient l'ENTÊTE puis la flushaient pour
 * obtenir son identifiant, et validaient les LIGNES ensuite. Une saisie refusée — une valeur de
 * colis à zéro, un prix unitaire absent — laissait donc en base un document VIDE pendant que
 * l'écran n'annonçait qu'une erreur de formulaire. L'utilisateur croyait n'avoir rien créé, et le
 * document fantôme comptait pourtant dans les listes et dans les totaux.
 *
 * Le dépannage était le pire des trois : il immobilise le véhicule AVANT le flush, si bien qu'un
 * refus laissait un car marqué EN PANNE sans aucun dépannage pour l'expliquer — sorti de
 * l'exploitation, et que plus rien ne permettait d'y ramener.
 *
 * !! CES TESTS COMPTENT LES LIGNES EN BASE, ils ne se contentent pas du code HTTP. Un test qui
 * vérifie seulement « la réponse est 400 » resterait vert avec le défaut : c'est précisément ce que
 * l'écran affichait déjà.
 */
final class CreationAtomiqueTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké'], [null, 240]);
        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            [
                'Courrier' => ['VOIR', 'CREER'],
                'Approvisionnement' => ['VOIR', 'CREER'],
                'Depannage' => ['VOIR', 'CREER'],
            ]
        );
    }

    #[Test]
    #[TestDox("Un colis sans valeur : aucun courrier n'est créé")]
    public function courrierRefuseNeLaisseRien(): void
    {
        $this->requete('POST', '/api/courriers', $this->agent, [
            'nomexpediteur' => 'Expéditeur',
            'contactexpediteur' => '+225 07 00 00 00 01',
            'nomdestinataire' => 'Destinataire',
            'contactdestinataire' => '+225 07 00 00 00 02',
            'gareArrivee' => $this->reseau->gare('Bouaké')->getId(),
            'details' => [[
                'nature' => 'Colis',
                'designation' => 'Carton',
                'type' => 'ORDINAIRE',
                'valeur' => 0, // L'oubli de saisie que le formulaire laisse passer.
            ]],
        ]);

        $this->assertStatut(400);
        self::assertCount(
            0,
            $this->em->getRepository(Courrier::class)->findAll(),
            'le courrier ne doit PAS exister : il n\'aurait aucun colis, et compterait pourtant dans les listes'
        );
    }

    #[Test]
    #[TestDox("Un prix unitaire absent : aucun approvisionnement n'est créé")]
    public function approvisionnementRefuseNeLaisseRien(): void
    {
        $fournisseur = $this->scenario->fournisseur($this->reseau->entreprise);
        $piece = $this->scenario->piece($this->reseau->entreprise);

        $this->requete('POST', '/api/approvisionnements', $this->agent, [
            'fournisseur' => $fournisseur->getId(),
            'details' => [[
                'piece' => $piece->getId(),
                'quantite' => 5,
                'prixunitaire' => 0,
            ]],
        ]);

        $this->assertStatut(400);
        self::assertCount(
            0,
            $this->em->getRepository(Approvisionnement::class)->findAll(),
            'un approvisionnement sans ligne pèserait zéro franc dans les coûts, tout en existant'
        );
    }

    #[Test]
    #[TestDox("Une ligne de pièce refusée : aucun dépannage, et le car n'est PAS immobilisé")]
    public function depannageRefuseNeLaisseRienEtLibereLeCar(): void
    {
        $car = $this->scenario->car($this->reseau->entreprise, 30);
        $typepanne = $this->scenario->typepanne($this->reseau->entreprise);
        $piece = $this->scenario->piece($this->reseau->entreprise);

        $this->requete('POST', '/api/depannages', $this->agent, [
            'car' => $car->getId(),
            'typepanne' => $typepanne->getId(),
            'lieudepannage' => 'Bord de route',
            'description' => 'Moteur',
            'details' => [
                ['piece' => $piece->getId(), 'quantite' => 1, 'prixunitaire' => 1000],
                // Le doublon est refusé PAR handleDetails, donc après le flush de l'entête.
                ['piece' => $piece->getId(), 'quantite' => 1, 'prixunitaire' => 1000],
            ],
        ]);

        $this->assertStatut(400);
        self::assertCount(
            0,
            $this->em->getRepository(Depannage::class)->findAll(),
            'aucun dépannage ne doit subsister'
        );

        $relu = $this->em->getRepository(Car::class)->find($car->getId());
        $this->em->refresh($relu);
        self::assertNotSame(
            CarStatus::EN_PANNE->value,
            $relu->getEtat(),
            "le car serait sorti de l'exploitation sans qu'aucun dépannage ne l'explique — et rien "
            . "n'aurait permis de l'y ramener"
        );
    }
}
