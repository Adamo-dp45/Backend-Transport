<?php

namespace App\Controller\Api;

use App\Domain\Service\ReservationExpirationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déclencheur HTTP des tâches planifiées, pour les hébergements qui ne proposent
 * qu'un « cron par URL » (sans accès shell/CLI). Équivalent de la commande
 * `app:reservations:expirer`, protégé par un jeton secret (env CRON_TOKEN).
 *
 * Sans jeton configuré, l'endpoint refuse TOUT (jamais ouvert par défaut).
 * À appeler depuis le planificateur de l'hébergeur, ex. toutes les 5 minutes :
 *   GET https://mon-domaine/api/cron/reservations-expirer?token=LE_JETON
 */
final class CronController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(CRON_TOKEN)%')]
        private readonly string $cronToken,
        private readonly ReservationExpirationService $expirationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/cron/reservations-expirer', name: 'api_cron_reservations_expirer', methods: ['GET', 'POST'])]
    public function expirerReservations(Request $request): JsonResponse
    {
        $this->assertToken($request);

        $resultat = $this->expirationService->traiter();
        $this->logger->info('[cron] Expiration des réservations exécutée', $resultat);

        return $this->json(['ok' => true] + $resultat);
    }

    /** Compare le jeton fourni (query `token` ou en-tête `X-Cron-Token`) au secret, en temps constant. */
    private function assertToken(Request $request): void
    {
        $fourni = (string) ($request->query->get('token') ?? $request->headers->get('X-Cron-Token', ''));
        if ($this->cronToken === '' || !hash_equals($this->cronToken, $fourni)) {
            throw new AccessDeniedHttpException('Jeton cron invalide.');
        }
    }
}
