<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LIMITE DE DÉBIT SUR « MOT DE PASSE OUBLIÉ » (D1 de la feuille de route).
 *
 * La route était ouverte sans aucune limite. Elle laissait deux abus : NOYER la boîte d'une
 * personne précise sous les courriels de réinitialisation, et BALAYER des milliers d'adresses pour
 * deviner lesquelles existent. Le 204 silencieux empêchait de lire la réponse ; rien n'empêchait
 * d'essayer en boucle.
 *
 * !! LA SENTINELLE QUI COMPTE EST CELLE DE L'ÉNUMÉRATION. Un limiteur par e-mail consommé APRÈS la
 * recherche du compte répondrait 429 sur une adresse connue et 204 sur une inconnue : la différence
 * que le 204 sert justement à effacer, rendue par le code de statut. C'est une correction qui peut
 * rouvrir le trou qu'elle prétend fermer, et seul ce test le verrait.
 */
final class MotDePasseOublieTest extends ApiTestCase
{
    private const LIMITE_EMAIL = 3;

    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké'], [null, 240]);
    }

    #[Test]
    #[TestDox('Au-delà de trois demandes pour la même adresse, la suivante est refusée')]
    public function tropDeDemandesPourUneAdresse(): void
    {
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        for ($i = 0; $i < self::LIMITE_EMAIL; $i++) {
            $this->demander($agent->getEmail());
            self::assertSame(
                201,
                $this->client->getResponse()->getStatusCode(),
                'les demandes légitimes passent : qui a vraiment oublié son mot de passe réessaie'
            );
        }

        $this->demander($agent->getEmail());
        $this->assertStatut(429);
    }

    #[Test]
    #[TestDox("Une adresse INCONNUE est limitée elle aussi : le 429 ne révèle aucun compte")]
    public function aucuneEnumerationParLeCodeDeStatut(): void
    {
        $inconnue = 'personne.inconnue@example.invalid';

        for ($i = 0; $i < self::LIMITE_EMAIL; $i++) {
            $this->demander($inconnue);
            self::assertSame(
                204,
                $this->client->getResponse()->getStatusCode(),
                'une adresse inconnue répond 204 silencieux, sans dire qu\'elle est inconnue'
            );
        }

        $this->demander($inconnue);
        self::assertSame(
            429,
            $this->client->getResponse()->getStatusCode(),
            "le compteur tourne pour TOUTE adresse saisie. S'il ne tournait que pour les comptes "
            . "existants, comparer 429 et 204 suffirait à dresser la liste des e-mails valides"
        );
    }

    #[Test]
    #[TestDox('La casse de l’adresse ne contourne pas la limite')]
    public function laCasseNeContournePas(): void
    {
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $email = (string) $agent->getEmail();

        for ($i = 0; $i < self::LIMITE_EMAIL; $i++) {
            $this->demander($email);
        }

        $this->demander(mb_strtoupper($email));
        self::assertSame(
            429,
            $this->client->getResponse()->getStatusCode(),
            '« Agent@… » et « agent@… » désignent la même boîte : les compter séparément offrirait '
            . 'autant de fois la limite qu\'il y a de façons d\'écrire une adresse'
        );
    }

    #[Test]
    #[TestDox("La réinitialisation est limitée elle aussi, sur la ressource et non sur le secret")]
    public function reinitialisationLimitee(): void
    {
        // Le jeton ne se devine pas (256 bits) : ce qu'on protège, c'est le coût du hachage.
        for ($i = 0; $i < 10; $i++) {
            $this->requete('POST', '/api/reset', null, [
                'token' => 'jeton-inexistant-' . $i,
                'password' => 'NouveauMotDePasse1!',
            ]);
            self::assertSame(400, $this->client->getResponse()->getStatusCode(), 'jeton invalide');
        }

        $this->requete('POST', '/api/reset', null, [
            'token' => 'jeton-inexistant-11',
            'password' => 'NouveauMotDePasse1!',
        ]);
        $this->assertStatut(429);
    }

    private function demander(string $email): void
    {
        $this->requete('POST', '/api/forgot', null, [
            'email' => $email,
            'frontResetUrl' => 'http://localhost:8002/reset',
        ]);
    }
}
