<?php

namespace App\Controller\Api;

use App\Domain\Service\MaintenanceService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mode maintenance GLOBAL. Lecture ouverte à tout utilisateur authentifié (le front en a besoin, y
 * compris pendant la maintenance) ; bascule réservée au SUPER ADMIN.
 */
final class MaintenanceController extends AbstractController
{
    #[Route('/api/maintenance', name: 'api_maintenance_get', methods: ['GET'])]
    public function show(MaintenanceService $service): JsonResponse
    {
        $m = $service->get();

        return $this->json([
            'actif' => $m->isActif(),
            'message' => $m->getMessage(),
            'depuis' => $m->getDepuis()?->format(\DATE_ATOM),
        ]);
    }

    #[Route('/api/maintenance', name: 'api_maintenance_patch', methods: ['PATCH'])]
    public function update(
        Request $request,
        Security $security,
        MaintenanceService $service,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        /** @var User $user */
        $user = $security->getUser();

        $payload = json_decode($request->getContent(), true) ?? [];
        $actif = (bool) ($payload['actif'] ?? false);
        $message = isset($payload['message']) ? trim((string) $payload['message']) : null;

        $m = $service->get();
        // On horodate au moment où l'on ACTIVE (transition off → on).
        if ($actif && !$m->isActif()) {
            $m->setDepuis(new \DateTimeImmutable());
        }
        $m->setActif($actif)
            ->setMessage($message !== '' ? $message : null)
            ->setUpdatedBy($user->getId());
        $em->flush();

        return $this->json([
            'actif' => $m->isActif(),
            'message' => $m->getMessage(),
            'depuis' => $m->getDepuis()?->format(\DATE_ATOM),
        ]);
    }
}
