<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PromouvoirAdminGareProcessor implements ProcessorInterface
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
 
        if($data->getId() === $currentUser->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez pas modifier votre propre rôle.');
        }

        if($data->isFounder()) {
            throw new BadRequestHttpException('Impossible de modifier le rôle du fondateur.');
        }

        $roles = $data->getRoles();
        if(in_array('ROLE_ADMIN_GARE', $roles, true)) {
            $roles = array_values(array_filter($roles, fn($r) => $r !== 'ROLE_ADMIN_GARE'));
        } else {
            if($data->getGare() === null) {
                throw new BadRequestHttpException('L\'utilisateur doit être lié à une gare pour devenir administrateur de gare.');
            }
            if(in_array('ROLE_ADMIN', $roles, true)) {
                throw new BadRequestHttpException('Un administrateur d\'entreprise ne peut pas être administrateur de gare en même temps.');
            }
            $roles[] = 'ROLE_ADMIN_GARE';
        }
        $data->setRoles($roles);

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
