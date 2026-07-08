<?php

namespace App\Controller\Api;

use App\Domain\Service\CapaciteService;
use App\Entity\User;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renseigne l'occupation d'un tronçon (capacité, places réservées, places disponibles) pour informer
 * l'agent au guichet — notamment combien de places sont tenues par des réservations.
 */
final class CapaciteController extends AbstractController
{
    #[Route('/api/capacite', name: 'api_capacite', methods: ['GET'])]
    public function __invoke(
        Request $request,
        VoyageRepository $voyageRepository,
        CapaciteService $capaciteService,
        Security $security
    ): JsonResponse
    {
        if (!$security->isGranted('VOIR', 'Ticket') && !$security->isGranted('VOIR', 'Reservation')) {
            throw new AccessDeniedHttpException();
        }

        /** @var User $user */
        $user = $security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        $voyageId = (int) $request->query->get('voyage');
        $monteeId = (int) $request->query->get('montee');
        $descenteId = (int) $request->query->get('descente');
        if (!$voyageId || !$monteeId || !$descenteId) {
            return $this->json(['capacite' => null, 'placesReservees' => 0, 'placesDisponibles' => null]);
        }

        $voyage = $voyageRepository->findOneBy(['id' => $voyageId, 'identreprise' => $identreprise, 'deletedAt' => null]);
        if (!$voyage || !$voyage->getLigne()) {
            return $this->json(['capacite' => null, 'placesReservees' => 0, 'placesDisponibles' => null]);
        }

        $ordreParGare = [];
        foreach ($voyage->getLigne()->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $ordreMontee = $ordreParGare[$monteeId] ?? null;
        $ordreDescente = $ordreParGare[$descenteId] ?? null;
        if ($ordreMontee === null || $ordreDescente === null || $ordreMontee >= $ordreDescente) {
            return $this->json(['capacite' => $capaciteService->capaciteEffective($voyage), 'placesReservees' => 0, 'placesDisponibles' => null]);
        }

        return $this->json([
            'capacite' => $capaciteService->capaciteEffective($voyage),
            'placesReservees' => $capaciteService->placesReservees($voyage, $ordreMontee, $ordreDescente, $identreprise),
            'placesDisponibles' => $capaciteService->placesDisponibles($voyage, $ordreMontee, $ordreDescente, $identreprise),
        ]);
    }
}
