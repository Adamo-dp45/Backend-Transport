<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Domain\Service\ReservationConfigService;
use App\Entity\Output\Reservation\DepartPubliqueDto;
use App\Repository\TarifRepository;
use App\Repository\VoyageRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** Départs réservables (voyages datés futurs) sur un tronçon + places/prix — `?slug=&provenance=&destination=`. */
final class DepartsPubliquesProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private VoyageRepository $voyageRepository,
        private TarifRepository $tarifRepository,
        private CapaciteService $capaciteService,
        private ReservationConfigService $config
    )
    {
    }

    /** @return DepartPubliqueDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $e = $this->resolver->resoudre($request?->query->get('slug'));
        $entrepriseId = $e->getId();

        $provenanceId = (int) $request?->query->get('provenance');
        $destinationId = (int) $request?->query->get('destination');
        if (!$provenanceId || !$destinationId || $provenanceId === $destinationId) {
            return [];
        }

        $tarif = $this->tarifRepository->findMontant($provenanceId, $destinationId, $entrepriseId);
        $montant = $tarif?->getMontant();

        // Ne montrer que les départs ENCORE réservables : le départ doit être au-delà de la fenêtre
        // de réservation (départ − délai > maintenant), sinon on afficherait un départ non réservable.
        $delaiMinutes = $this->config->getParametre($entrepriseId)->getDelaiExpirationMinutes();
        $limiteDepart = (new \DateTimeImmutable())->modify('+' . $delaiMinutes . ' minutes');

        $departs = [];
        foreach ($this->voyageRepository->findFutursPourTroncon($provenanceId, $destinationId, $entrepriseId) as $voyage) {
            if ($voyage->getDatedepartprevue() !== null && $voyage->getDatedepartprevue() <= $limiteDepart) {
                continue; // fenêtre de réservation dépassée
            }
            $ordreParGare = [];
            foreach ($voyage->getLigne()->getArrets() as $arret) {
                $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
            }
            $ordreMontee = $ordreParGare[$provenanceId] ?? null;
            $ordreDescente = $ordreParGare[$destinationId] ?? null;
            if ($ordreMontee === null || $ordreDescente === null || $ordreMontee >= $ordreDescente) {
                continue;
            }
            // DÉPART PARTIEL : ne pas proposer un voyage pour une montée située AVANT sa provenance réelle.
            $origineEff = $voyage->getOrigineEffective();
            $ordreProvenance = $origineEff !== null ? ($ordreParGare[$origineEff->getId()] ?? 0) : 0;
            if ($ordreMontee < $ordreProvenance) {
                continue;
            }

            $places = $this->capaciteService->placesDisponibles($voyage, $ordreMontee, $ordreDescente, $entrepriseId);
            if ($places <= 0) {
                continue; // complet ou capacité non définie → non réservable
            }

            $departs[] = new DepartPubliqueDto(
                voyageId: $voyage->getId(),
                codevoyage: $voyage->getCodevoyage(),
                datedepartprevue: $voyage->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
                datearriveeprevue: $voyage->getDatearriveeprevue()?->format(\DateTimeInterface::ATOM),
                placesDisponibles: $places,
                montant: $montant !== null ? (int) $montant : null
            );
        }

        return $departs;
    }
}
