<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Role;
use App\Entity\User;
use App\Security\GareScopedEntities;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RoleProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();
        /**
         * @var Role
         */
        $role = $data;

        if($operation->getName() === 'RolePost') {
            $this->handlePost($role, $user, $entrepriseId);
        }

        if($operation->getName() === 'RolePatch') {
            $this->handlePatch($role, $user, $entrepriseId);
        }

        return $this->processor->process($role, $operation, $uriVariables, $context); /*
            - Pas besoin de 'persist' et 'flush' le 'Role' dans le 'processor' car on délègue au 'DoctrineProcessor' interne de 'ApiPlatform' pour ne pas casser des comportements internes
        */
    }

    private function handlePost(Role $role, User $user, int $entrepriseId): void
    {
        // Un acteur de gare (non privilégié) crée un rôle DE SA GARE (sinon rôle entreprise = gare null).
        if(GareScopedEntities::isGareDelegate($user)) {
            $role->setGare($user->getGare());
        }
        $role
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId())
            ->setUpdatedBy($user->getId());
        $this->assertPermissionsInScope($role, $user);
        $this->assertUniqueName($role, $entrepriseId);
        $this->syncPermissions($role, $user, $entrepriseId);
    }

    private function handlePatch(Role $role, User $user, int $entrepriseId): void
    {
        // La gare du rôle n'est pas exposée en écriture : elle est conservée telle quelle ('previous_data').
        // 'GareScopeExtension' a déjà empêché de charger un rôle hors de la gare de l'acteur.
        $role
            ->setUpdatedBy($user->getId());
        $this->assertPermissionsInScope($role, $user);
        $this->assertUniqueName($role, $entrepriseId);
        $this->syncPermissions($role, $user, $entrepriseId);
    }

    /**
     * Option A : un acteur borné à une gare ne peut déléguer des permissions QUE sur les entités de
     * son périmètre ('GareScopedEntities::ENTITIES'). Garde-fou serveur indispensable (le filtrage du
     * formulaire ne suffit pas) contre l'escalade de privilèges.
     */
    private function assertPermissionsInScope(Role $role, User $user): void
    {
        if(!GareScopedEntities::isGareDelegate($user)) {
            return; // admin/super entreprise et utilisateur central : aucune restriction
        }
        foreach($role->getPermissions() as $permission) {
            if(!in_array($permission->getEntity(), GareScopedEntities::ENTITIES, true)) {
                throw new AccessDeniedHttpException(sprintf(
                    'Vous ne pouvez pas attribuer de permission sur « %s » : hors de votre périmètre de gare.',
                    $permission->getEntity()
                ));
            }
        }
    }

    /**
     * Unicité du nom au périmètre (entreprise, gare). Remplace 'UniquePerEntreprise(name)' qui imposait
     * une unicité entreprise-wide (deux gares n'auraient pas pu réutiliser un nom).
     */
    private function assertUniqueName(Role $role, int $entrepriseId): void
    {
        if(empty($role->getName())) {
            return;
        }
        $existing = $this->em->getRepository(Role::class)->findOneBy([
            'identreprise' => $entrepriseId,
            'name' => $role->getName(),
            'gare' => $role->getGare(), // entité Gare ou null → bucket distinct
            'deletedAt' => null,
        ]);
        if($existing && $existing->getId() !== $role->getId()) {
            throw new ConflictHttpException('Un rôle portant ce nom existe déjà dans ce périmètre');
        }
    }

    private function syncPermissions(Role $role, User $user, int $entrepriseId): void
    {
        /*
            - La suppression des permissions existantes en cas d'update est géré par 'orphanRemoval: true'
        */
        foreach($role->getPermissions() as $permission) {
            $permission->setIdentreprise($entrepriseId)
                ->setCreatedBy($user->getId())
                ->setUpdatedBy($user->getId()); /*
                    - On n'a pas 'persist' ici vu qu'on a déjà 'cascade: ['persist']' dans 'Role'
                */
        }
    }
}
