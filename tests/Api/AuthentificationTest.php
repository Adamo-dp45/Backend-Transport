<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReferenceStatus;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le parcours d'authentification et ses refus.
 *
 * Le point le plus délicat est la SUSPENSION : 'UserChecker' bloque la connexion, mais il ne dit
 * rien d'un utilisateur suspendu qui détient déjà un jeton valide. C'est 'JWTSubscriber' qui ferme
 * cette porte, à chaque requête authentifiée. Les deux moitiés sont testées séparément — ne
 * couvrir que la connexion laisserait un compte révoqué actif jusqu'à l'expiration de son jeton.
 */
final class AuthentificationTest extends ApiTestCase
{
    #[Test]
    #[TestDox('Une connexion valide renvoie un jeton et un jeton de rafraîchissement')]
    public function connexionValide(): void
    {
        $entreprise = $this->scenario->entreprise();
        $user = $this->scenario->utilisateur($entreprise);

        $this->connexion($user->getEmail(), 'Password123!');

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertArrayHasKey('token', $reponse);
        self::assertArrayHasKey('refresh_token', $reponse, 'sans lui, la session ne peut pas être prolongée');
    }

    #[Test]
    #[TestDox('Un mot de passe erroné est refusé')]
    public function motDePasseErrone(): void
    {
        $user = $this->scenario->utilisateur($this->scenario->entreprise());

        $this->connexion($user->getEmail(), 'mauvais-mot-de-passe');

        $this->assertStatut(401);
    }

    #[Test]
    #[TestDox("Un compte suspendu ne peut plus se connecter")]
    public function compteSuspenduRefuseALaConnexion(): void
    {
        $user = $this->scenario->utilisateur(
            $this->scenario->entreprise(),
            statut: ReferenceStatus::SUSPENDU
        );

        $this->connexion($user->getEmail(), 'Password123!');

        $this->assertStatut(401);
    }

    #[Test]
    #[TestDox("Un compte suspendu APRÈS émission de son jeton est rejeté dès la requête suivante")]
    public function compteSuspenduAvecJetonDejaEmis(): void
    {
        $user = $this->scenario->utilisateur($this->scenario->entreprise());

        // Jeton obtenu alors que le compte était encore actif.
        $jeton = $this->jetonPour($user);

        $user->setStatut(ReferenceStatus::SUSPENDU->value);
        $this->em->flush();

        $this->client->request('GET', '/api/me', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $jeton,
        ]);

        // 403 et non 401 : le jeton est authentique, c'est le COMPTE qui n'est plus autorisé
        // ('JWTSubscriber' lève une AccessDeniedHttpException). Sans cette garde, un agent révoqué
        // continuerait d'encaisser jusqu'à l'expiration de son jeton.
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox('Une requête sans jeton est refusée')]
    public function requeteAnonymeRefusee(): void
    {
        $this->requete('GET', '/api/me');

        $this->assertStatut(401);
    }

    #[Test]
    #[TestDox("Un jeton falsifié est refusé")]
    public function jetonFalsifieRefuse(): void
    {
        $this->client->request('GET', '/api/me', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_AUTHORIZATION' => 'Bearer pas.un.jeton',
        ]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox('Le profil renvoie bien l\'utilisateur porteur du jeton')]
    public function profilDeLUtilisateurConnecte(): void
    {
        $entreprise = $this->scenario->entreprise('Compagnie du profil');
        $user = $this->scenario->utilisateur($entreprise);

        $this->requete('GET', '/api/me', $user);

        $this->assertStatut(200);
        self::assertSame($user->getEmail(), $this->reponseJson()['email'] ?? null);
    }

    #[Test]
    #[TestDox('Le jeton de rafraîchissement permet de prolonger la session')]
    public function rafraichissementDeSession(): void
    {
        $user = $this->scenario->utilisateur($this->scenario->entreprise());

        $this->connexion($user->getEmail(), 'Password123!');
        $this->assertStatut(200);
        $refresh = $this->reponseJson()['refresh_token'];

        $this->client->request('POST', '/api/token/refresh', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['refresh_token' => $refresh]));

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertArrayHasKey('token', $this->reponseJson());
    }

    #[Test]
    #[TestDox("Après quatre échecs, la connexion est bloquée avant même de vérifier le mot de passe")]
    public function forceBruteBloquee(): void
    {
        $user = $this->scenario->utilisateur($this->scenario->entreprise());

        // 'login_throttling' : 4 tentatives par fenêtre de 15 minutes.
        for ($i = 0; $i < 4; $i++) {
            $this->connexion($user->getEmail(), 'mauvais-mot-de-passe');
            $this->assertStatut(401);
        }

        // La 5e est refusée pour cause de blocage — et le BON mot de passe ne passe pas non plus :
        // le limiteur agit avant la vérification.
        $this->connexion($user->getEmail(), 'Password123!');

        $this->assertStatut(429);
        self::assertStringContainsString(
            'Trop de tentatives',
            (string) ($this->reponseJson()['message'] ?? ''),
            'le message métier de LoginFailureHandler doit remonter, pas un 401 générique'
        );
    }

    private function connexion(string $email, string $motDePasse): void
    {
        $this->client->request('POST', '/api/login_check', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['username' => $email, 'password' => $motDePasse]));
    }
}
