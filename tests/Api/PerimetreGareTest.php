<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le périmètre de GARE, qui se superpose au périmètre entreprise.
 *
 * 'GareScopeExtension' distingue trois régimes selon l'interface portée par l'entité, et chacun se
 * casse différemment :
 *   · GareOwnedInterface        (User)            → un seul champ gare
 *   · MultiGareScopedInterface  (Courrier, Bagage)→ visible si la gare est au DÉPART ou à L'ARRIVÉE
 *   · LigneGareScopedInterface  (Voyage, Ligne)   → visible si la ligne DESSERT la gare
 *
 * Il ne s'active que pour un agent RATTACHÉ à une gare et non-admin : les trois autres profils
 * (admin entreprise, super admin, utilisateur central sans gare) voient tout. Chacun est couvert
 * ici, car c'est la combinaison « rattaché + non-admin » qui est facile à casser par inadvertance.
 */
final class PerimetreGareTest extends ApiTestCase
{
    private Reseau $nord;

    private Reseau $sud;

    protected function setUp(): void
    {
        parent::setUp();

        // Deux lignes DISJOINTES de la même compagnie : une gare de l'une ne dessert pas l'autre.
        $this->nord = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->sud = $this->scenario->reseau(['Daloa', 'San-Pédro'], entreprise: $this->nord->entreprise);
    }

    #[Test]
    #[TestDox("Un agent ne voit que les voyages des lignes qui desservent sa gare")]
    public function voyagesBornesAuxLignesDesservies(): void
    {
        $this->scenario->voyage($this->nord, car: $this->scenario->car($this->nord->entreprise, 10));
        $this->scenario->voyage($this->sud, car: $this->scenario->car($this->nord->entreprise, 10));

        $agentBouake = $this->agent('nord', 'Bouaké');
        $this->requete('GET', '/api/voyages', $agentBouake);

        $this->assertStatut(200);
        self::assertCount(
            1,
            $this->reponseJson()['member'] ?? [],
            "la ligne du Sud ne passe pas par Bouaké : son voyage n'a rien à faire dans la liste"
        );
    }

    #[Test]
    #[TestDox("Un agent ne voit que les lignes qui desservent sa gare")]
    public function lignesBorneesAuxDesservies(): void
    {
        $agentBouake = $this->agent('nord', 'Bouaké');

        $this->requete('GET', '/api/lignes', $agentBouake);

        $this->assertStatut(200);
        self::assertCount(1, $this->reponseJson()['member'] ?? []);
    }

    #[Test]
    #[TestDox("La fiche d'une ligne conserve TOUS ses arrêts, même pour un agent borné à une gare")]
    public function ficheLigneConserveTousLesArrets(): void
    {
        $agentBouake = $this->agent('nord', 'Bouaké');

        $this->requete('GET', '/api/lignes/' . $this->nord->ligne->getId(), $agentBouake);

        $this->assertStatut(200);
        // Le piège : borner la VISIBILITÉ de la ligne par un INNER JOIN sur les arrêts tronquerait
        // la collection sérialisée au seul arrêt de l'agent (eager-loading). La fiche ne
        // renverrait qu'une gare, et le sélecteur « gare de descente » resterait vide côté front.
        // C'est pourquoi l'extension filtre par sous-requête EXISTS.
        self::assertCount(
            3,
            $this->reponseJson()['arrets'] ?? [],
            'la ligne doit exposer ses trois arrêts, pas seulement celui de l\'agent'
        );
    }

    #[Test]
    #[TestDox("Un admin de gare ne voit que les utilisateurs de sa gare")]
    public function utilisateursBornesALaGare(): void
    {
        // ROLE_ADMIN_GARE contourne les permissions sur les entités de gare (dont 'User'), mais
        // n'est PAS ROLE_ADMIN : le filtre de gare doit continuer de s'appliquer.
        $chefBouake = $this->agent('nord', 'Bouaké', ['ROLE_ADMIN_GARE']);
        $this->agent('nord', 'Bouaké');
        $this->agent('nord', 'Korhogo');
        $this->agent('nord', 'Abidjan');

        $this->requete('GET', '/api/users', $chefBouake);

        $this->assertStatut(200);
        $emails = array_column($this->reponseJson()['member'] ?? [], 'email');

        self::assertCount(2, $emails, 'le chef de gare et l\'agent de Bouaké, et personne d\'autre');
        self::assertContains($chefBouake->getEmail(), $emails);
    }

    #[Test]
    #[TestDox("Un admin d'entreprise échappe au filtre de gare")]
    public function adminEchappeAuFiltreDeGare(): void
    {
        $this->scenario->voyage($this->nord, car: $this->scenario->car($this->nord->entreprise, 10));
        $this->scenario->voyage($this->sud, car: $this->scenario->car($this->nord->entreprise, 10));

        // Rattaché à une gare ET admin : le rôle prime, il voit toute la compagnie.
        $admin = $this->agent('nord', 'Bouaké', ['ROLE_ADMIN']);
        $this->requete('GET', '/api/voyages', $admin);

        $this->assertStatut(200);
        self::assertCount(2, $this->reponseJson()['member'] ?? []);
    }

    #[Test]
    #[TestDox("Un utilisateur central, sans gare, voit toute la compagnie")]
    public function utilisateurCentralVoitTout(): void
    {
        $this->scenario->voyage($this->nord, car: $this->scenario->car($this->nord->entreprise, 10));
        $this->scenario->voyage($this->sud, car: $this->scenario->car($this->nord->entreprise, 10));

        $central = $this->scenario->utilisateur($this->nord->entreprise);
        $this->requete('GET', '/api/voyages', $central);

        $this->assertStatut(200);
        self::assertCount(
            2,
            $this->reponseJson()['member'] ?? [],
            "sans rattachement, aucun périmètre de gare ne s'applique"
        );
    }

    #[Test]
    #[TestDox("Le filtre de gare n'ouvre jamais le périmètre entreprise : les deux se cumulent")]
    public function filtreGareNOuvrePasLePerimetreEntreprise(): void
    {
        // Une autre compagnie, dont une gare porte le même nom.
        $concurrent = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->scenario->voyage($concurrent, car: $this->scenario->car($concurrent->entreprise, 10));
        $this->scenario->voyage($this->nord, car: $this->scenario->car($this->nord->entreprise, 10));

        $agentBouake = $this->agent('nord', 'Bouaké');
        $this->requete('GET', '/api/voyages', $agentBouake);

        $this->assertStatut(200);
        self::assertCount(1, $this->reponseJson()['member'] ?? [], 'seul le voyage de SA compagnie');
    }

    /** @param list<string> $roles */
    private function agent(string $reseau, string $gare, array $roles = []): \App\Entity\User
    {
        $cible = $reseau === 'nord' ? $this->nord : $this->sud;

        return $this->scenario->utilisateur(
            $this->nord->entreprise,
            gare: $cible->gare($gare),
            roles: $roles
        );
    }
}
