<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class UserProfileProcessor implements ProcessorInterface
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
        $user = $this->security->getUser();
        $user
            ->setEmail($data->getEmail())
            ->setNom($data->getNom())
            ->setPrenom($data->getPrenom())
        ;
        return $this->processor->process($user, $operation, $uriVariables, $context);
    }
}
