<?php

namespace App\Tests\Api;

use App\Entity\Depannage;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LE COÛT D'UNE PANNE, C'EST LES PIÈCES **PLUS** LA MAIN D'ŒUVRE EXTERNE.
 *
 * La main d'œuvre externe (le soudeur du village, le garage appelé au bord de la route) est une
 * charge, mais elle se range avec le DÉPANNAGE et non dans `Depense` : la verser là scinderait le coût
 * d'une même panne en deux postes, le poste « dépannages » du bénéfice sous-estimerait ce qu'une panne
 * coûte, et un car réparé par des mains externes passerait pour bon marché — les deux chiffres restant
 * individuellement corrects, donc invisibles. Les trois postes du bénéfice restent DISJOINTS.
 *
 * LA SENTINELLE EST LE TEST DE MODIFICATION. `Depannage::$couttotal` est recalculé à chaque écriture
 * depuis les pièces : y verser naïvement la main d'œuvre aurait suffi pour que la correction d'une
 * pièce l'efface du chiffre, sans erreur et sans trace. C'est exactement ce que
 * `corrigerUnePieceNEffacePasLaMainDoeuvre()` interdit.
 */
final class MaindoeuvreDepannageTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private int $carId;

    private int $typepanneId;

    private int $pieceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->carId = (int) $this->scenario->car($this->reseau->entreprise, 30)->getId();
        $this->typepanneId = (int) $this->scenario->typepanne($this->reseau->entreprise)->getId();
        $this->pieceId = (int) $this->scenario->piece($this->reseau->entreprise, 'Filtre', 12000)->getId();
    }

    /** @param list<array<string, mixed>> $maindoeuvres */
    private function ouvrir(int $quantitePiece, array $maindoeuvres): int
    {
        $this->requete('POST', '/api/depannages', $this->admin, [
            'lieudepannage' => 'Bord de route, axe Abidjan — Bouaké',
            'description' => 'Panne moteur',
            'car' => $this->carId,
            'typepanne' => $this->typepanneId,
            'details' => [['piece' => $this->pieceId, 'quantite' => $quantitePiece]],
            'maindoeuvres' => $maindoeuvres,
        ]);
        $this->assertStatut(201);

        return (int) $this->reponseJson()['id'];
    }

    private function coutTotal(int $id): int
    {
        return (int) $this->relire(Depannage::class, $id)->getCouttotal();
    }

    /**
     * Un PATCH avec la charge utile COMPLÈTE.
     *
     * !! 'DepannageInput' déclare 'lieudepannage' et 'car' NON NULS avec 'NotBlank'/'NotNull' : un
     * PATCH partiel est donc refusé en 422 par la VALIDATION, alors que 'handlePatch' est écrit pour
     * le supporter ('$data->lieudepannage ?? $depannage->getLieudepannage()'). Incohérence
     * PRÉEXISTANTE, indépendante de la main d'œuvre : on envoie donc tout, comme le client réel.
     *
     * @param array<string, mixed> $modifications
     */
    private function modifier(int $id, array $modifications): void
    {
        $this->requete('PATCH', '/api/depannages/' . $id, $this->admin, array_merge([
            'lieudepannage' => 'Bord de route, axe Abidjan — Bouaké',
            'car' => $this->carId,
            'typepanne' => $this->typepanneId,
        ], $modifications));
        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("Le coût du dépannage additionne les pièces et la main d'œuvre")]
    public function leCoutAdditionneLesDeux(): void
    {
        $id = $this->ouvrir(2, [
            ['intervenant' => 'Garage Kouassi', 'prestation' => 'Démontage culasse', 'montant' => 30000],
            ['intervenant' => 'Soudeur du village', 'montant' => 15000],
        ]);

        // 2 filtres à 12 000 = 24 000, plus 45 000 de main d'œuvre.
        self::assertSame(69000, $this->coutTotal($id));
    }

    #[Test]
    #[TestDox("Corriger une PIÈCE n'efface pas la main d'œuvre du coût total")]
    public function corrigerUnePieceNEffacePasLaMainDoeuvre(): void
    {
        $id = $this->ouvrir(2, [['intervenant' => 'Garage Kouassi', 'montant' => 45000]]);
        self::assertSame(69000, $this->coutTotal($id));

        /*
            LA MODIFICATION NE PORTE QUE SUR LES PIÈCES, et le payload ne mentionne pas la main d'œuvre.
            Le processus recalcule pourtant `couttotal` : il doit donc RELIRE en base le total qu'il n'a
            pas touché. Sans cela le coût retomberait à 36 000 — les pièces seules — et les 45 000
            disparaîtraient du chiffre sans erreur ni trace.
        */
        $this->modifier($id, ['details' => [['piece' => $this->pieceId, 'quantite' => 3]]]);

        self::assertSame(81000, $this->coutTotal($id), '3 × 12 000 de pièces + 45 000 de main d\'œuvre');
    }

    #[Test]
    #[TestDox("Un tableau vide SUPPRIME la main d'œuvre, l'absence la conserve")]
    public function videSupprimeAbsenceConserve(): void
    {
        $id = $this->ouvrir(1, [['intervenant' => 'Garage Kouassi', 'montant' => 45000]]);
        self::assertSame(57000, $this->coutTotal($id));

        // Absence de la clé : on ne touche pas — c'est le cas du test précédent, revérifié ici seul.
        $this->modifier($id, ['description' => 'Précisée']);
        self::assertSame(57000, $this->coutTotal($id), 'sans la clé, la main d\'œuvre est conservée');

        // Tableau VIDE : on supprime. C'est la nuance que porte le `?array` du DTO.
        $this->modifier($id, ['maindoeuvres' => []]);
        self::assertSame(12000, $this->coutTotal($id), 'tableau vide : il ne reste que la pièce');
    }

    #[Test]
    #[TestDox('Un montant nul ou négatif est refusé : il baisserait le coût de la panne')]
    public function montantInvalideRefuse(): void
    {
        foreach ([0, -5000] as $montant) {
            $this->requete('POST', '/api/depannages', $this->admin, [
                'lieudepannage' => 'Atelier',
                'car' => $this->carId,
                'typepanne' => $this->typepanneId,
                'details' => [['piece' => $this->pieceId, 'quantite' => 1]],
                'maindoeuvres' => [['intervenant' => 'Garage', 'montant' => $montant]],
            ]);
            $this->assertStatut(400);
        }
    }

    #[Test]
    #[TestDox("Un intervenant sans nom est refusé : « 45 000 de main d'œuvre » ne se relit pas")]
    public function intervenantObligatoire(): void
    {
        $this->requete('POST', '/api/depannages', $this->admin, [
            'lieudepannage' => 'Atelier',
            'car' => $this->carId,
            'typepanne' => $this->typepanneId,
            'details' => [['piece' => $this->pieceId, 'quantite' => 1]],
            'maindoeuvres' => [['montant' => 45000]],
        ]);

        $this->assertStatut(400);
    }

    #[Test]
    #[TestDox("La fiche expose le détail et la part de main d'œuvre")]
    public function laFicheExposeLeDetail(): void
    {
        $id = $this->ouvrir(2, [
            ['intervenant' => 'Garage Kouassi', 'prestation' => 'Démontage culasse', 'montant' => 30000],
            ['intervenant' => 'Soudeur du village', 'montant' => 15000],
        ]);

        $this->requete('GET', '/api/depannages/' . $id, $this->admin);
        $this->assertStatut(200);
        $fiche = $this->reponseJson();

        self::assertCount(2, $fiche['detailmaindoeuvres'] ?? []);
        /*
            `coutmaindoeuvre` est DÉRIVÉ à la lecture, jamais stocké : un écran peut annoncer « 69 000
            dont 45 000 de main d'œuvre » sans recalculer les pièces, et sans qu'un second champ persisté
            puisse dériver de `couttotal`.
        */
        self::assertSame(45000, $fiche['coutmaindoeuvre'] ?? null);
        self::assertSame(69000, (int) $fiche['couttotal']);
    }
}
