<?php

namespace App\Controller\Api;

use App\Domain\Service\FideliteService;
use App\Entity\User;
use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche du statut de fidélité d'un client par TÉLÉPHONE, sans le créer.
 * Sert au guichet : quand l'agent saisit le numéro du passager, on affiche tout de suite
 * s'il est membre et s'il a une récompense disponible.
 */
final class FideliteLookupController extends AbstractController
{
    #[Route('/api/fidelite/lookup', name: 'api_fidelite_lookup', methods: ['GET'])]
    public function __invoke(
        Request $request,
        ClientRepository $clientRepository,
        FideliteService $fideliteService,
        Security $security
    ): JsonResponse
    {
        // Réservé aux vendeurs de billets
        if (!$security->isGranted('VOIR', 'Ticket') && !$security->isGranted('CREER', 'Ticket')) {
            throw new AccessDeniedHttpException();
        }

        /** @var User $user */
        $user = $security->getUser();

        $contact = preg_replace('/\s+/', '', (string) $request->query->get('contact', ''));
        if ($contact === '') {
            return $this->json(['found' => false]);
        }

        $client = $clientRepository->findOneActifByContact($contact, $user->getEntreprise()->getId());
        if ($client === null) {
            return $this->json(['found' => false]);
        }

        return $this->json([
            'found' => true,
            'client' => ['id' => $client->getId(), 'nom' => $client->getNom()],
            'statut' => $fideliteService->getStatut($client),
        ]);
    }
}
