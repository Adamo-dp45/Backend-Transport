<?php

namespace App\Tests\Api;

use App\Entity\Depense;
use App\Entity\MediaObject;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * UN FICHIER TÉLÉVERSÉ SE LIT AVEC LES DROITS DE LA FICHE QUI LE PORTE — ET SEULEMENT AINSI.
 *
 * Jusqu'au 28/09/2026, un `MediaObject` n'appartenait à personne et vivait sous `public/` : qui
 * devinait un identifiant obtenait l'URL (`GET /api/media_objects/{id}`, ouvert à tout compte de toute
 * compagnie), et l'URL donnait le fichier sans authentification. Un bulletin de salaire joint à une
 * dépense du siège était donc lisible par un guichetier d'une autre compagnie.
 *
 * Ces tests tiennent les QUATRE portes que la correction a fermées, chacune indépendamment :
 *  - la lecture DIRECTE d'un média (fermée) ;
 *  - l'accroche d'un média d'une AUTRE compagnie (l'IRI ne se résout plus) ;
 *  - l'accroche, dans la même compagnie, d'un document téléversé par QUELQU'UN D'AUTRE — le détour qui
 *    permettait à un agent de gare de lire une pièce du siège à travers sa propre dépense ;
 *  - le logo désigné par identifiant (`/api/me/entreprise`), qui échappait à la résolution d'IRI.
 * Et la seule porte ouverte : `GET /api/depenses/{id}/justificatif`, qui applique les droits de la dépense.
 */
final class MediaObjectTest extends ApiTestCase
{
    /** PNG 1×1 valide : le validateur lit les octets, pas l'extension. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private Reseau $reseau;

    private User $admin;

    private User $agentBouake;

    /** Une AUTRE gare, avec le droit de lire les dépenses. */
    private User $agentKorhogo;

    /** Un collègue de Bouaké SANS le droit de lire les dépenses. */
    private User $guichetierBouake;

