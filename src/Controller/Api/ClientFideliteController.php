<?php

namespace App\Controller\Api;

use App\Domain\Service\FideliteService;
use App\Entity\User;
use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ClientFideliteController extends AbstractController
{
    #[Route('/api/clients/{id}/fidelite', name: 'api_client_fidelite', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function __invoke(
        int $id,
        ClientRepository $clientRepository,
        FideliteService $fideliteService,
        Security $security
    ): JsonResponse
    {
        /** @var User $user */
        $user = $security->getUser();

        $client = $clientRepository->findOneBy([
            'id' => $id,
            'identreprise' => $user->getEntreprise()->getId(),
            'deletedAt' => null
        ]);
        if (!$client) {
            throw new NotFoundHttpException('Client introuvable');
        }

        // Mêmes droits que la fiche client : voir un client, ou voir la billetterie
        if (!$security->isGranted('VOIR', $client) && !$security->isGranted('VOIR', 'Ticket')) {
            throw new AccessDeniedHttpException();
        }

        return $this->json($fideliteService->getStatut($client));
    }
}
