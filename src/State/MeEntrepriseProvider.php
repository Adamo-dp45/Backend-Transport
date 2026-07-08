<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use Symfony\Bundle\SecurityBundle\Security;

class MeEntrepriseProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private EntrepriseRepository $entrepriseRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entreprise = $this->entrepriseRepository->find($user->getEntreprise()->getId());
        return $entreprise;
    }
}
