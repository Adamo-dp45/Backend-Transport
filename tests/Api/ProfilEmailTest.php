<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UN AGENT NE CHANGE PAS SON PROPRE E-MAIL (décision du 06/10/2026).
 *
 * L'e-mail est l'IDENTIFIANT DE CONNEXION et le canal de réinitialisation du mot de passe. En
 * self-service il ouvre deux trous : l'agent qui se trompe s'exclut lui-même, et celui qui part peut
 * rediriger la récupération de son compte vers une adresse personnelle — gardant la main sur un
 * compte qu'on croit avoir repris. Il reste modifiable par l'ADMINISTRATEUR.
 *
 * !! CE QUI SE TESTE ICI EST UN SILENCE, et c'est pour cela qu'il faut un test. La garde n'est pas
 * un refus : '/api/me' ACCEPTE l'e-mail et répond 200 — il ne le recopie simplement jamais sur
 * l'utilisateur ('UserProfileProcessor'). Un 200 parfaitement crédible, qui ne dit rien de ce qu'il a
 * fait. Rétablir un jour le 'setEmail()' « pour uniformiser avec nom et prénom » rouvrirait le trou
 * sans qu'aucun code de statut ne change, et seule la RELECTURE en base le verrait.
 *
 * !! LA GARDE N'EST PAS DANS LE GROUPE DE SÉRIALISATION, et ce n'est pas faute d'avoir essayé :
 * retirer 'write:User:profil' de l'e-mail le rend NULL sur le User « formulaire » que '/api/me'
 * dénormalise, et la validation refuse alors TOUT enregistrement de profil (422). Le champ doit
 * rester accepté ; c'est son application qui est fermée.
 */
final class ProfilEmailTest extends ApiTestCase
{
    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
    }

    #[Test]
    #[TestDox("Un agent ne peut pas changer son e-mail par son profil")]
    public function lAgentNeChangePasSonEmail(): void
    {
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $origine = (string) $agent->getEmail();

        /*
            NOM ET PRÉNOM ENSEMBLE : '/api/me' se comporte en REMPLACEMENT et non en fusion — un champ
            du groupe qu'on n'envoie pas est remis à vide, et la validation répond 422. Le formulaire
            de profil envoie bien les deux ; le test doit faire pareil, sinon il échoue pour une
            raison qui n'a rien à voir avec l'e-mail.
        */
        $this->requete('PATCH', '/api/me', $agent, [
            'email' => 'adresse.personnelle@example.invalid',
            'nom' => 'Nouveau nom',
            'prenom' => (string) $agent->getPrenom(),
        ]);
        $this->assertStatut(200);

        $relu = $this->relire(User::class, (int) $agent->getId());
        self::assertSame(
            $origine,
            $relu->getEmail(),
            "l'e-mail doit être inchangé. La garde est dans 'UserProfileProcessor', qui ne le recopie "
            . "pas sur l'utilisateur connecté : l'API l'ACCEPTE et l'IGNORE, en répondant 200. Seule la "
            . "relecture en base le prouve — aucun code de statut ne le signale"
        );
        self::assertSame(
            'Nouveau nom',
            $relu->getNom(),
            "le reste du profil reste modifiable : on ferme l'e-mail, pas le self-service"
        );
    }

    #[Test]
    #[TestDox("L'administrateur, lui, peut changer l'e-mail d'un agent")]
    public function lAdminPeutChangerLEmail(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        $this->requete('PATCH', '/api/users/' . $agent->getId(), $admin, [
            'email' => 'nouvelle.adresse@example.test',
        ]);
        $this->assertStatut(200);

        self::assertSame(
            'nouvelle.adresse@example.test',
            $this->relire(User::class, (int) $agent->getId())->getEmail(),
            "fermer le self-service ne doit pas fermer la gestion : un agent qui perd son adresse "
            . "doit pouvoir être dépanné, sinon la règle se contourne en recréant des comptes"
        );
    }
}
