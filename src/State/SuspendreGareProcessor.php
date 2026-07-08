<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Gare;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class SuspendreGareProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Gare $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $data
            ->setStatut(
                $data->getStatut() === ReferenceStatus::ACTIF->value
                ? ReferenceStatus::SUSPENDU->value
                : ReferenceStatus::ACTIF->value
            )
            ->setUpdatedBy($user->getId())
        ;
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
