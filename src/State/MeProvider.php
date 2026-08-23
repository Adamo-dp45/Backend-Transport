<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserRepository $userRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        /*
            ApiPlatform exécute le PROVIDER AVANT d'évaluer le 'security:' de l'opération : sur une
            requête anonyme, l'utilisateur est donc encore nul ici. Sans cette garde, '/api/me'
            répondait 500 (« Call to a member function getId() on null ») au lieu de 401 — et le
            front, qui redirige vers le login sur 401 ('AuthenticationExceptionListener'), affichait
            une page d'erreur ; côté mobile, l'intercepteur ne tentait pas son rafraîchissement.
            Le jeton étant absent, Symfony traduit cette exception via l'entry point JWT en 401.
        */
        if (!$user instanceof User) {
            throw new AccessDeniedException('Authentification requise.');
        }

        return $this->userRepository->findWithRolesAndPermissions($user->getId());
    }
}
