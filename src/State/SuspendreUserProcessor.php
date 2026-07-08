<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\User;
use App\Security\UserManagementGuard;
use Symfony\Bundle\SecurityBundle\Security;

class SuspendreUserProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
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
        $currentUser = $this->security->getUser(); /*
            - L'identreprise est déjà géré par le filtre ; toutes les règles de hiérarchie/gare
              (auto-suspension, fondateur, admin, admin de gare, périmètre gare) sont centralisées.
        */
        $this->guard->assertCanManage($currentUser, $data);

        $data->setStatut(
            $data->getStatut() === ReferenceStatus::ACTIF->value
            ? ReferenceStatus::SUSPENDU->value
            : ReferenceStatus::ACTIF->value
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
