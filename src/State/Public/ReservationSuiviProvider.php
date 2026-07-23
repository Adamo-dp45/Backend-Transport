<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Suivi public d'une réservation par son code — `?slug=&code=&contact=`. Le téléphone (contact) est
 * exigé et doit correspondre : évite qu'un tiers consulte une réservation en devinant un code.
 */
final class ReservationSuiviProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private ReservationRepository $reservationRepository,
        private ReservationPubliqueMapper $mapper
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ReservationPubliqueDto
    {
        $request = $this->requestStack->getCurrentRequest();
        $e = $this->resolver->resoudre($request?->query->get('slug'));
        $code = trim((string) $request?->query->get('code'));
        $contact = preg_replace('/\s+/', '', (string) $request?->query->get('contact'));

        $reservation = $code !== '' ? $this->reservationRepository->findOneBy([
            'code' => $code,
            'identreprise' => $e->getId(),
            'deletedAt' => null,
        ]) : null;

        // Vérif du téléphone (normalisé) — anti-énumération de codes
        $contactResa = preg_replace('/\s+/', '', (string) $reservation?->getContactclient());
        if ($reservation === null || $contact === '' || $contact !== $contactResa) {
            throw new NotFoundHttpException('Réservation introuvable');
        }

        return $this->mapper->versDto($reservation);
    }
}
