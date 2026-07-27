<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AlerteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints d'appoint du centre d'alertes qui ne passent pas par le pipeline entité :
 *  - le RÉSUMÉ (badge de la cloche) : total + ventilations, sans charger la liste ;
 *  - le « TOUT LIRE » : acquittement en masse des alertes ACTIVE visibles.
 * Le ciblage (audience) est porté par AlerteRepository via AlerteAudienceResolver — même règle
 * que la collection /api/alertes. Firewall api anonyme → on exige l'authentification ici.
 */
final class AlerteController extends AbstractController
{
    #[Route('/api/alertes/resume', name: 'api_alertes_resume', methods: ['GET'])]
    public function resume(Security $security, AlerteRepository $alertes): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $security->getUser();
        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return $this->json(['total' => 0, 'parSeverite' => [], 'parFamille' => []]);
        }

        return $this->json($alertes->resume($entreprise->getId()));
    }

    #[Route('/api/alertes/recentes', name: 'api_alertes_recentes', methods: ['GET'])]
    public function recentes(Security $security, AlerteRepository $alertes): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $security->getUser();
        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return $this->json([]);
        }

        // Ordonnancement décidé par le serveur (sévérité puis date), audience appliquée.
        return $this->json(
            $alertes->recentes($entreprise->getId()),
            200,
            [],
            ['groups' => ['read:Alerte', 'read:Base']]
        );
    }

    #[Route('/api/alertes/lire-tout', name: 'api_alertes_lire_tout', methods: ['POST'])]
    public function lireTout(Security $security, AlerteRepository $alertes): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $security->getUser();
        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return $this->json(['basculees' => 0]);
        }

        $n = $alertes->marquerToutLu($entreprise->getId(), $user->getId());

        return $this->json(['basculees' => $n]);
    }
}
