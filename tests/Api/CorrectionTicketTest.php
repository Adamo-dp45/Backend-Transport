<?php

namespace App\Tests\Api;

use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * La CORRECTION d'identité d'un billet (`PATCH /api/tickets/{id}`) : qui peut la faire, et jusqu'à quand.
 *
 * Deux bornes coexistent, et c'est voulu :
 *   · la GARE émettrice corrige tant que le car n'a pas quitté la gare de montée ;
 *   · le VENDEUR À BORD corrige SES PROPRES VENTES tant que le car n'a pas atteint l'escale suivante
 *     — il encaisse après le départ, il doit pouvoir se relire dans la foulée.
 *
 * Être le commercial d'un voyage n'ouvre PAS les billets émis au guichet par les gares desservies :
 * la propriété se lit sur 'Ticket::commercial', que seule la vente à bord renseigne. Le corollaire
 * est assumé et vérifié ici : passé le départ, un billet de guichet n'est plus corrigible par
 * personne, pas même par un administrateur.
 */
final class CorrectionTicketTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $commercial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);

        // Rattaché à Abidjan : sa gare d'attache n'est PAS la position du car.
        $this->commercial = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'MODIFIER']]
        );

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10)
        );
        $this->voyage
            ->setCommercial($this->commercial)
            ->setDatedepartreelle(new DateTimeImmutable('-2 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();
    }

    #[Test]
    #[TestDox('Le vendeur à bord corrige sa propre vente tant que le car est sur la gare de montée')]
    public function vendeurCorrigeSaVente(): void
    {
        $billet = $this->venteABord(siege: 1);

        $this->requete('PATCH', '/api/tickets/' . $billet->getId(), $this->commercial, [
            'nomclient' => 'Konan Aya',
        ]);

        $this->assertStatut(200);
        self::assertSame('Konan Aya', $this->relire(Ticket::class, $billet->getId())->getNomclient());
    }

    #[Test]
    #[TestDox("Le commercial du voyage ne corrige PAS un billet vendu au guichet d'une autre gare")]
    public function commercialNeCorrigePasLeGuichet(): void
    {
        // Vendu par la gare de Bouaké, pas par lui : aucun 'commercial' sur le billet.
        $billet = $this->venteGuichet(siege: 2, de: 'Bouaké');

        $this->requete('PATCH', '/api/tickets/' . $billet->getId(), $this->commercial, [
            'nomclient' => 'Tentative',
        ]);

        self::assertContains(
            $this->client->getResponse()->getStatusCode(),
            [400, 403],
            'être affecté au départ n\'ouvre pas les ventes des guichets'
        );
        self::assertNotSame(
            'Tentative',
            $this->relire(Ticket::class, $billet->getId())->getNomclient(),
            'le billet ne doit pas avoir bougé'
        );
    }

    #[Test]
    #[TestDox('La vente à bord reste corrigible après le départ, là où la gare a déjà perdu la main')]
    public function venteABordSurvitAuDepart(): void
    {
        $billet = $this->venteABord(siege: 3);

        // Le car quitte Bouaké : la borne de la GARE se ferme (Passage::departReelle).
        $this->scenario->passage($this->reseau, $this->voyage, 'Bouaké',
            arrivee: new DateTimeImmutable('-1 hour'),
            depart: new DateTimeImmutable('-10 minutes')
        );
        // La collection 'passages' du voyage est déjà en mémoire : sans relecture, la garde ne voit
        // pas le départ qu'on vient d'écrire.
        $this->em->refresh($this->voyage);

        $agentBouake = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            ['Ticket' => ['VOIR', 'MODIFIER']]
        );
        $this->requete('PATCH', '/api/tickets/' . $billet->getId(), $agentBouake, ['nomclient' => 'Par la gare']);
        self::assertStatut(400);

        // Le vendeur à bord, lui, roule encore vers Korhogo : il se relit.
        $this->requete('PATCH', '/api/tickets/' . $billet->getId(), $this->commercial, ['nomclient' => 'Par le vendeur']);
        $this->assertStatut(200);
        self::assertSame('Par le vendeur', $this->relire(Ticket::class, $billet->getId())->getNomclient());
    }

    #[Test]
    #[TestDox('Passé le départ, un billet de guichet n\'est plus corrigible, pas même par un admin')]
    public function billetGuichetIncorrigibleApresDepart(): void
    {
        $billet = $this->venteGuichet(siege: 4, de: 'Bouaké');
        $this->scenario->passage($this->reseau, $this->voyage, 'Bouaké',
            arrivee: new DateTimeImmutable('-1 hour'),
            depart: new DateTimeImmutable('-10 minutes')
        );
        // La collection 'passages' du voyage est déjà en mémoire : sans relecture, la garde ne voit
        // pas le départ qu'on vient d'écrire.
        $this->em->refresh($this->voyage);

        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->requete('PATCH', '/api/tickets/' . $billet->getId(), $admin, ['nomclient' => 'Par l\'admin']);

        self::assertStatut(400);
        self::assertNotSame(
            'Par l\'admin',
            $this->relire(Ticket::class, $billet->getId())->getNomclient(),
            'conséquence assumée de la restriction : la correction doit se faire avant le départ'
        );
    }

    #[Test]
    #[TestDox("Le repère 'modifiable' lu par le front dit exactement ce que l'API acceptera")]
    public function repereModifiableSuitLaRegle(): void
    {
        $sienne = $this->venteABord(siege: 5);
        $guichet = $this->venteGuichet(siege: 6, de: 'Bouaké');
        $this->scenario->passage($this->reseau, $this->voyage, 'Bouaké',
            arrivee: new DateTimeImmutable('-1 hour'),
            depart: new DateTimeImmutable('-10 minutes')
        );
        // La collection 'passages' du voyage est déjà en mémoire : sans relecture, la garde ne voit
        // pas le départ qu'on vient d'écrire.
        $this->em->refresh($this->voyage);

        $this->requete('GET', '/api/tickets/' . $sienne->getId(), $this->commercial);
        self::assertTrue($this->reponseJson()['modifiable'], 'sa vente : « Modifier » doit s\'afficher');

        $this->requete('GET', '/api/tickets/' . $guichet->getId(), $this->commercial);
        self::assertFalse($this->reponseJson()['modifiable'], 'vente du guichet : « Modifier » doit disparaître');
    }

    // --------------------------------------------------------------------------------------------

    /** Un billet émis PAR le vendeur à bord (porte son identité de commercial). */
    private function venteABord(int $siege): Ticket
    {
        return $this->scenario->billet(
            $this->reseau, $this->voyage, $siege, 'Bouaké', 'Korhogo',
            commercial: $this->commercial
        );
    }

    /** Un billet émis AU GUICHET : aucun commercial dessus, la recette est à la gare. */
    private function venteGuichet(int $siege, string $de): Ticket
    {
        return $this->scenario->billet($this->reseau, $this->voyage, $siege, $de, 'Korhogo');
    }
}
