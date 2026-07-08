<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PromouvoirUserProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
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
        /*
            if($data->getEntreprise()->getId() !== $currentUser->getEntreprise()->getId()) { -- Le filtre..
                throw new AccessDeniedHttpException('Action non autorisée');
            }
        */
        if(!$currentUser->isFounder()) {
            throw new AccessDeniedHttpException('Seul l\'administrateur principal peut promouvoir un utilisateur');
        }

        if($data->getId() === $currentUser->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez pas modifier votre propre rôle');
        }

        if($data->isFounder()) {
            throw new BadRequestHttpException('Impossible de modifier le rôle du fondateur');
        }

        $roles = $data->getRoles();

        if(!in_array('ROLE_ADMIN', $roles, true) && in_array('ROLE_ADMIN_GARE', $roles, true)) {
            throw new BadRequestHttpException('Cet utilisateur est administrateur de gare. Révoquez ce rôle avant de le promouvoir en administrateur d\'entreprise.');
        }

        if(in_array('ROLE_ADMIN', $roles, true)) {
            $roles = array_values(array_filter($roles, fn($role) => $role !== 'ROLE_ADMIN')); /*
                - On rétrograde pour rétirer le rôle d'administrateur
            */
        } else {
            // Un administrateur d'entreprise gère toute l'entreprise : il ne doit pas être rattaché à une gare
            if($data->getGare() !== null) {
                throw new BadRequestHttpException('Cet utilisateur est lié à une gare. Retirez sa gare avant de le promouvoir en administrateur d\'entreprise.');
            }
            $roles[] = 'ROLE_ADMIN';
        }
        $data->setRoles($roles); /*
            - '->setUpdatedAt(new \DateTimeImmutable())' gérer par le 'EntityBase'
        */
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
