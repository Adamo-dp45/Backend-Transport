<?php

namespace App\DataFixtures;

use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Permission;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les rôles RBAC et leurs permissions.
 *
 * Une permission = (entité, action, entreprise) rattachée à un rôle, exactement ce
 * qu'interroge 'PermissionRepository::hasPermission()' depuis le 'PermissionVoter'.
 *
 * Deux points respectés ici, car le serveur les impose et un jeu de données incohérent
 * masquerait les régressions :
 *  - le nom d'entité doit être le NOM COURT de l'entité ('Ticket', 'Voyage'…), tel que le renvoie
 *    'EntityDiscoveryService' — une faute de frappe donne un rôle qui n'ouvre silencieusement rien ;
 *  - un rôle RATTACHÉ À UNE GARE ne peut porter que des permissions du périmètre de gare
 *    ('GareScopedEntities::ENTITIES'), sinon 'RoleProcessor' refuserait sa création via l'API.
 */
class RoleFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    private const TOUT = ['VOIR', 'CREER', 'MODIFIER', 'SUPPRIMER'];
    private const LECTURE = ['VOIR'];
    private const SAISIE = ['VOIR', 'CREER', 'MODIFIER'];
    /**
     * Billetterie d'un GUICHET : la saisie, plus le DÉSISTEMENT (remboursement / report).
     *
     * 'DESISTER' est volontairement séparé de 'MODIFIER' — corriger le nom d'un passager et lui
     * rembourser son billet n'engagent pas la même caisse. C'est ce qui distingue ici le guichetier
     * du commercial à bord : même travail de vente, mais la caisse du désistement reste à la gare.
     */
    private const BILLETTERIE_GUICHET = ['VOIR', 'CREER', 'MODIFIER', 'DESISTER'];

    public function getDependencies(): array
    {
        return [GareFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $iraId = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $sahelId = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class)->getId();

        // --------------------------------------------------------------- Rôles d'entreprise (IRA)
        $this->creerRole($manager, Refs::IRA, $iraId, 'guichetier', 'Guichetier', 'ENTREPRISE', null,
            'Vend les billets, enregistre bagages et courriers depuis sa gare.', [
                'Ticket' => self::BILLETTERIE_GUICHET,
                // Saisie seule sur Bagage/Courrier : déclarer une PERTE engage la compagnie, cela
                // remonte à l'encadrement de gare. C'est précisément ce que 'DECLARER_PERDU' permet
                // désormais de refuser sans retirer la saisie.
                'Bagage' => self::SAISIE,
                'Courrier' => self::SAISIE,
                'Reservation' => [...self::SAISIE, 'ANNULER'], // le comptoir annule une réservation
                'Client' => self::SAISIE,
                'Voyage' => self::LECTURE,
                'Passage' => self::LECTURE,
            ]);

        $this->creerRole($manager, Refs::IRA, $iraId, 'commercial', 'Commercial à bord', 'ENTREPRISE', null,
            'Vend depuis la position réelle du car et fait avancer le voyage.', [
                // Pas de 'DESISTER' : le remboursement est une opération de GARE, jamais du bord.
                // C'est la seule différence de billetterie avec le guichetier, et elle est voulue.
                'Ticket' => self::SAISIE,
                'Bagage' => self::SAISIE,
                'Client' => ['VOIR', 'CREER'],
                'Voyage' => self::LECTURE,
                // MODIFIER sur Passage : c'est l'action « le car repart » / avance de position.
                'Passage' => ['VOIR', 'MODIFIER'],
            ]);

        $this->creerRole($manager, Refs::IRA, $iraId, 'exploitation', 'Superviseur exploitation', 'ENTREPRISE', null,
            'Planifie les départs, affecte cars et équipages, suit la ponctualité.', [
                'Voyage' => self::TOUT,
                'Ligne' => ['VOIR', 'MODIFIER'],
                'Tarif' => ['VOIR', 'MODIFIER'],
                'Gare' => self::LECTURE,
                'Personnel' => self::LECTURE,
                'Car' => self::LECTURE,
                'Passage' => ['VOIR', 'MODIFIER'],
                'Alerte' => self::LECTURE,
                'Activite' => self::LECTURE,
            ]);

        $this->creerRole($manager, Refs::IRA, $iraId, 'magasinier', 'Magasinier', 'ENTREPRISE', null,
            'Tient le stock de pièces détachées et saisit les approvisionnements.', [
                'Piece' => [...self::SAISIE, 'AJUSTER'],             // l'inventaire est son métier
                'Approvisionnement' => [...self::SAISIE, 'ANNULER'], // il défait ses propres entrées
                'Fournisseur' => self::SAISIE,
                'Inventaire' => self::LECTURE,
                'Typepiece' => ['VOIR', 'CREER'],
                'Marquepiece' => ['VOIR', 'CREER'],
                'Model' => ['VOIR', 'CREER'],
            ]);

        /*
            Le COMPTABLE tient les charges DU SIÈGE (loyer, salaires de la direction) : elles ne sont
            imputées à aucune gare, personne d'autre ne peut donc les saisir. Les charges d'une gare,
            elles, sont tenues par son propre admin sans rôle dédié ('Depense' est dans
            'GareScopedEntities'). Il voit les fournisseurs, bénéficiaires possibles, sans pouvoir
            les modifier.
        */
        $this->creerRole($manager, Refs::IRA, $iraId, 'comptable', 'Comptable', 'ENTREPRISE', null,
            'Saisit et suit les charges d\'exploitation de la compagnie.', [
                'Depense' => self::SAISIE,
                'Typedepense' => ['VOIR', 'CREER'],
                'Fournisseur' => self::LECTURE,
                'Activite' => self::LECTURE,
            ]);

        $this->creerRole($manager, Refs::IRA, $iraId, 'flotte', 'Responsable flotte', 'ENTREPRISE', null,
            'Gère les véhicules et les dépannages.', [
                'Car' => self::SAISIE,
                'Depannage' => [...self::SAISIE, 'ANNULER'], // l'annulation restaure les pièces consommées
                'Marque' => ['VOIR', 'CREER'],
                'Modelvehicule' => ['VOIR', 'CREER'],
                'Typevehicule' => ['VOIR', 'CREER'],
                'Typepanne' => ['VOIR', 'CREER'],
                'Personnel' => self::LECTURE,
            ]);

        // ------------------------------------------------------------------ Rôle rattaché à UNE gare
        // Périmètre de gare : uniquement 'GareScopedEntities::ENTITIES'. Le rôle n'est proposable
        // qu'aux utilisateurs de la gare d'Abidjan.
        $this->creerRole($manager, Refs::IRA, $iraId, 'chef-abidjan', 'Encadrement gare d\'Abidjan', 'GARE',
            $this->getReference(Refs::gare(Refs::IRA, 'abidjan'), Gare::class),
            'Encadre les opérations et l\'équipe de la gare d\'Abidjan.', [
                'Voyage' => self::TOUT,
                'Ticket' => [...self::TOUT, 'DESISTER'],
                'Reservation' => [...self::TOUT, 'ANNULER'],
                'Courrier' => [...self::TOUT, 'DECLARER_PERDU'],
                'Bagage' => [...self::TOUT, 'DECLARER_PERDU'],
                'User' => self::SAISIE,
                'Role' => ['VOIR', 'CREER'],
            ]);

        // -------------------------------------------------------------------- Seconde compagnie
        // Même NOM de rôle qu'IRA : c'est volontaire, l'unicité est par entreprise.
        $this->creerRole($manager, Refs::SAHEL, $sahelId, 'guichetier', 'Guichetier', 'ENTREPRISE', null,
            'Vend les billets depuis sa gare.', [
                'Ticket' => self::BILLETTERIE_GUICHET,
                'Bagage' => self::SAISIE,
                'Voyage' => self::LECTURE,
                'Client' => self::SAISIE,
            ]);

        $manager->flush();
    }

    /**
     * @param array<string, list<string>> $permissions nom court d'entité => actions
     */
    private function creerRole(
        ObjectManager $manager,
        string $cie,
        int $identreprise,
        string $code,
        string $nom,
        string $typerole,
        ?Gare $gare,
        string $description,
        array $permissions
    ): void {
        $role = new Role();
        $role
            ->setName($nom)
            ->setDescription($description)
            ->setTyperole($typerole)
            ->setGare($gare)
            ->setIdentreprise($identreprise);

        foreach ($permissions as $entite => $actions) {
            foreach ($actions as $action) {
                $permission = (new Permission())
                    ->setEntity($entite)
                    ->setAction($action)
                    ->setIdentreprise($identreprise);
                // cascade: ['persist'] sur Role::permissions — l'ajout suffit à les faire écrire.
                $role->addPermission($permission);
            }
        }

        $manager->persist($role);
        $this->addReference(Refs::role($cie, $code), $role);
    }
}
