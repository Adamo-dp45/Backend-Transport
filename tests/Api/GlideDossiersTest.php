<?php

namespace App\Tests\Api;

use App\Entity\MediaObject;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * GLIDE NE LIT QUE LES IMAGES — JAMAIS LES JUSTIFICATIFS.
 *
 * Sa source est tout `public/`, et les justificatifs de dépense y vivent (`public/documents`, interdit au
 * serveur web par un `.htaccess`). Mais le `.htaccess` ne protège que les fichiers servis en STATIQUE :
 * `/media/documents/<nom>.png` ferait lire le justificatif par PHP, par-dessus l'interdiction. D'où la
 * liste des dossiers de `ImageController` (`images/media`, `images/users`).
 */
final class GlideDossiersTest extends ApiTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** Le format des tableaux du frontend : affichés en 32 px, demandés en 64 pour les écrans haute densité. */
    private const FORMAT_TABLEAU = '?w=64&h=64&fit=crop&fm=jpg&q=75';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    protected function tearDown(): void
    {
        $projet = static::getContainer()->getParameter('kernel.project_dir');
        parent::tearDown();
        (new Filesystem())->remove([$projet . '/var/test/images', $projet . '/var/test/documents', $projet . '/var/test/cache']);
    }

    private function televerserImage(bool $prive): MediaObject
    {
        $chemin = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($chemin, base64_decode(self::PNG));

        $this->client->request(
            'POST',
            '/api/media_objects',
            $prive ? ['prive' => '1'] : [],
            ['file' => new UploadedFile($chemin, 'photo.png', null, null, true)],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->jetonPour($this->admin),
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );
        $this->assertStatut(201);

        return $this->relire(MediaObject::class, (int) $this->reponseJson()['id']);
    }

    #[Test]
    #[TestDox("Une image publique est servie par Glide, au format léger des tableaux")]
    public function uneImagePubliqueEstServie(): void
    {
        $image = $this->televerserImage(prive: false);

        $this->client->request('GET', '/media/images/media/' . $image->getFilePath() . self::FORMAT_TABLEAU);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('image/jpeg', $this->client->getResponse()->headers->get('Content-Type'));
    }

    #[Test]
    #[TestDox("Un justificatif en image n'est JAMAIS servi par Glide, même en connaissant son nom")]
    public function unJustificatifNEstPasServi(): void
    {
        /*
            Un justificatif en IMAGE, réellement présent sous la source de Glide : sans la liste des
            dossiers, Glide le redimensionnerait et le servirait (vérifié : 200). Un fichier inexistant
            aurait répondu 404 avec ou sans la garde — le test n'aurait rien prouvé.
        */
        $document = $this->televerserImage(prive: true);

        $this->client->request('GET', '/media/documents/' . $document->getDocumentPath() . self::FORMAT_TABLEAU);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }
}
