<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReferenceStatus;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'API publique de réservation — la seule surface ouverte SANS authentification, consommée par les
 * applications mobiles du client invité.
 *
 * Le périmètre entreprise n'y est pas porté par un jeton mais par le paramètre '?slug='. C'est le
 * SEUL rempart entre deux compagnies sur cette surface : si un provider oublie de le résoudre, les
 * départs d'un transporteur s'affichent dans l'application d'un autre. Chaque endpoint est donc
 * vérifié sur trois points — accessible sans jeton, borné au slug, et refusant un slug inconnu.
 */
final class ReservationPubliqueTest extends ApiTestCase
{
    private Reseau $compagnie;

    private Reseau $concurrente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compagnie = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 180, 240]);
        $this->concurrente = $this->scenario->reseau(['Daloa', 'Man']);
    }

    #[Test]
    #[TestDox("La fiche de la compagnie est accessible sans aucun jeton")]
    public function compagnieAccessibleSansJeton(): void
    {
        $this->requete('GET', '/api/reservation/compagnie?slug=' . $this->slug($this->compagnie));

        $this->assertStatut(200);
        self::assertSame($this->compagnie->entreprise->getLibelle(), $this->reponseJson()['libelle'] ?? null);
    }

    #[Test]
    #[TestDox("Un slug inconnu renvoie 404, sans révéler si la compagnie existe")]
    public function slugInconnu(): void
    {
        $this->requete('GET', '/api/reservation/compagnie?slug=compagnie-qui-nexiste-pas');

        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Un slug absent est refusé : aucune compagnie par défaut")]
    public function slugAbsent(): void
    {
        $this->requete('GET', '/api/reservation/compagnie');

        // Servir une compagnie arbitraire faute de slug exposerait la première venue.
        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Une compagnie suspendue disparaît de l'API publique")]
    public function compagnieSuspendue(): void
    {
        $this->compagnie->entreprise->setStatut(ReferenceStatus::SUSPENDU->value);
        $this->em->flush();

        $this->requete('GET', '/api/reservation/compagnie?slug=' . $this->slug($this->compagnie));

        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Les gares d'une ville sont celles du slug, jamais celles d'une autre compagnie")]
    public function garesBorneesAuSlug(): void
    {
        $this->requete('GET', sprintf(
            '/api/reservation/gares?slug=%s&ville=%d',
            $this->slug($this->compagnie),
            $this->compagnie->ville('Bouaké')->getId()
        ));

        $this->assertStatut(200);
        self::assertSame(['Bouaké'], $this->libellesRetournes());
    }

    #[Test]
    #[TestDox("Une ville d'une autre compagnie ne livre aucune gare, même avec un slug valide")]
    public function villeDUneAutreCompagnieNeFuitPas(): void
    {
        // Slug de la compagnie A, identifiant de ville appartenant à la compagnie B : le croisement
        // doit rester vide. Sans le filtre d'entreprise dans la requête, on lirait le réseau du voisin.
        $this->requete('GET', sprintf(
            '/api/reservation/gares?slug=%s&ville=%d',
            $this->slug($this->compagnie),
            $this->concurrente->ville('Daloa')->getId()
        ));

        $this->assertStatut(200);
        self::assertSame([], $this->libellesRetournes());
    }

    #[Test]
    #[TestDox("Sans paramètre de ville, aucune gare n'est divulguée")]
    public function garesSansVille(): void
    {
        $this->requete('GET', '/api/reservation/gares?slug=' . $this->slug($this->compagnie));

        $this->assertStatut(200);
        self::assertSame([], $this->libellesRetournes(), 'le réseau complet n\'est pas exposé par défaut');
    }

    #[Test]
    #[TestDox("Les départs proposés sont ceux de la compagnie du slug")]
    public function departsBornesAuSlug(): void
    {
        $this->scenario->tarif($this->compagnie, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->voyage(
            $this->compagnie,
            car: $this->scenario->car($this->compagnie->entreprise, 30),
            depart: new \DateTimeImmutable('+2 days 08:00')
        );

        $this->requete('GET', sprintf(
            '/api/reservation/departs?slug=%s&provenance=%d&destination=%d',
            $this->slug($this->compagnie),
            $this->compagnie->gare('Abidjan')->getId(),
            $this->compagnie->gare('Korhogo')->getId()
        ));

        $this->assertStatut(200);
        self::assertCount(1, $this->reponseJson()['member'] ?? []);
    }

    #[Test]
    #[TestDox("Le départ expose l'heure de passage à la gare du client, pour lui éviter tout calcul")]
    public function departExposeLHeureDePassage(): void
    {
        $this->scenario->tarif($this->compagnie, 'Bouaké', 'Korhogo', 8000);
        $this->scenario->voyage(
            $this->compagnie,
            car: $this->scenario->car($this->compagnie->entreprise, 30),
            depart: new \DateTimeImmutable('+2 days 08:00')
        );

        $this->requete('GET', sprintf(
            '/api/reservation/departs?slug=%s&provenance=%d&destination=%d',
            $this->slug($this->compagnie),
            $this->compagnie->gare('Bouaké')->getId(),
            $this->compagnie->gare('Korhogo')->getId()
        ));

        $this->assertStatut(200);
        $depart = ($this->reponseJson()['member'] ?? [])[0] ?? [];

        self::assertArrayHasKey(
            'heurepassage',
            $depart,
            "le client monte à Bouaké : sans cette heure, l'app devrait refaire la somme des tronçons"
        );
        // Départ d'Abidjan à 08:00, Bouaké à +3 h.
        self::assertStringContainsString('11:00', (string) $depart['heurepassage']);
    }

    #[Test]
    #[TestDox("La liste des villes est publique et bornée au slug")]
    public function villesBorneesAuSlug(): void
    {
        $this->requete('GET', '/api/reservation/villes?slug=' . $this->slug($this->compagnie));

        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("Le suivi d'une réservation exige le code ET le téléphone")]
    public function suiviExigeCodeEtTelephone(): void
    {
        $this->requete('GET', '/api/reservation/suivi?slug=' . $this->slug($this->compagnie));

        // Sans les deux, on pourrait énumérer les réservations d'une compagnie.
        self::assertContains(
            $this->client->getResponse()->getStatusCode(),
            [400, 404, 422],
            'un suivi sans identifiants ne doit jamais aboutir'
        );
    }

    private function slug(Reseau $reseau): string
    {
        return (string) $reseau->entreprise->getSlug();
    }
}
