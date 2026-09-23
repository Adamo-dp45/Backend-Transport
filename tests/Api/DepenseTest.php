<?php

namespace App\Tests\Api;

use App\Entity\Depense;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Les DÉPENSES : ce qui sort de la caisse, et à qui on l'impute.
 *
 * Deux règles portent tout le module et se cassent en silence si personne ne les tient :
 *
 *  1. L'IMPUTATION. Une dépense appartient à une gare, ou au siège quand elle n'en a pas. Un agent
 *     ne doit pouvoir imputer ni à la gare voisine, ni au siège — où il ne reverrait même pas sa
 *     propre saisie, le périmètre de gare masquant les dépenses sans gare.
 *  2. LE BÉNÉFICE. Les dépenses sont un TROISIÈME poste, qui ne recouvre ni les dépannages ni les
 *     approvisionnements. Le test du bénéfice est la sentinelle de ce choix : il tombera le jour où
 *     quelqu'un dérivera une dépense d'un de ces deux modules.
 */
final class DepenseTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private User $agentBouake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);

        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->agentBouake = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            ['Depense' => ['VOIR', 'CREER', 'MODIFIER', 'SUPPRIMER']]
        );
    }

    #[Test]
    #[TestDox("Une dépense d'une autre compagnie n'est jamais visible")]
    public function perimetreEntreprise(): void
    {
        $autre = $this->scenario->reseau(['Daloa', 'Man']);
        $this->scenario->depense($autre->entreprise, 90000, $autre->gare('Daloa'));
        $this->scenario->depense($this->reseau->entreprise, 12000, $this->reseau->gare('Bouaké'));

        $this->requete('GET', '/api/depenses', $this->admin);

        $this->assertStatut(200);
        $montants = array_map(
            static fn (array $d): int => (int) $d['montant'],
            $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []
        );
        self::assertSame([12000], $montants, 'le périmètre entreprise ne laisse rien filtrer');
    }

    #[Test]
    #[TestDox("Un agent de gare ne voit que sa gare, et jamais les dépenses du siège")]
    public function perimetreGare(): void
    {
        $this->scenario->depense($this->reseau->entreprise, 12000, $this->reseau->gare('Bouaké'));
        $this->scenario->depense($this->reseau->entreprise, 34000, $this->reseau->gare('Abidjan'));
        // Sans gare = le SIÈGE : loyer, salaires de la direction.
        $this->scenario->depense($this->reseau->entreprise, 500000);

        $this->requete('GET', '/api/depenses', $this->agentBouake);

        $this->assertStatut(200);
        $montants = array_map(
            static fn (array $d): int => (int) $d['montant'],
            $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []
        );
        self::assertSame([12000], $montants, 'ni la gare voisine, ni les charges du siège');
    }

    #[Test]
    #[TestDox("L'administrateur voit tout, gares et siège")]
    public function adminVoitLeSiege(): void
    {
        $this->scenario->depense($this->reseau->entreprise, 12000, $this->reseau->gare('Bouaké'));
        $this->scenario->depense($this->reseau->entreprise, 500000);

        $this->requete('GET', '/api/depenses', $this->admin);

        $this->assertStatut(200);
        self::assertCount(2, $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []);
    }

    #[Test]
    #[TestDox("La dépense d'un agent de gare tombe sur SA gare, même s'il n'en dit rien")]
    public function impuationAutomatique(): void
    {
        $this->requete('POST', '/api/depenses', $this->agentBouake, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 25000,
            'typedepense' => '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId(),
            'libelle' => 'Gasoil',
        ]);

        $this->assertStatut(201);
        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->reponseJson()['gare']['id'] ?? null,
            'la gare de celui qui saisit'
        );
        self::assertSame('GARE', $this->reponseJson()['portee'] ?? null);
    }

    #[Test]
    #[TestDox("Un agent de gare ne peut imputer ni à une autre gare, ni au siège")]
    public function impuationBornee(): void
    {
        $type = '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId();

        // Une autre gare : refusé plutôt que réécrit en silence — l'agent doit savoir.
        $this->requete('POST', '/api/depenses', $this->agentBouake, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 25000,
            'typedepense' => $type,
            'gare' => '/api/gares/' . $this->reseau->gare('Abidjan')->getId(),
        ]);
        $this->assertStatut(403);

        /*
            Le siège demandé explicitement n'est pas refusé : il est RAMENÉ à la gare de l'agent,
            comme une gare absente. La garantie tenue est la même — un agent de gare ne crée jamais
            une charge de siège, qui partirait dans un périmètre où lui-même ne la reverrait pas —
            et distinguer « champ absent » de « champ à null » obligerait à lire la charge brute
            pour un refus qui n'apporterait rien.
        */
        $this->requete('POST', '/api/depenses', $this->agentBouake, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 25000,
            'typedepense' => $type,
            'gare' => null,
        ]);
        $this->assertStatut(201);
        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->reponseJson()['gare']['id'] ?? null,
            'ramenée à sa gare, jamais au siège'
        );
    }

    #[Test]
    #[TestDox("Une dépense ne peut pas pointer la gare d'une autre compagnie")]
    public function referencesEtrangeresRefusees(): void
    {
        $autre = $this->scenario->reseau(['Daloa', 'Man']);

        $this->requete('POST', '/api/depenses', $this->admin, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 25000,
            'typedepense' => '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId(),
            'gare' => '/api/gares/' . $autre->gare('Daloa')->getId(),
        ]);

        /*
            Le refus vient d'ApiPlatform lui-même : le périmètre entreprise s'applique aussi à la
            RÉSOLUTION d'un IRI, la gare étrangère est donc introuvable avant même le processor
            (400). La relecture faite par 'DepenseProcessor' est la ceinture de sécurité derrière
            cette bretelle — ce qui compte ici est qu'aucune dépense ne soit créée.
        */
        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());

        $this->requete('GET', '/api/depenses', $this->admin);
        self::assertCount(0, $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []);
    }

    #[Test]
    #[TestDox('Le bénéfice baisse exactement du montant de la dépense')]
    public function impactSurLeBenefice(): void
    {
        $this->requete('GET', '/api/stats/financiere', $this->admin);
        $this->assertStatut(200);
        $avant = (float) $this->reponseJson()['beneficeNet'];

        $this->scenario->depense($this->reseau->entreprise, 50000, $this->reseau->gare('Bouaké'));

        $this->requete('GET', '/api/stats/financiere', $this->admin);
        $this->assertStatut(200);
        $apres = $this->reponseJson();

        self::assertSame(50000.0, (float) $apres['coutDepenses'], 'le troisième poste est servi');
        self::assertSame(
            $avant - 50000,
            (float) $apres['beneficeNet'],
            'exactement une fois : aucune dépense n\'est dérivée d\'un dépannage ou d\'un approvisionnement'
        );
    }

    #[Test]
    #[TestDox("Le résultat d'une gare est sa recette moins SES dépenses, sans celles du siège")]
    public function resultatDeLaGare(): void
    {
        $chefBouake = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Bouaké'),
            roles: ['ROLE_ADMIN_GARE']
        );

        $this->scenario->depense($this->reseau->entreprise, 30000, $this->reseau->gare('Bouaké'));
        $this->scenario->depense($this->reseau->entreprise, 70000, $this->reseau->gare('Abidjan'));
        $this->scenario->depense($this->reseau->entreprise, 500000); // siège

        $this->requete('GET', '/api/gares/me/dashboard?periode=tout', $chefBouake);

        $this->assertStatut(200);
        $charge = $this->reponseJson();

        self::assertSame(30000, $charge['depenses']['montant'], 'ni Abidjan, ni le siège');
        self::assertSame(
            $charge['recetteTotale'] - 30000,
            $charge['resultat']['net'],
            'le résultat est bien la recette moins les dépenses imputées'
        );
        self::assertSame(
            'DEPENSES_GARE',
            $charge['resultat']['perimetre'],
            'le périmètre voyage avec le chiffre : dépannages et approvisionnements n\'y sont pas'
        );
    }

    #[Test]
    #[TestDox("Un admin de gare tient les charges de SA gare, sans permission explicite")]
    public function adminDeGareTientSesCharges(): void
    {
        /*
            Aucun appel à 'autoriser()' : c'est tout l'enjeu. 'Depense' figure dans
            'GareScopedEntities', le bypass du 'PermissionVoter' joue donc pour un ROLE_ADMIN_GARE —
            le chef de gare gère ses charges comme il gère ses ventes, sans qu'un administrateur
            d'entreprise ait à lui fabriquer un rôle.
        */
        $chefBouake = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Bouaké'),
            roles: ['ROLE_ADMIN_GARE']
        );

        $this->requete('POST', '/api/depenses', $chefBouake, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 40000,
            'typedepense' => '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId(),
            'libelle' => 'Gasoil',
        ]);

        $this->assertStatut(201);
        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->reponseJson()['gare']['id'] ?? null,
            'imputée à SA gare'
        );
        $id = (int) $this->reponseJson()['id'];

        // Il corrige son montant lui-même : c'est la contrepartie du refus de suppression.
        $this->requete('PATCH', '/api/depenses/' . $id, $chefBouake, ['montant' => 38000]);
        $this->assertStatut(200);
        self::assertSame(38000, $this->relire(Depense::class, $id)->getMontant());

        // Le bypass ne lui ouvre PAS la corbeille : elle est gardée par un rôle, pas par une permission.
        $this->requete('PATCH', '/api/depenses/' . $id . '/remove', $chefBouake, []);
        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox("L'admin de gare ne touche ni aux autres gares ni au siège")]
    public function adminDeGareResteDansSonPerimetre(): void
    {
        $chefBouake = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Bouaké'),
            roles: ['ROLE_ADMIN_GARE']
        );
        $type = '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId();

        $this->requete('POST', '/api/depenses', $chefBouake, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => 40000,
            'typedepense' => $type,
            'gare' => '/api/gares/' . $this->reseau->gare('Abidjan')->getId(),
        ]);
        $this->assertStatut(403);

        // Et il ne VOIT pas les charges du siège : le bypass porte sur la permission, jamais sur le
        // périmètre de lecture, qui reste tenu par 'GareScopeExtension'.
        $this->scenario->depense($this->reseau->entreprise, 500000);
        $this->requete('GET', '/api/depenses', $chefBouake);
        $this->assertStatut(200);
        self::assertCount(0, $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? []);
    }

    #[Test]
    #[TestDox("Seul l'administrateur met une dépense en corbeille")]
    public function corbeilleReserveeALAdmin(): void
    {
        $depense = $this->scenario->depense($this->reseau->entreprise, 12000, $this->reseau->gare('Bouaké'));

        // L'agent a pourtant la permission SUPPRIMER : une sortie d'argent est un document.
        $this->requete('PATCH', '/api/depenses/' . $depense->getId() . '/remove', $this->agentBouake, []);
        $this->assertStatut(403);

        $this->requete('PATCH', '/api/depenses/' . $depense->getId() . '/remove', $this->admin, []);
        $this->assertStatut(200);
        self::assertNotNull($this->relire(Depense::class, (int) $depense->getId())->getDeletedAt());
    }

    #[Test]
    #[TestDox('Une dépense en corbeille ne pèse plus sur le bénéfice')]
    public function corbeilleRetireDuBenefice(): void
    {
        $depense = $this->scenario->depense($this->reseau->entreprise, 50000, $this->reseau->gare('Bouaké'));

        $this->requete('PATCH', '/api/depenses/' . $depense->getId() . '/remove', $this->admin, []);
        $this->assertStatut(200);

        $this->requete('GET', '/api/stats/financiere', $this->admin);
        $this->assertStatut(200);
        self::assertSame(0.0, (float) $this->reponseJson()['coutDepenses']);
    }

    #[Test]
    #[TestDox("L'analyse des dépenses sépare les gares, le siège et ce qui n'est imputé à personne")]
    public function statistiquesDesDepenses(): void
    {
        $this->scenario->depense($this->reseau->entreprise, 30000, $this->reseau->gare('Bouaké'));
        $this->scenario->depense($this->reseau->entreprise, 70000, $this->reseau->gare('Abidjan'));
        $this->scenario->depense($this->reseau->entreprise, 500000); // siège

        $this->requete('GET', '/api/stats/depenses?debut=2000-01-01&fin=2100-01-01', $this->admin);

        $this->assertStatut(200);
        $charge = $this->reponseJson();

        self::assertSame(600000, $charge['total'], 'gares + siège');
        self::assertSame(100000, $charge['totalGares']);
        self::assertSame(500000, $charge['totalSiege']);
        self::assertSame(
            $charge['totalGares'] + $charge['totalSiege'],
            $charge['total'],
            'les deux totaux se recoupent : c\'est ce qui empêche le chiffre de mentir'
        );

        // Le siège apparaît dans la ventilation par gare, en ligne à part et sans recette.
        $siege = array_values(array_filter($charge['parGare'], static fn (array $g): bool => $g['gareId'] === null));
        self::assertCount(1, $siege);
        self::assertSame(500000, $siege[0]['depenses']);
        self::assertSame(0, $siege[0]['recette']);
    }

    #[Test]
    #[TestDox('Un montant négatif est refusé : il remonterait le bénéfice')]
    public function montantNegatifRefuse(): void
    {
        $this->requete('POST', '/api/depenses', $this->admin, [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => -50000,
            'typedepense' => '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId(),
        ]);

        $this->assertStatut(422);
    }
}
