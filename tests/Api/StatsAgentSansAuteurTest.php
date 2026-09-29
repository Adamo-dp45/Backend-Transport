<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UNE VENTE SANS AUTEUR NE DOIT NI FAIRE TOMBER L'ÉCRAN, NI INVENTER UN VENDEUR.
 *
 * `created_by` est NULLABLE sur toutes les écritures. Deux conséquences se cachaient dans
 * `AgentStatsProvider` :
 *
 *  1. un courrier ou un bagage sans auteur produisait la clé de tableau `''` (PHP convertit `null` en
 *     chaîne vide), et `AgentPerformanceDto` déclare `int $id` : **TypeError, donc 500** sur
 *     `/api/stats/agent`. Tout l'écran des agents tombait pour une seule ligne mal attribuée ;
 *  2. les boucles des ACTIONS CRITIQUES faisaient `(int) $r['agentid']`, ce qui transforme `null` en
 *     **0** — un vendeur fantôme nommé « — » dans un tableau anti-fraude, avec des annulations à son
 *     nom. Pire qu'une erreur : un chiffre crédible et faux.
 *
 * Les billets échappaient au premier piège par accident : leur requête passe par un `INNER JOIN` sur
 * `User`, qui écarte les lignes sans auteur. Ce n'est pas une protection, c'est un effet de bord — et il
 * ne couvrait ni les courriers ni les bagages, qui lisent `createdBy` directement.
 *
 * Mesuré avant correction : zéro vente sans auteur en base aujourd'hui, le chemin n'était donc pas
 * atteignable. Mais rien n'empêche une écriture automatique (synchronisation hors ligne, réservation
 * publique, reprise de données) d'en créer une.
 */
final class StatsAgentSansAuteurTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->agent = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan')
        );
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $this->requete('GET', '/api/stats/agent?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson();
    }

    #[Test]
    #[TestDox("Un courrier sans auteur ne fait pas tomber l'écran des agents")]
    public function courrierSansAuteurNeCassePas(): void
    {
        $courrier = $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 4000);
        self::assertNull($courrier->getCreatedBy(), 'le scénario pose bien une vente sans auteur');

        // Avant correction : 500 (TypeError sur AgentPerformanceDto::$id).
        $stats = $this->stats();

        self::assertIsArray($stats['performances'] ?? null);
    }

    #[Test]
    #[TestDox("La vente sans auteur n'apparaît pas comme un agent, et son montant est DIT")]
    public function laVenteSansAuteurEstNommeeAPart(): void
    {
        $vu = $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 4000, fraissuivi: 500);
        $vu->setCreatedBy((int) $this->agent->getId());
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 7000); // sans auteur
        $this->em->flush();

        $stats = $this->stats();
        $ids = array_column($stats['performances'], 'id');

        self::assertSame([(int) $this->agent->getId()], $ids, 'un seul agent, le vrai');
        foreach ($ids as $id) {
            self::assertNotSame(0, $id, 'aucun agent n\'a l\'identifiant 0');
        }

        /*
            ON NE JETTE PAS LE MONTANT EN SILENCE. L'écarter du classement est juste — ce n'est l'œuvre
            de personne —, mais le faire disparaître sans un mot reproduirait le défaut que toute cette
            série de correctifs traque : un total qui ne totalise pas ce qu'il prétend. Le montant non
            attribué est donc servi à part, et la somme redevient réconciliable.
        */
        self::assertSame(7000.0, (float) ($stats['recetteNonAttribuee'] ?? -1));
    }

    #[Test]
    #[TestDox("Une annulation sans auteur ne crée pas d'agent n° 0 dans le tableau anti-fraude")]
    public function annulationSansAuteurNeCreePasDAgentFantome(): void
    {
        /*
            `(int) null` vaut 0 : sans garde, une ligne annulée par personne apparaîtrait comme un
            vendeur d'identifiant 0, nommé « — », avec des annulations à son nom. Dans un tableau qui
            sert à repérer les abus, c'est la dernière chose à inventer.

            !! CE TEST GARDE UNE DOUBLE PROTECTION, PAS UN DÉFAUT CONSTATÉ. Les sept requêtes des
            actions critiques filtrent DÉJÀ l'auteur nul en SQL (`updatedBy IS NOT NULL`,
            `createdBy IS NOT NULL`, `deletedBy IS NOT NULL`) : l'agent n° 0 n'a jamais pu apparaître.
            La garde `$agentValide` des boucles du provider est une SECONDE barrière. Mesuré le
            28/09/2026 : retirer le filtre SQL seul laisse ce test VERT (la garde PHP rattrape) ; il ne
            tombe que si l'on retire LES DEUX. À ne pas lire comme la preuve que la garde PHP a corrigé
            quelque chose — c'est la clause SQL qui porte la règle.
        */
        $courrier = $this->scenario->courrier(
            $this->reseau, 'Abidjan', 'Bouaké',
            montant: 4000,
            statut: 'ANNULE'
        );
        $courrier->setUpdatedBy(null);
        $this->em->flush();

        $stats = $this->stats();

        foreach ($stats['actionsCritiques'] ?? [] as $ligne) {
            self::assertNotSame(0, (int) $ligne['id'], 'aucune ligne critique sur un agent inexistant');
        }
    }
}
