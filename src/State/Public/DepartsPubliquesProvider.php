<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Output\Reservation\DepartPubliqueDto;
use App\Repository\TarifRepository;
use App\Repository\VoyageRepository;
use App\Security\VoyageGuard;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Départs encore réservables AU DÉPART DE LA GARE DEMANDÉE, sur un tronçon, avec places et prix —
 * `?slug=&provenance=&destination=`. La réservabilité se juge à la gare de montée (le car peut être
 * parti de l'origine sans y être encore passé), pas à l'heure de départ du voyage.
 */
final class DepartsPubliquesProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private VoyageRepository $voyageRepository,
        private TarifRepository $tarifRepository,
        private CapaciteService $capaciteService,
        private VoyageGuard $voyageGuard,
        private ReservationEcheanceService $echeance
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
        if ($montant === null) {
            // Sans tarif, la création de réservation échoue ('Aucun tarif défini pour ce trajet') :
            // mieux vaut ne rien proposer que d'afficher un départ sans prix qui refusera la réservation.
            return [];
        }

        $now = new \DateTimeImmutable();

        $departs = [];
        foreach ($this->voyageRepository->findFutursPourTroncon($provenanceId, $destinationId, $entrepriseId) as $voyage) {
            $ordreParGare = [];
            $gareMontee = null;
            foreach ($voyage->getLigne()->getArrets() as $arret) {
                $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
                if ($arret->getGare()->getId() === $provenanceId) {
                    $gareMontee = $arret->getGare(); // l'arrêt de CETTE ligne, pas une gare quelconque
                }
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
            /*
                Réservable À CETTE GARE-LÀ, et non « voyage à venir » : un car qui roule entre Abidjan
                et Bouaké se réserve encore au départ de Bouaké. Deux conditions, exactement celles que
                la création appliquera ensuite (ReservationCreationService) — proposer un départ que la
                réservation refuserait derrière serait pire que de ne rien proposer :
                  1. le car n'a pas déjà quitté la gare de montée ;
                  2. il reste le délai de présentation avant son passage à cette gare.
            */
            if ($this->voyageGuard->monteeDepassee($voyage, $gareMontee)) {
                continue;
            }
            $limite = $this->echeance->limitePresentationPour($voyage, $gareMontee, $entrepriseId);
            if ($limite === null || $limite <= $now) {
                continue;
            }

            $places = $this->capaciteService->placesDisponibles($voyage, $ordreMontee, $ordreDescente, $entrepriseId);
            if ($places <= 0) {
                continue; // complet ou capacité non définie → non réservable
            }

            $departs[] = new DepartPubliqueDto(
                voyageId: $voyage->getId(),
                codevoyage: $voyage->getCodevoyage(),
                heurepassage: $this->echeance->heurePassage($voyage, $gareMontee)?->format(\DateTimeInterface::ATOM),
                datedepartprevue: $voyage->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
                datearriveeprevue: $voyage->getDatearriveeprevue()?->format(\DateTimeInterface::ATOM),
                placesDisponibles: $places,
                montant: $montant !== null ? (int) $montant : null
            );
        }

        return $departs;
    }
}
