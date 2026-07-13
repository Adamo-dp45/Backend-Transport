<?php

namespace App\Controller\Api;

use App\Domain\Service\ConfigRecetteService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Paramètres de RECETTE / CA de l'entreprise courante (config séparée). Lecture + mise à jour de
 * l'inclusion des courriers dans le chiffre d'affaires. Réservé à l'admin d'entreprise.
 */
final class ConfigRecetteController extends AbstractController
{
    #[Route('/api/me/config-recette', name: 'api_me_config_recette_get', methods: ['GET'])]
    public function show(Security $security, ConfigRecetteService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $security->getUser();
        $config = $service->get($user->getEntreprise()->getId());

        return $this->json(['courriershorsca' => $config->isCourriershorsca()]);
    }

    #[Route('/api/me/config-recette', name: 'api_me_config_recette_patch', methods: ['PATCH'])]
    public function update(
        Request $request,
        Security $security,
        ConfigRecetteService $service,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User $user */
        $user = $security->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $horsCa = (bool) ($payload['courriershorsca'] ?? false);

        $config = $service->get($user->getEntreprise()->getId());
        $config->setCourriershorsca($horsCa)->setUpdatedBy($user->getId());
        $em->flush();

        return $this->json(['courriershorsca' => $config->isCourriershorsca()]);
    }
}
