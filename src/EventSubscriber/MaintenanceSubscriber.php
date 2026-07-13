<?php

namespace App\EventSubscriber;

use App\Repository\MaintenanceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * VERROU DUR de maintenance côté API : quand le mode maintenance est actif, l'API refuse toute requête
 * des utilisateurs NON super-admin (503), y compris les clients qui taperaient l'API en direct (apps
 * mobiles incluses). Whitelist : login / refresh de token et la lecture de l'état de maintenance (le
 * front en a besoin), + le préflight CORS. Priorité < 8 → s'exécute APRÈS l'authentification du firewall.
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    /**
     * Chemins toujours accessibles, même en maintenance : authentification (login/refresh/logout),
     * profil de l'utilisateur courant (/api/me — le front en a besoin pour s'authentifier et savoir qui
     * il est, y compris pour afficher la page de maintenance) et lecture de l'état de maintenance.
     * Correspondance EXACTE : /api/me/* (entreprise, config…) reste bloqué.
     */
    private const WHITELIST = [
        '/api/login_check',
        '/api/token/refresh',
        '/api/token/invalidate',
        '/api/me',
        '/api/maintenance',
    ];

    public function __construct(
        private MaintenanceRepository $maintenanceRepository,
        private Security $security
    )
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // On ne garde que l'API ; le reste (docs, etc.) n'est pas concerné.
        if (!str_starts_with($path, '/api')) {
            return;
        }
        // Préflight CORS + chemins whitelistés
        if ($request->getMethod() === 'OPTIONS' || in_array($path, self::WHITELIST, true)) {
            return;
        }

        // Fail-open : si on ne peut PAS lire l'état (table pas encore migrée, hoquet DB…), on N'ENFERME PAS
        // les utilisateurs — on laisse passer plutôt que de casser toute l'API.
        try {
            $maintenance = $this->maintenanceRepository->getSingleton();
        } catch (\Throwable) {
            return;
        }
        if ($maintenance === null || !$maintenance->isActif()) {
            return;
        }

        // Le super admin garde l'accès total pour piloter/désactiver la maintenance.
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'maintenance',
            'detail' => $maintenance->getMessage() ?: 'Application en maintenance. Merci de réessayer plus tard.',
        ], Response::HTTP_SERVICE_UNAVAILABLE));
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité 7 : juste après le firewall (8) → l'utilisateur est authentifié, isGranted() fiable.
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }
}
