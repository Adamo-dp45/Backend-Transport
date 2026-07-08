<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Gare;
use App\Entity\User;
use App\Entity\UserRole;
use App\Repository\EntrepriseRepository;
use App\Security\UserManagementGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private Security $security,
        private EntrepriseRepository $entrepriseRepository,
        private UserManagementGuard $guard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $data */

        /**
         * @var User
         */
        $currentUser = $this->security->getUser();
        $entrepriseId = $currentUser->getEntreprise()->getId();
        $entreprise = $this->entrepriseRepository->find($entrepriseId);

        if(!empty($data->getPlainPassword())) {
            $data->setPassword(
                $this->hasher->hashPassword(
                    $data,
                    $data->getPlainPassword()
                )
            );
            $data->setPlainPassword(null); // Permet d'éviter de laisser des données sensibles comme le mot de passe en clair en mémoire
        }

        if($operation instanceof Post) {
            if($this->em->getRepository(User::class)->findOneBy(['email' => $data->getEmail()])) { /*
                - On contrôle l'unicité de l'email AVANT l'insert pour un message propre (au lieu d'une erreur SQL brute)
            */
                throw new ConflictHttpException('Cet email est déjà utilisé');
            }
            $data->setEntreprise($entreprise); /*
                - On lui affecte l'entreprise de l'utilisateur qui l'a crée
            */
            // Un acteur rattaché à une gare et NON privilégié (admin de gare OU simple
            // utilisateur de gare ayant les droits de gestion des users) est borné à SA gare :
            // il ne peut créer que pour sa propre gare, et celle-ci est auto-affectée par défaut.
            // (Admin/super entreprise et utilisateur central sans gare : libre choix de la gare.)
            $estPrivilegie = in_array('ROLE_ADMIN', $currentUser->getRoles(), true)
                || in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true);
            $gareActeur = $currentUser->getGare();

            if($data->getGare() !== null) {
                /**
                 * @var Gare $gare
                 */
                $gare = $data->getGare();
                if(!$estPrivilegie && $gareActeur !== null && $gareActeur->getId() !== $gare->getId()) {
                    throw new AccessDeniedHttpException('Vous ne pouvez créer des utilisateurs que pour votre propre gare.');
                }
                $data->setGare($gare);
            } elseif(!$estPrivilegie && $gareActeur !== null) {
                $data->setGare($gareActeur); /*
                    - Auto-affectation de la gare de l'acteur s'il n'en précise pas (champ masqué côté front)
                */
            }
        }

        if($operation instanceof Patch) {
            /**
             * @var User|null $previous
             * - On s'appuie sur l'état AVANT dénormalisation (rôles/gare réels de la cible), car '$data'
             *   reflète déjà le payload (un acteur pourrait y glisser une autre gare/rôle).
             */
            $previous = $context['previous_data'] ?? null;
            if(!$previous instanceof User) {
                throw new AccessDeniedHttpException('Modification impossible');
            }

            // Règles d'autorisation centralisées (hiérarchie + périmètre gare + auto-modification)
            $this->guard->assertCanManage($currentUser, $previous);

            if($data->getEmail() !== $previous->getEmail()
                && $this->em->getRepository(User::class)->findOneBy(['email' => $data->getEmail()])) { /*
                - Email changé vers un email déjà pris → message propre au lieu d'une erreur SQL
            */
                throw new ConflictHttpException('Cet email est déjà utilisé');
            }

            // Un acteur rattaché à une gare (non admin) ne peut pas changer la gare de l'utilisateur : on la fige.
            // (Un admin/super ou un utilisateur central sans gare peut réaffecter la gare.)
            $estPrivilegie = in_array('ROLE_ADMIN', $currentUser->getRoles(), true)
                || in_array('ROLE_SUPER_ADMIN', $currentUser->getRoles(), true);
            if(!$estPrivilegie && $currentUser->getGare() !== null) {
                $data->setGare($previous->getGare());
            }

            $existingRoles = $this->em->getRepository(UserRole::class)->findBy([
                'usere' => $data
            ]);
            foreach($existingRoles as $existing) { /*
                - On supprime les anciens 'UserRole' de l'utilisateur ou avoir le 'orphanRemoval: true' et 'cascade: ['persist', 'remove']' sur le 'OneToMany'
            */
                $this->em->remove($existing);
            }
            /*
                - Si on 'write:User' sur '$roles'
                $incomingRoles = $data->getRoles(); -- On.. empêche un non administrateur de s'attribuer 'ROLE_ADMIN'
                if(in_array('ROLE_ADMIN', $incomingRoles, true) && !in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
                    throw new AccessDeniedHttpException('Vous ne pouvez pas attribuer le rôle administrateur');
                }
            */
        }

        foreach($data->getUserRoles() as $userRole) {
            if (!$userRole->getRole()) {
                continue; /*
                    - Pour éviter un rôle 'null' ou '{}' et on peut 'throw' une exception 'BadRequestHttpException' vu qu'on n'a définie 'role' comme nullable dans 'UserRole'
                */
            }
            $userRole
                ->setUsere($data)
                ->setIdentreprise($entreprise->getId())
                ->setCreatedBy($currentUser->getId()); /*
                - On ne persist pas vu qu'on n'a le 'cascade: ['persist']' sur 'User'
            */
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
