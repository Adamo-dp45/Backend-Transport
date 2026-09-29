<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LE TOTAL DES CHARGES SUR LE LISTING DES VOYAGES, ET SON COÛT.
 *
 * Afficher une somme sur une liste appelle naturellement une requête par ligne : le N+1, invisible en
 * développement (25 requêtes rapides sur 30 voyages de démonstration) et ruineux en production. C'est
 * la question qu'il faut donc MESURER, pas supposer : `VoyageProvider` rassemble les identifiants de la
 * page et `DepenseRepository::totauxParVoyages()` ramène tous les totaux d'un coup.
 *
 * Ce fichier vérifie les deux promesses : le chiffre est juste, et il ne coûte QU'UNE requête de plus
 * quelle que soit la taille de la page. La seconde assertion est celle qui empêche un futur
 * « simplifions, mettons un SUM dans le getter » de passer inaperçu.
 */
final class ListeVoyageChargesTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    /** @return list<array<string, mixed>> */
    private function listeVoyages(User $acteur): array
    {
        $this->requete('GET', '/api/voyages?itemsPerPage=30', $acteur);
        $this->assertStatut(200);

        return $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];
    }

    private function creerVoyageAvecCharges(array $montants): int
    {
        $voyage = $this->scenario->voyage($this->reseau);
        $type = $this->scenario->typedepense($this->reseau->entreprise, 'Frais de route');
        foreach ($montants as $montant) {
            $depense = $this->scenario->depense($this->reseau->entreprise, $montant, $this->reseau->gare('Abidjan'));
            $depense->setVoyage($voyage)->setTypedepense($type);
        }
        $this->em->flush();

        return (int) $voyage->getId();
    }

    #[Test]
    #[TestDox('Le listing porte le total des charges de chaque départ')]
    public function leListingPorteLeTotal(): void
    {
        $avecDeux = $this->creerVoyageAvecCharges([25000, 5000]);
        $sansCharge = (int) $this->scenario->voyage($this->reseau)->getId();

        $totaux = [];
        foreach ($this->listeVoyages($this->admin) as $v) {
            $totaux[$v['id']] = $v['depensestotal'] ?? 'ABSENT';
        }

        self::assertSame(30000, $totaux[$avecDeux], 'les deux charges sont additionnées');
        /*
            ZÉRO et non null : l'acteur a le droit de savoir, et la réponse est « aucune charge ». Le
            repository omet les voyages sans dépense, c'est au provider de traduire cette absence.
        */
        self::assertSame(0, $totaux[$sansCharge], 'un départ sans charge affiche 0, pas rien');
    }

    #[Test]
    #[TestDox("Sans le droit sur les dépenses, le total reste NUL et non zéro")]
    public function sansPermissionLeTotalEstNul(): void
    {
        $this->creerVoyageAvecCharges([25000]);
        $agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Voyage' => ['VOIR']]
        );

        foreach ($this->listeVoyages($agent) as $v) {
            self::assertNull(
                $v['depensestotal'],
                'un zéro laisserait croire à un départ sans frais : « je ne sais pas » s\'écrit null'
            );
        }
    }

    #[Test]
    #[TestDox('UNE seule requête de charges, que la page porte 1 départ ou 20')]
    public function uneSeuleRequetePourToutePage(): void
    {
        $this->creerVoyageAvecCharges([1000]);
        self::assertSame(1, $this->requetesDeCharges(), 'un départ');

        for ($i = 0; $i < 19; $i++) {
            $this->creerVoyageAvecCharges([1000]);
        }
        self::assertSame(
            1,
            $this->requetesDeCharges(),
            'vingt départs : si ce nombre a grandi, la somme est calculée par LIGNE et non par PAGE'
        );
    }

    /**
     * Nombre de requêtes SQL TOUCHANT LA TABLE DES DÉPENSES pendant un appel au listing.
     *
     * ON NE COMPTE PAS LE TOTAL DES REQUÊTES, et c'est délibéré : mesuré, il vaut 41 puis 29 sur deux
     * appels identiques — le premier réchauffe les caches de métadonnées, et la sérialisation charge
     * paresseusement ligne à ligne (ligne, car, gare) un N+1 préexistant qui n'a rien à voir avec ce
     * champ. Un chiffre global noierait donc le signal dans le bruit. On isole la table qui nous
     * concerne : c'est la seule mesure qui répond à la question posée.
     *
     * Le profiler est lu plutôt qu'un logger installé à la main : il mesure ce que la requête HTTP a
     * RÉELLEMENT exécuté, provider et extensions comprises.
     */
    private function requetesDeCharges(): int
    {
        $this->client->enableProfiler();
        $this->listeVoyages($this->admin);

        $profil = $this->client->getProfile();
        self::assertNotFalse($profil, 'profiler indisponible : la mesure ne voudrait rien dire');

        $compte = 0;
        foreach ($profil->getCollector('db')->getQueries() as $connexion) {
            foreach ($connexion as $requete) {
                if (str_contains(strtolower((string) ($requete['sql'] ?? '')), 'from depense')) {
                    $compte++;
                }
            }
        }

        return $compte;
    }
}
