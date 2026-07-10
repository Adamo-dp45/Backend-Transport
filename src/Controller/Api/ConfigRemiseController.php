<?php

namespace App\Controller\Api;

use App\Domain\Service\ConfigRemiseService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres de REMISE de l'entreprise courante (config séparée, comme la fidélité). Lecture + mise à jour
 * du plafond de remise manuelle. Réservé à l'admin d'entreprise.
 */
final class ConfigRemiseController extends AbstractController
{
    #[Route('/api/me/config-remise', name: 'api_me_config_remise_get', methods: ['GET'])]
    public function show(Security $security, ConfigRemiseService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $security->getUser();
        $config = $service->get($user->getEntreprise()->getId());

        return $this->json(['maxpourcentage' => $config->getMaxpourcentage()]);
    }

    #[Route('/api/me/config-remise', name: 'api_me_config_remise_patch', methods: ['PATCH'])]
    public function update(
        Request $request,
        Security $security,
        ConfigRemiseService $service,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $security->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $raw = $payload['maxpourcentage'] ?? null;

        // Plafond : entier 0..100, ou null (= pas de plafond) si vide.
        $max = ($raw === null || $raw === '') ? null : (int) $raw;
        if ($max !== null && ($max < 0 || $max > 100)) {
            return $this->json(['detail' => 'Le plafond doit être compris entre 0 et 100 %.'], 422);
        }

        $config = $service->get($user->getEntreprise()->getId());
        $config->setMaxpourcentage($max)->setUpdatedBy($user->getId());
        $em->flush();

        return $this->json(['maxpourcentage' => $config->getMaxpourcentage()]);
    }
}
