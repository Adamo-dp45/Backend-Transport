<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le PLAN DES SIÈGES vu par tronçon (`GET /api/sieges?car=&voyage=&montee=&descente=`) — et surtout
 * l'avertissement « déjà vendu par une gare aval ».
 *
 * La priorité amont a un revers : un siège vendu par une gare située plus bas sur la ligne
 * n'apparaît pas occupé à l'amont, qui le revend sans le savoir et évince ce passager. Le plan doit
 * donc dire DEUX choses à la fois, sans que l'une abîme l'autre : ce siège est VENDABLE (la règle
 * ne change pas) et le vendre COÛTE une place à quelqu'un (l'agent choisira ailleurs s'il peut).
 *
 * Un test par confusion possible : le signal doit se taire quand la vente aval est une revente
 * légitime, et ne jamais transformer un siège libre en siège occupé.
 */
final class PlanSiegesTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Abidjan (1) → Bouaké (2) → Yamoussoukro (3) → Korhogo (4)
        $this->reseau = $this->scenario->reseau(
            ['Abidjan', 'Bouaké', 'Yamoussoukro', 'Korhogo'],
            [null, 240, 120, 180]
        );

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'CREER']]
        );
    }

    #[Test]
    #[TestDox("Un siège vendu par une gare AVAL reste libre pour l'amont, mais est signalé")]
    public function siegeVenduEnAvalEstSignale(): void
    {
        // Bouaké a déjà vendu le siège 3 jusqu'à Korhogo. Abidjan vend Abidjan → Korhogo.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Bouaké', a: 'Korhogo');

        $siege = $this->siegeDuPlan(3, de: 'Abidjan', a: 'Korhogo');

        self::assertSame('LIBRE', $siege['statut'], 'la priorité amont ne bouge pas : le siège reste vendable');
        self::assertTrue($siege['venduAval'], "l'agent doit savoir qu'il évincerait quelqu'un");
        self::assertSame('Bouaké', $siege['avalMontee']);
        self::assertSame('Korhogo', $siege['avalDescente']);
        self::assertSame(1, $siege['avalNombre']);
        self::assertNotEmpty($siege['avalNom'], 'nommer le passager : c\'est lui qu\'on prive de sa place');
    }

    #[Test]
    #[TestDox('Une vente aval qui ne recouvre PAS le tronçon vendu ne déclenche aucune alerte')]
    public function reventeLegitimeNeDeclenchePasLAlerte(): void
    {
        /*
            LE CAS À NE PAS CONFONDRE, et celui qui décrédibiliserait l'alerte s'il la déclenchait :
            le passager monte à Yamoussoukro, là où l'acheteur DESCEND. Personne n'est évincé — c'est
            la revente d'un siège sur deux tronçons disjoints, exactement ce que la compagnie veut.
        */
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Yamoussoukro', a: 'Korhogo');

        $siege = $this->siegeDuPlan(3, de: 'Abidjan', a: 'Yamoussoukro');

        self::assertSame('LIBRE', $siege['statut']);
        self::assertFalse($siege['venduAval'], 'une revente propre ne doit pas crier au loup');
    }

    #[Test]
    #[TestDox("Un passager qui DESCEND à ma gare ne déclenche aucune alerte : son siège s'y libère")]
    public function billetQuiDescendALaGareDeMontee(): void
    {
        /*
            LE FAUX POSITIF QUI A ÉTÉ LIVRÉ, et le plus visible de tous : Abidjan vend jusqu'à Bouaké,
            puis Bouaké ouvre son plan pour revendre le siège — et le voyait signalé « vendu par une
            gare en aval ». Son occupant vient pourtant d'en DESCENDRE : le siège est libre à Bouaké,
            il n'y a personne à évincer.

            La cause : on échappe au test d'occupation de DEUX façons — monter plus tard (le vrai cas
            aval) ou être monté plus tôt et avoir DÉJÀ DESCENDU. Sans la borne '$tm > $ordreMontee',
            la seconde passait pour la première.

            Un repère qui se déclenche à tort sur la revente la plus banale du réseau ne serait plus
            regardé du tout — et ne préviendrait donc plus des vraies évictions.
        */
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Abidjan', a: 'Bouaké');

        $siege = $this->siegeDuPlan(3, de: 'Bouaké', a: 'Korhogo');

        self::assertSame('LIBRE', $siege['statut'], 'le passager descend ici : le siège se libère');
        self::assertFalse($siege['venduAval'], 'personne ne serait évincé — rien à signaler');
        self::assertNull($siege['avalMontee']);
        self::assertSame(0, $siege['avalNombre']);
    }

    #[Test]
    #[TestDox("Un passager descendu AVANT ma gare ne déclenche rien non plus")]
    public function billetEntierementEnAmont(): void
    {
        // Abidjan → Bouaké vu depuis Yamoussoukro : le billet est derrière nous de bout en bout.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Abidjan', a: 'Bouaké');

        $siege = $this->siegeDuPlan(3, de: 'Yamoussoukro', a: 'Korhogo');

        self::assertSame('LIBRE', $siege['statut']);
        self::assertFalse($siege['venduAval']);
    }

    #[Test]
    #[TestDox("Depuis une gare intermédiaire, une vente encore plus en aval reste signalée")]
    public function alerteDepuisUneGareIntermediaire(): void
    {
        // Le cas aval RESTE détecté quand l'acheteur n'est pas à l'origine de la ligne : Bouaké vend
        // jusqu'à Korhogo, Yamoussoukro a déjà vendu ce siège.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Yamoussoukro', a: 'Korhogo');

        $siege = $this->siegeDuPlan(3, de: 'Bouaké', a: 'Korhogo');

        self::assertTrue($siege['venduAval'], 'la correction ne doit pas éteindre le vrai cas');
        self::assertSame('Yamoussoukro', $siege['avalMontee']);
    }

    #[Test]
    #[TestDox("Un siège réellement occupé à la montée reste OCCUPE, sans alerte aval")]
    public function siegeOccupeEnAmontResteOccupe(): void
    {
        // Le passager est déjà assis quand l'acheteur monte : c'est une occupation, pas une alerte.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Abidjan', a: 'Korhogo');

        $siege = $this->siegeDuPlan(3, de: 'Abidjan', a: 'Korhogo');

        self::assertSame('OCCUPE', $siege['statut']);
        self::assertFalse($siege['venduAval']);
    }

    #[Test]
    #[TestDox('Plusieurs ventes aval sont comptées, et c\'est la plus AMONT qui est nommée')]
    public function plusieursVentesAvalSontComptees(): void
    {
        // Deux montées à l'intérieur du tronçon Abidjan → Korhogo : les deux sauteraient.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Yamoussoukro', a: 'Korhogo');
        $premier = $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Bouaké', a: 'Yamoussoukro');

        $siege = $this->siegeDuPlan(3, de: 'Abidjan', a: 'Korhogo');

        self::assertTrue($siege['venduAval']);
        self::assertSame(2, $siege['avalNombre'], 'ne pas en cacher un derrière l\'autre');
        self::assertSame($premier->getNomclient(), $siege['avalNom'], 'le plus amont monte le premier');
        self::assertSame('Bouaké', $siege['avalMontee']);
    }

    #[Test]
    #[TestDox("Sans tronçon demandé, aucune alerte : on ne sait pas ce qui serait vendu")]
    public function sansTronconAucuneAlerte(): void
    {
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Bouaké', a: 'Korhogo');

        $this->requete('GET', sprintf(
            '/api/sieges?car=%d&voyage=%d',
            $this->voyage->getCar()->getId(),
            $this->voyage->getId()
        ), $this->agent);

        $this->assertStatut(200);
        foreach ($this->reponseJson()['member'] ?? [] as $siege) {
            self::assertFalse($siege['venduAval'], 'sans montée ni descente, l\'alerte n\'a pas de sens');
        }
    }

    /**
     * Le siège numéro $numero tel que le plan le renvoie pour le tronçon $de → $a.
     *
     * @return array<string, mixed>
     */
    private function siegeDuPlan(int $numero, string $de, string $a): array
    {
        $this->requete('GET', sprintf(
            '/api/sieges?car=%d&voyage=%d&montee=%d&descente=%d',
            $this->voyage->getCar()->getId(),
            $this->voyage->getId(),
            $this->reseau->gare($de)->getId(),
            $this->reseau->gare($a)->getId()
        ), $this->agent);

        $this->assertStatut(200);

        foreach ($this->reponseJson()['member'] ?? [] as $siege) {
            if (($siege['numero'] ?? null) === $numero) {
                return $siege;
            }
        }

        self::fail(sprintf('Le siège n°%d est absent du plan renvoyé.', $numero));
    }
}
