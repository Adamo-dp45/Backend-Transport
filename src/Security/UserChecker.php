<?php

namespace App\Security;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface // Dans 'security.yaml' - 'user_checker'
{
    /**
     * Permet de faire un traitement avant l'authentification
     * @param UserInterface $user
     * @throws CustomUserMessageAuthenticationException
     * @return void
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if(!$user instanceof User) {
            return;
        }

        if($user->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new CustomUserMessageAuthenticationException('Votre compte a été suspendu. Contactez l\'administrateur.');
        }

        if($user->getEntreprise() && $user->getEntreprise()->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new CustomUserMessageAuthenticationException('Votre entreprise a été désactivée. Contactez le support.');
        }

        if($user->getGare() && $user->getGare()->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new CustomUserMessageAuthenticationException('Votre gare a été suspendue. Contactez l\'administrateur.');
        }
    }

    /**
     * Permet de faire un traitement après l'authentification
     * @param UserInterface $user
     * @param mixed $token
     * @return void
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}