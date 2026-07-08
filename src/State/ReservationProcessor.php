<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ReservationCreationService;
use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Création d'une réservation de PLACE côté GUICHET (agent). Délègue la logique (validation, prix
 * verrouillé, capacité sous verrou, expiration) au service partagé {@see ReservationCreationService}
 * — le même que celui utilisé par l'API publique invité (mobile/web).
 */
class ReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private ReservationCreationService $creationService,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Reservation
    {
        /** @var Reservation $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $userGareEntity = $user->getGare(); // App\Entity\Gare|null côté BK

        return $this->creationService->creer(
            $data,
            $user->getEntreprise()->getId(),
            $userGareEntity,
            $user->getId()
        );
    }
}
