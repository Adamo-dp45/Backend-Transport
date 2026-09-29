<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * « COURRIERS HORS CHIFFRE D'AFFAIRES » DOIT VALOIR PARTOUT, OU NULLE PART.
 *
 * `ConfigRecette::$courriershorsca` permet à une compagnie de traiter le fret comme une activité à
 * part : la recette courrier reste AFFICHÉE sur sa propre ligne, mais elle ne compte dans AUCUN total
 * composite. La règle est écrite dans `RecetteGareService` — « ils restent affichés via
 * recetteCourriers, mais ne comptent ni dans recetteTotale ni dans le canal guichet ».
 *
 * Trois surfaces l'ignoraient, et chacune produisait DEUX CHIFFRES POUR LA MÊME CHOSE sur un seul
 * écran : la recette d'une gare l'excluait pendant que ses séries journalières la comptaient, le
 * classement des agents la comptait, et le résultat d'un départ aussi.
 *
 * LE DÉFAUT NE SE VOYAIT PAS sur le jeu de démonstration : le drapeau y est actif sur la seule
 * compagnie qui n'a AUCUN courrier. Zéro d'un côté comme de l'autre — mesuré. D'où ce fichier, qui
 * monte le cas que les données ne montraient pas : un réseau avec des courriers ET le drapeau levé.
 */
