<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationConfigService;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Renvoie les paramètres de réservation de l'entreprise courante (auto-créés si absents).
 */
class MeParametreReservationProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ReservationConfigService $config
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->config->getParametre($user->getEntreprise()->getId());
    }
}
