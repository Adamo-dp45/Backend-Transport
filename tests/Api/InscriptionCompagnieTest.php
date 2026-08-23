<?php

namespace App\Tests\Api;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'enregistrement d'une compagnie, désormais réservé au SUPER ADMINISTRATEUR.
 *
 * Cette route était publique : n'importe qui pouvait créer une entreprise et s'y attribuer
 * 'ROLE_ADMIN' — donc obtenir un périmètre complet sur la plateforme. C'est le seul endroit du
 * système où un compte administrateur naît par appel HTTP ; le verrou mérite d'être épinglé, car
 * une expression 'security:' se retire par mégarde sans que rien d'autre ne casse.
 */
final class InscriptionCompagnieTest extends ApiTestCase
{
    #[Test]
    #[TestDox("Un anonyme ne peut pas enregistrer de compagnie")]
    public function anonymeRefuse(): void
    {
        $this->requete('POST', '/api/register', corps: $this->payload());

        $this->assertStatut(401);
        self::assertNull($this->trouverEntreprise('Compagnie de test'), 'rien ne doit avoir été créé');
    }

    #[Test]
    #[TestDox("L'administrateur d'une compagnie ne peut pas en créer une autre")]
    public function adminEntrepriseRefuse(): void
    {
        $admin = $this->scenario->utilisateur($this->scenario->entreprise(), roles: ['ROLE_ADMIN']);

        $this->requete('POST', '/api/register', $admin, $this->payload());

        // 403 et non 401 : il est authentifié, mais ouvrir des comptes relève de l'exploitant.
        $this->assertStatut(403);
        self::assertNull($this->trouverEntreprise('Compagnie de test'));
    }

    #[Test]
    #[TestDox("Un agent de gare ne peut pas enregistrer de compagnie")]
    public function agentRefuse(): void
    {
        $agent = $this->scenario->utilisateur($this->scenario->entreprise());

        $this->requete('POST', '/api/register', $agent, $this->payload());

        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox("Le super administrateur enregistre la compagnie et son fondateur")]
    public function superAdminAutorise(): void
    {
        $this->requete('POST', '/api/register', $this->superAdmin(), $this->payload());

        $this->assertStatut(201);

        $entreprise = $this->trouverEntreprise('Compagnie de test');
        self::assertNotNull($entreprise, 'la compagnie doit exister');
        self::assertSame('compagnie-de-test', $entreprise->getSlug(), 'le slug public est dérivé du libellé');

        $fondateur = $this->em->getRepository(User::class)->findOneBy(['email' => 'fondateur@compagnie-test.ci']);
        self::assertNotNull($fondateur);
        self::assertContains('ROLE_ADMIN', $fondateur->getRoles(), 'le fondateur dirige sa compagnie');
        self::assertTrue($fondateur->isFounder());
        self::assertSame(
            $entreprise->getId(),
            $fondateur->getEntreprise()?->getId(),
            'le fondateur est rattaché à la compagnie qu\'il vient de recevoir'
        );
    }

    #[Test]
    #[TestDox("Le super administrateur n'appartient à aucune compagnie : cela ne gêne pas la création")]
    public function superAdminSansEntreprise(): void
    {
        $super = $this->superAdmin();
        self::assertNull($super->getEntreprise(), 'préalable du test : le super admin est hors périmètre');

        $this->requete('POST', '/api/register', $super, $this->payload());

        // Le processor ne doit jamais lire l'entreprise de l'ACTEUR — elle n'existe pas.
        $this->assertStatut(201);
    }

    #[Test]
    #[TestDox("Une adresse déjà utilisée est refusée, sans créer d'entreprise orpheline")]
    public function emailDejaUtilise(): void
    {
        $super = $this->superAdmin();
        $this->requete('POST', '/api/register', $super, $this->payload());
        $this->assertStatut(201);

        $this->requete('POST', '/api/register', $super, $this->payload(libelle: 'Une autre compagnie'));

        $this->assertStatut(409);
        self::assertNull(
            $this->trouverEntreprise('Une autre compagnie'),
            'le conflit est détecté AVANT la transaction : aucune entreprise sans administrateur'
        );
    }

    #[Test]
    #[TestDox("Une charge incomplète est rejetée par la validation")]
    public function chargeInvalide(): void
    {
        $this->requete('POST', '/api/register', $this->superAdmin(), ['email' => 'pas-un-email']);

        $this->assertStatut(422);
    }

    /** Super administrateur : rôle de plateforme, sans entreprise ni gare. */
    private function superAdmin(): User
    {
        $super = $this->scenario->utilisateur($this->scenario->entreprise(), roles: ['ROLE_SUPER_ADMIN']);
        $super->setEntreprise(null);
        $this->em->flush();

        return $super;
    }

    /** @return array<string, mixed> */
    private function payload(string $libelle = 'Compagnie de test'): array
    {
        return [
            'email' => 'fondateur@compagnie-test.ci',
            'nom' => 'Kouassi',
            'prenom' => 'Jean-Marc',
            'password' => 'Password123!',
            'libelle' => $libelle,
            'contact1' => '+225 27 20 30 40 50',
            'anneecreation' => '2024-01-15',
        ];
    }

    private function trouverEntreprise(string $libelle): ?Entreprise
    {
        return $this->em->getRepository(Entreprise::class)->findOneBy(['libelle' => $libelle]);
    }
}