final class CourriersHorsCaTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private Voyage $voyage;

    private User $agent;

    /** Recette hors courriers du scénario : un billet à 15 000. */
    private const BILLETS = 15000;

    /** Le courrier du scénario : 4 000 de transport + 500 de frais de suivi. */
    private const COURRIERS = 4500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        // LE DRAPEAU EST LEVÉ : tout ce qui suit doit s'y conformer.
        $this->scenario->configRecette($this->reseau->entreprise, courriersHorsCa: true);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 20)
        );

        /*
            UN AGENT VENDEUR IDENTIFIÉ : sans 'createdBy', aucune vente n'est rattachée à personne et le
            classement des agents n'aurait rien à classer — le test passerait à vide.
        */
        $this->agent = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan')
        );

        $billet = $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo', prix: self::BILLETS);
        $billet->setCreatedBy((int) $this->agent->getId());

        $courrier = $this->scenario->courrier(
            $this->reseau, 'Abidjan', 'Korhogo',
            montant: 4000,
            fraissuivi: 500,
            statut: 'EN_TRANSIT'
        );
        $courrier->setVoyage($this->voyage)->setCreatedBy((int) $this->agent->getId());
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function json(string $uri): array
    {
        $this->requete('GET', $uri . (str_contains($uri, '?') ? '&' : '?') . 'debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson();
    }

    #[Test]
    #[TestDox("La recette d'une gare exclut les courriers, et l'affiche à part")]
    public function recetteDeGare(): void
    {
        // Le socle : c'est la surface qui définit la règle, elle sert de référence aux autres.
        $parGare = $this->json('/api/stats/gares')['parGare'] ?? [];
        $abidjan = $this->gare($parGare, 'Abidjan');

        self::assertSame(self::BILLETS, (int) $abidjan['recetteTotale'], 'le total composite les exclut');
        self::assertSame(self::COURRIERS, (int) $abidjan['recetteCourriers'], 'mais ils restent lisibles');
    }

    #[Test]
    #[TestDox("Les séries JOURNALIÈRES d'une gare les excluent aussi")]
    public function seriesJournalieres(): void
    {
        /*
            LE DÉFAUT LE PLUS VICIEUX : sur le MÊME écran, le total de la gare les excluait et la courbe
            du jour les comptait. La somme de la série ne tombait donc pas sur le total affiché juste
            au-dessus, sans qu'aucun des deux nombres soit signalé comme faux.
        */
        $abidjan = $this->gare($this->json('/api/stats/gares')['parGare'] ?? [], 'Abidjan');
        $serie = $abidjan['serieJour'] ?? [];

        self::assertNotEmpty($serie, 'la série journalière est servie');
        $sommeSerie = 0;
        foreach ($serie as $point) {
            $sommeSerie += (int) (is_array($point) ? ($point['recette'] ?? $point['montant'] ?? 0) : $point);
        }

        self::assertSame(
            (int) $abidjan['recetteTotale'],
            $sommeSerie,
            'la somme de la série doit tomber sur le total de la gare, sinon un des deux ment'
        );
    }

    #[Test]
    #[TestDox("Le classement des AGENTS les exclut de la recette totale")]
    public function recetteParAgent(): void
    {
        $performances = $this->json('/api/stats/agent')['performances'] ?? [];
        self::assertNotEmpty($performances, 'au moins un agent a vendu');

        foreach ($performances as $agent) {
            // Chaque agent : sa recette courrier reste lisible, son TOTAL ne la compte pas.
            self::assertSame(
                (float) $agent['recetteTickets'] + (float) $agent['recetteBagages'],
                (float) $agent['recetteTotale'],
                'le total d\'un agent additionne billets et bagages, pas les courriers hors CA'
            );
        }
    }

    #[Test]
    #[TestDox("Le RÉSULTAT d'un départ les exclut de sa recette")]
    public function resultatDuVoyage(): void
    {
        /*
            Même raison : `recette` est un total COMPOSITE. Si le fret ne compte pas dans le chiffre
            d'affaires de la compagnie, il ne peut pas compter dans celui d'un de ses départs — le
            résultat du voyage serait alors plus généreux que le bénéfice auquel il contribue.
        */
        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/resultat', $this->admin);
        $this->assertStatut(200);
        $r = $this->reponseJson();

        self::assertSame(self::COURRIERS, (int) $r['courriers'], 'la part courrier reste lisible');
        self::assertSame(self::BILLETS, (int) $r['recette'], 'mais la recette du départ les exclut');
    }

    #[Test]
    #[TestDox("Drapeau BAISSÉ, tout les compte : la bascule fait bouger les mêmes chiffres")]
    public function drapeauBaisseToutCompte(): void
    {
        /*
            La contrepartie indispensable. Un correctif qui exclurait les courriers TOUJOURS passerait
            les quatre tests précédents et casserait les compagnies qui les comptent — c'est-à-dire le
            cas par défaut. On rebascule donc le drapeau et l'on exige l'inverse.
        */
        /*
            ON BASCULE LE DRAPEAU EXISTANT, on n'en crée pas un second : 'ConfigRecette' est un SINGLETON
            par entreprise, et une deuxième ligne laisserait le service lire la première — le test
            mesurerait alors l'ancien réglage en croyant mesurer le nouveau.
        */
        $config = $this->em->getRepository(\App\Entity\ConfigRecette::class)
            ->findOneBy(['identreprise' => $this->reseau->identreprise()]);
        self::assertNotNull($config, 'le paramétrage du setUp doit exister');
        $config->setCourriershorsca(false);
        $this->em->flush();

        $attendu = self::BILLETS + self::COURRIERS;

        $abidjan = $this->gare($this->json('/api/stats/gares')['parGare'] ?? [], 'Abidjan');
        self::assertSame($attendu, (int) $abidjan['recetteTotale'], 'la gare les compte');

        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/resultat', $this->admin);
        $this->assertStatut(200);
        self::assertSame($attendu, (int) $this->reponseJson()['recette'], 'le départ les compte');

        foreach ($this->json('/api/stats/agent')['performances'] ?? [] as $agent) {
            self::assertSame(
                (float) $agent['recetteTickets'] + (float) $agent['recetteBagages'] + (float) $agent['recetteCourriers'],
                (float) $agent['recetteTotale'],
                'l\'agent les compte'
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $parGare
     * @return array<string, mixed>
     */
    private function gare(array $parGare, string $nom): array
    {
        $libelle = (string) $this->reseau->gare($nom)->getLibelle();
        foreach ($parGare as $ligne) {
            if (($ligne['libelle'] ?? null) === $libelle) {
                return $ligne;
            }
        }

        self::fail(sprintf('gare « %s » absente de parGare', $libelle));
    }
}
