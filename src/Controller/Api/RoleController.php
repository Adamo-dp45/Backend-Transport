<?php

namespace App\Controller\Api;

use App\Domain\Service\EntityDiscoveryService;
use App\Entity\User;
use App\Security\GareScopedEntities;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RoleController extends AbstractController
{
    #[Route('/api/entities', name: 'api_entities', methods: ['GET'])]
    public function entities(EntityDiscoveryService $discovery, Security $security): JsonResponse
    {
        $entities = $discovery->getEntityList();

        $user = $security->getUser();
        if($user instanceof User && GareScopedEntities::isGareDelegate($user)) {
            // Un acteur de gare ne peut déléguer que sur les entités de son périmètre :
            // on masque le reste des cases du formulaire de rôle (le 'RoleProcessor' refuse de toute façon).
            $entities = array_values(array_intersect($entities, GareScopedEntities::ENTITIES));
        }

        return $this->json($entities);
    }
}