    protected function setUp(): void
    {
        parent::setUp();

        /*
            Tous les comptes sont créés ICI, avant la première requête : le client de test vide
            l'EntityManager à chaque appel, et une gare du réseau, détachée, ne se rattache plus à un
            nouvel utilisateur (« A new entity was found through the relationship… »).
        */
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->agentBouake = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            ['Depense' => ['VOIR', 'CREER', 'MODIFIER']]
        );
        $this->agentKorhogo = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Korhogo')),
            ['Depense' => ['VOIR']]
        );
        $this->guichetierBouake = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké'));
    }

    protected function tearDown(): void
    {
        // Les fichiers téléversés par les tests ('when@test' de 'vich_uploader.yaml') : jamais sous 'public/'.
        $projet = static::getContainer()->getParameter('kernel.project_dir');
        parent::tearDown();
        (new Filesystem())->remove([$projet . '/var/test/images', $projet . '/var/test/documents', $projet . '/var/test/cache']);
    }

    private function televerser(User $user, string $nom, string $contenu, bool $prive): void
    {
        $chemin = tempnam(sys_get_temp_dir(), 'media');
        file_put_contents($chemin, $contenu);

        $this->client->request(
            'POST',
            '/api/media_objects',
            $prive ? ['prive' => '1'] : [],
            ['file' => new UploadedFile($chemin, $nom, null, null, true)],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jetonPour($user),
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );
    }

    /** @return array{id: int, iri: string} */
    private function televerserOk(User $user, string $nom, string $contenu, bool $prive): array
    {
        $this->televerser($user, $nom, $contenu, $prive);
        $this->assertStatut(201);
        $media = $this->reponseJson();

        return ['id' => (int) $media['id'], 'iri' => $media['@id']];
    }

    /** @return array<string, mixed> */
    private function corpsDepense(?string $justificatif, int $montant = 25000): array
    {
        return [
            'datedepense' => (new DateTimeImmutable())->format(DATE_ATOM),
            'montant' => $montant,
            'typedepense' => '/api/typedepenses/' . $this->scenario->typedepense($this->reseau->entreprise)->getId(),
            'libelle' => 'Facture pneus',
            'justificatif' => $justificatif,
        ];
    }

    #[Test]
    #[TestDox("Un document privé n'a pas d'URL, porte l'entreprise de l'auteur et n'est pas sous public/")]
    public function unDocumentPriveNAPasDUrl(): void
    {
        $this->televerser($this->agentBouake, 'bulletin.pdf', self::PDF, prive: true);
        $this->assertStatut(201);
        $json = $this->reponseJson();

        self::assertNull($json['contentUrl'], 'un document privé n\'a pas d\'URL');
        self::assertTrue($json['prive']);

        $media = $this->relire(MediaObject::class, (int) $json['id']);
        self::assertSame($this->reseau->entreprise->getId(), $media->getIdentreprise());
        self::assertSame($this->agentBouake->getId(), $media->getCreatedBy());
        self::assertNull($media->getFilePath(), 'rien dans le stockage public');

        $projet = static::getContainer()->getParameter('kernel.project_dir');
        self::assertFileExists($projet . '/var/test/documents/' . $media->getDocumentPath());

        /*
            LE NOM EST LA SECONDE BARRIÈRE : le dossier des documents est sous 'public/', interdit par un
            '.htaccess' que 'symfony serve' et Nginx ignorent. 128 bits aléatoires, et non 'uniqid()' qui
            se déduit de l'heure du dépôt ('NomAleatoireNamer').
        */
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.pdf$/', (string) $media->getDocumentPath());
    }

    #[Test]
    #[TestDox("Une image publique garde son URL ; un PDF, lui, est refusé en public")]
    public function unPdfNAtterritPasEnPublic(): void
    {
        $this->televerser($this->admin, 'logo.png', base64_decode(self::PNG), prive: false);
        $this->assertStatut(201);
        self::assertStringStartsWith('/images/media/', (string) $this->reponseJson()['contentUrl']);

        $this->televerser($this->admin, 'facture.pdf', self::PDF, prive: false);
        $this->assertStatut(400);
    }

    #[Test]
    #[TestDox("La lecture directe d'un média est fermée, même dans sa propre compagnie")]
    public function laLectureDirecteEstFermee(): void
    {
        $media = $this->televerserOk($this->admin, 'logo.png', base64_decode(self::PNG), prive: false);

        $this->requete('GET', $media['iri'], $this->admin);
        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Le média d'une autre compagnie ne peut pas être accroché à une dépense")]
    public function unMediaEtrangerNeSAccrochePas(): void
    {
        $autre = $this->scenario->reseau(['Daloa', 'Man']);
        $adminAutre = $this->scenario->utilisateur($autre->entreprise, roles: ['ROLE_ADMIN']);
        $etranger = $this->televerserOk($adminAutre, 'bulletin.pdf', self::PDF, prive: true);

        $this->requete('POST', '/api/depenses', $this->admin, $this->corpsDepense($etranger['iri']));

        // L'IRI ne se résout plus : 'EntrepriseScopeExtension' borne la résolution à la compagnie.
        $this->assertStatut(400);
    }

    #[Test]
    #[TestDox("Un agent ne peut pas accrocher un document téléversé par quelqu'un d'autre — même compagnie")]
    public function unDocumentDUnAutreNeSAccrochePas(): void
    {
        // La pièce du SIÈGE, que l'agent de Bouaké ne doit jamais lire.
        $bulletin = $this->televerserOk($this->admin, 'bulletin.pdf', self::PDF, prive: true);

        $this->requete('POST', '/api/depenses', $this->agentBouake, $this->corpsDepense($bulletin['iri']));

        /*
            Sans la garde de l'AUTEUR, l'IRI se résout (même compagnie), la dépense est créée à Bouaké,
            et l'agent télécharge le bulletin par SA fiche : le périmètre de gare ne protège que la
            dépense, pas le fichier qu'on y accroche.
        */
        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox("Une image publique ne peut pas servir de justificatif")]
    public function uneImagePubliqueNEstPasUnJustificatif(): void
    {
        $image = $this->televerserOk($this->agentBouake, 'recu.png', base64_decode(self::PNG), prive: false);

        $this->requete('POST', '/api/depenses', $this->agentBouake, $this->corpsDepense($image['iri']));
        $this->assertStatut(422);
    }

    #[Test]
    #[TestDox("Le justificatif se télécharge avec les droits de la dépense, et seulement avec eux")]
    public function leJustificatifSuitLesDroitsDeLaDepense(): void
    {
        $document = $this->televerserOk($this->agentBouake, 'facture.pdf', self::PDF, prive: true);
        $this->requete('POST', '/api/depenses', $this->agentBouake, $this->corpsDepense($document['iri']));
        $this->assertStatut(201);
        $id = (int) $this->reponseJson()['id'];
        $justificatif = $this->reponseJson()['justificatif'] ?? [];
        // 'array_key_exists' et non '??' : ce dernier confond une clé NULLE et une clé ABSENTE.
        self::assertArrayHasKey('contentUrl', $justificatif);
        self::assertNull($justificatif['contentUrl'], 'aucune URL sur la fiche');
        self::assertTrue($justificatif['prive'] ?? false);

        // L'auteur, et l'administrateur : le fichier, octet pour octet, jamais mis en cache.
        foreach ([$this->agentBouake, $this->admin] as $lecteur) {
            $this->requete('GET', '/api/depenses/' . $id . '/justificatif', $lecteur);
            $this->assertStatut(200);
            $reponse = $this->client->getResponse();
            self::assertSame(self::PDF, file_get_contents($reponse->getFile()->getPathname()));
            self::assertSame('application/pdf', $reponse->headers->get('Content-Type'));
            self::assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));
            self::assertStringContainsString('no-store', (string) $reponse->headers->get('Cache-Control'));
        }

        // L'agent d'une AUTRE gare, avec le droit de lire les dépenses : la dépense lui est introuvable.
        $this->requete('GET', '/api/depenses/' . $id . '/justificatif', $this->agentKorhogo);
        $this->assertStatut(404);

        // Un collègue de Bouaké SANS le droit de lire les dépenses.
        $this->requete('GET', '/api/depenses/' . $id . '/justificatif', $this->guichetierBouake);
        $this->assertStatut(403);

        // Sans jeton.
        $this->requete('GET', '/api/depenses/' . $id . '/justificatif');
        $this->assertStatut(401);
    }

    #[Test]
    #[TestDox("Corriger la dépense d'un agent conserve son justificatif, que le correcteur n'a pas téléversé")]
    public function unJustificatifInchangeResteAccroche(): void
    {
        $document = $this->televerserOk($this->agentBouake, 'facture.pdf', self::PDF, prive: true);
        $this->requete('POST', '/api/depenses', $this->agentBouake, $this->corpsDepense($document['iri']));
        $this->assertStatut(201);
        $id = (int) $this->reponseJson()['id'];

        // Le formulaire de modification renvoie TOUJOURS le justificatif existant.
        $this->requete('PATCH', '/api/depenses/' . $id, $this->admin, [
            'montant' => 30000,
            'justificatif' => $document['iri'],
        ]);
        $this->assertStatut(200);

        $depense = $this->relire(Depense::class, $id);
        self::assertSame(30000, $depense->getMontant());
        self::assertSame($document['id'], $depense->getJustificatif()?->getId());
    }

    #[Test]
    #[TestDox("Le logo désigné par identifiant doit appartenir à la compagnie")]
    public function unLogoEtrangerEstRefuse(): void
    {
        $autre = $this->scenario->reseau(['Daloa', 'Man']);
        $adminAutre = $this->scenario->utilisateur($autre->entreprise, roles: ['ROLE_ADMIN']);
        $etranger = $this->televerserOk($adminAutre, 'logo.png', base64_decode(self::PNG), prive: false);
        $sien = $this->televerserOk($this->admin, 'logo.png', base64_decode(self::PNG), prive: false);

        $corps = static fn (int $image): array => [
            'libelle' => 'Compagnie de test',
            'contact1' => '0700000000',
            'image' => $image,
        ];

        // L'identifiant échappe à la résolution d'IRI : c'est le processor qui doit borner.
        $this->requete('PATCH', '/api/me/entreprise', $this->admin, $corps($etranger['id']));
        $this->assertStatut(404);

        $this->requete('PATCH', '/api/me/entreprise', $this->admin, $corps($sien['id']));
        $this->assertStatut(200);
    }
}
