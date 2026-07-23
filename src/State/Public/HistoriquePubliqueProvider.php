<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Historique des réservations d'un client (invité) : liste ses réservations d'une compagnie par son
 * TÉLÉPHONE — `?slug=&contact=`. C'est ainsi qu'un client sans compte retrouve/suit ses réservations
 * et re-télécharge un bon. (Le téléphone joue le rôle d'identifiant ; un vrai « compte + OTP » pourra
 * être ajouté plus tard pour renforcer.)
 */
final class HistoriquePubliqueProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private ReservationRepository $reservationRepository,
        private ReservationPubliqueMapper $mapper
    )
    {
    }

    /** @return ReservationPubliqueDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $e = $this->resolver->resoudre($request?->query->get('slug'));
        $contact = trim((string) $request?->query->get('contact'));
        if ($contact === '') {
            return [];
        }

        return array_map(
            fn($r) => $this->mapper->versDto($r),
            $this->reservationRepository->findParContactPourEntreprise($contact, $e->getId())
        );
    }
}
