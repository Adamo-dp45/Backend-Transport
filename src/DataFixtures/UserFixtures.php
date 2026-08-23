<?php

namespace App\DataFixtures;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Les comptes utilisateurs — un par SITUATION de sécurité, pas un par personne.
 *
 * Le contrôle d'accès se joue sur trois axes qui se cumulent (rôle Symfony, rattachement à une gare,
 * permissions RBAC). Le jeu couvre donc chaque combinaison qui change réellement le comportement :
 *
 *  | compte                | rôle Symfony     | gare     | ce qu'il permet de vérifier                  |
 *  |-----------------------|------------------|----------|----------------------------------------------|
 *  | super@…               | ROLE_SUPER_ADMIN | —        | hors périmètre entreprise : corbeille, maintenance |
 *  | admin@ira…            | ROLE_ADMIN       | —        | fondateur, dashboard global avec finances    |
 *  | chef.abidjan@ira…     | ROLE_ADMIN_GARE  | Abidjan  | bypass LIMITÉ aux entités de gare            |
 *  | agent.abidjan@ira…    | —                | Abidjan  | périmètre gare + permissions explicites      |
 *  | agent.bouake@ira…     | —                | Bouaké   | gare INTERMÉDIAIRE : réception, revente      |
 *  | commercial@ira…       | —                | Abidjan  | vente à bord (app mobile commerciale)        |
 *  | exploitation@ira…     | —                | —        | central sans gare : voit tout, sans finances |
 *  | suspendu@ira…         | —                | Abidjan  | 'UserChecker' + 'JWTSubscriber'              |
 *
 * Le SUPER ADMIN n'a délibérément AUCUNE entreprise : plusieurs services en dépendent (la corbeille
 * ne doit jamais lire l'entreprise de l'acteur), et un super admin rattaché à une compagnie
 * masquerait ce piège.
 */
class UserFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        // ------------------------------------------------------------------------- Super admin
        $super = new User();
        $super
            ->setEmail('super@itransport.ci')
            ->setNom('Plateforme')
            ->setPrenom('Super Admin')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setStatut(ReferenceStatus::ACTIF->value)
            ->setIsFounder(false);
        $super->setPassword($this->hasher->hashPassword($super, Refs::MOTDEPASSE));
        $manager->persist($super);
        $this->addReference(Refs::user('plateforme', 'super'), $super);

        // ------------------------------------------------------------------------ IRA Transport
        $ira = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class);

        $this->creerUser($manager, Refs::IRA, $ira, 'admin', 'admin@ira-transport.ci', 'Kouassi', 'Jean-Marc',
            ['ROLE_ADMIN'], null, [], ReferenceStatus::ACTIF, true);

        $this->creerUser($manager, Refs::IRA, $ira, 'direction', 'direction@ira-transport.ci', 'Yao', 'Christelle',
            ['ROLE_ADMIN'], null, []);

        $this->creerUser($manager, Refs::IRA, $ira, 'chef-abidjan', 'chef.abidjan@ira-transport.ci', 'Koffi', 'Aristide',
            ['ROLE_ADMIN_GARE'], 'abidjan', ['chef-abidjan']);

        $this->creerUser($manager, Refs::IRA, $ira, 'chef-korhogo', 'chef.korhogo@ira-transport.ci', 'Silué', 'Adama',
            ['ROLE_ADMIN_GARE'], 'korhogo', []);

        $this->creerUser($manager, Refs::IRA, $ira, 'agent-abidjan', 'agent.abidjan@ira-transport.ci', 'Assi', 'Bernadette',
            [], 'abidjan', ['guichetier']);

        $this->creerUser($manager, Refs::IRA, $ira, 'agent-bouake', 'agent.bouake@ira-transport.ci', 'Traoré', 'Moussa',
            [], 'bouake', ['guichetier']);

        $this->creerUser($manager, Refs::IRA, $ira, 'agent-yamoussoukro', 'agent.yamoussoukro@ira-transport.ci', 'N\'Guessan', 'Marie',
            [], 'yamoussoukro', ['guichetier']);

        // Le commercial est RATTACHÉ à une gare : sa recette à bord y revient (et non à la gare de montée).
        $this->creerUser($manager, Refs::IRA, $ira, 'commercial', 'commercial@ira-transport.ci', 'Diomandé', 'Karim',
            [], 'abidjan', ['commercial']);

        // Utilisateurs CENTRAUX (sans gare) : aucun filtre de gare, mais pas admin pour autant.
        $this->creerUser($manager, Refs::IRA, $ira, 'exploitation', 'exploitation@ira-transport.ci', 'Bamba', 'Séverin',
            [], null, ['exploitation']);

        $this->creerUser($manager, Refs::IRA, $ira, 'magasin', 'magasin@ira-transport.ci', 'Konan', 'Éric',
            [], null, ['magasinier']);

        $this->creerUser($manager, Refs::IRA, $ira, 'flotte', 'flotte@ira-transport.ci', 'Ouattara', 'Lassina',
            [], null, ['flotte']);

        // Compte SUSPENDU : la connexion doit échouer ('UserChecker'), et un jeton déjà émis doit
        // être rejeté à la requête suivante ('JWTSubscriber').
        $this->creerUser($manager, Refs::IRA, $ira, 'suspendu', 'suspendu@ira-transport.ci', 'Doumbia', 'Awa',
            [], 'abidjan', ['guichetier'], ReferenceStatus::SUSPENDU);

        // ------------------------------------------------------------------------ Sahel Voyages
        $sahel = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class);

        $this->creerUser($manager, Refs::SAHEL, $sahel, 'admin', 'admin@sahel-voyages.ci', 'Ouattara', 'Ibrahim',
            ['ROLE_ADMIN'], null, [], ReferenceStatus::ACTIF, true);

        $this->creerUser($manager, Refs::SAHEL, $sahel, 'agent', 'agent@sahel-voyages.ci', 'Coulibaly', 'Salif',
            [], 'ferkessedougou', ['guichetier']);

        $manager->flush();
    }

    /**
     * @param list<string> $rolesSymfony rôles techniques stockés dans la colonne JSON 'roles'
     * @param list<string> $codesRoles   codes des rôles RBAC ('Role') à attribuer
     */
    private function creerUser(
        ObjectManager $manager,
        string $cie,
        Entreprise $entreprise,
        string $code,
        string $email,
        string $nom,
        string $prenom,
        array $rolesSymfony,
        ?string $codeGare,
        array $codesRoles,
        ReferenceStatus $statut = ReferenceStatus::ACTIF,
        bool $fondateur = false
    ): void {
        $user = new User();
        $user
            ->setEmail($email)
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setRoles($rolesSymfony)
            ->setEntreprise($entreprise)
            ->setStatut($statut->value)
            ->setIsFounder($fondateur);

        if ($codeGare !== null) {
            $user->setGare($this->getReference(Refs::gare($cie, $codeGare), Gare::class));
        }

        $user->setPassword($this->hasher->hashPassword($user, Refs::MOTDEPASSE));

        foreach ($codesRoles as $codeRole) {
            $userRole = (new UserRole())
                ->setRole($this->getReference(Refs::role($cie, $codeRole), Role::class))
                ->setIdentreprise($entreprise->getId());
            // cascade: ['persist'] sur User::userRoles — l'ajout suffit à écrire la liaison.
            $user->addUserRole($userRole);
        }

        $manager->persist($user);
        $this->addReference(Refs::user($cie, $code), $user);
    }
}
