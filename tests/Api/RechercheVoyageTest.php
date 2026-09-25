<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Un filtre NON DÉCLARÉ est ignoré EN SILENCE.
 *
 * C'est le piège que garde ce fichier, et il ne ressemble pas à une panne : ApiPlatform ne connaît
 * que les filtres déclarés en attribut, et répond la collection ENTIÈRE pour les autres — sans
 * erreur, sans avertissement, avec un 200 parfaitement crédible. `?provenance=ZZZZ` rendait donc
 * exactement les mêmes voyages que `?provenance=Abidjan`, et le sélecteur distant du frontend, qui
 * cherche précisément là-dessus, affichait la même liste quoi qu'on tape.
 *
 * Un test qui vérifierait seulement « la requête filtrée répond 200 » serait resté vert pendant tout
 * ce temps. D'où la forme retenue : on demande quelque chose qui n'existe PAS et on exige le vide.
 *
 * AUDIT DU 25/09/2026 — les huit ressources du sélecteur distant ('SearchController::RESOURCES' côté
 * FT) ont été recoupées une à une avec les filtres réellement déclarés. Deux étaient en défaut :
 * 'voyages' (cherchait 'provenance') et 'gares' (cherchait 'libelle' sur une entité qui n'avait AUCUN
 * filtre). Les six autres — fournisseurs, pieces, cars, personnels, lignes, users — sont conformes,
 * ainsi que les paramètres envoyés par les trois applications mobiles. Ce fichier garde les deux
 * qu'il a fallu réparer.
 */
final class RechercheVoyageTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->scenario->voyage($this->reseau, provenance: 'Abidjan');
        $this->scenario->voyage($this->reseau, provenance: 'Bouaké');
        /*
            L'acteur est construit UNE fois, dans le setUp : chaque requête HTTP redémarre le noyau et
            vide l'EntityManager, si bien qu'un utilisateur créé à la volée au milieu d'un test ferait
            resurgir une entreprise DÉTACHÉE — « A new entity was found through the relationship
            User#entreprise » — dès la deuxième recherche du même test.
        */
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    /**
     * @param array<string, string> $query
     * @return list<array<string, mixed>>
     */
    private function chercher(array $query): array
    {
        $this->requete('GET', '/api/voyages?' . http_build_query($query), $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];
    }

    #[Test]
    #[TestDox("Une provenance qui n'existe pas ne rend AUCUN voyage")]
    public function provenanceIntrouvableNeRendRien(): void
    {
        // LA sentinelle. Si le filtre disparaît du mapping, ce test rend deux voyages et tombe ;
        // l'assertion « Abidjan en rend un » resterait verte, elle, et ne protégerait rien.
        self::assertSame([], $this->chercher(['provenance' => 'GareQuiNExistePas']));
    }

    #[Test]
    #[TestDox('La provenance filtre, et par fragment')]
    public function provenanceFiltreParFragment(): void
    {
        $trouves = $this->chercher(['provenance' => 'Abidjan']);

        self::assertCount(1, $trouves);
        self::assertStringContainsString('Abidjan', $trouves[0]['provenance']);

        // PARTIAL et non EXACT : personne ne tape le libellé complet d'une gare dans une recherche.
        self::assertCount(1, $this->chercher(['provenance' => 'Abidj']));
    }

    #[Test]
    #[TestDox('La destination filtre aussi')]
    public function destinationFiltre(): void
    {
        self::assertSame([], $this->chercher(['destination' => 'GareQuiNExistePas']));
        self::assertNotEmpty($this->chercher(['destination' => 'Korhogo']));
    }

    #[Test]
    #[TestDox("Le libellé d'une gare filtre, alors qu'aucun filtre n'existait")]
    public function libelleDeGareFiltre(): void
    {
        /*
            'Gare' ne déclarait AUCUN filtre. Le défaut ne se voyait pas encore : aucun écran n'est
            branché sur la ressource 'gares' du sélecteur distant, et 'paginationEnabled: false' renvoie
            toutes les gares que tom-select filtre alors localement. Le premier sélecteur REACT s'en
            serait remis au serveur, lui, et aurait affiché la liste entière à chaque frappe.
        */
        $this->requete('GET', '/api/gares?libelle=GareQuiNExistePas', $this->admin);
        $this->assertStatut(200);
        self::assertSame([], $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []);

        $this->requete('GET', '/api/gares?libelle=Abidj', $this->admin);
        $this->assertStatut(200);
        $trouvees = $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];
        self::assertCount(1, $trouvees);
        self::assertStringContainsString('Abidjan', $trouvees[0]['libelle']);
    }

    #[Test]
    #[TestDox('Le code du voyage filtre par fragment')]
    public function codevoyageFiltre(): void
    {
        $tous = $this->chercher([]);
        self::assertNotEmpty($tous);
        $code = $tous[0]['codevoyage'];

        self::assertNotEmpty($this->chercher(['codevoyage' => substr($code, 0, 6)]));
        self::assertSame([], $this->chercher(['codevoyage' => 'XX-INTROUVABLE-XX']));
    }
}
