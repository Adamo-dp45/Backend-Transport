<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Dto\ChangePasswordInput;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $hasher,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var ChangePasswordInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        if(!$this->hasher->isPasswordValid($user, $data->currentPassword)) {
            throw new BadRequestHttpException("Mot de passe actuel incorrect");
        }
        $user->setPassword($this->hasher->hashPassword($user, $data->newPassword));

        return $this->processor->process($user, $operation, $uriVariables, $context);
    }
}
