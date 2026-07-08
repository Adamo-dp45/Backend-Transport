<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\FideliteService;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Renvoie le programme de fidélité de l'entreprise courante (auto-créé avec les défauts si absent).
 */
class MeProgrammeFideliteProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private FideliteService $fideliteService
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->fideliteService->getProgramme($user->getEntreprise()->getId());
    }
}
