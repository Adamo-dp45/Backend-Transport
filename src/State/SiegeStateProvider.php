<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Enum\TicketStatus;
use App\Entity\User;
use App\Repository\SiegeRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class SiegeStateProvider implements ProviderInterface
{
    public function __construct(
        private SiegeRepository $siegeRepository,
        private TicketRepository $ticketRepository,
        private VoyageRepository $voyageRepository,
        private RequestStack $requestStack,
        private Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $carParam = $request->query->get('car');
        $voyageId = $request->query->get('voyage');
        $monteeParam = $request->query->get('montee');     // gare de montée (id ou iri)
        $descenteParam = $request->query->get('descente');  // gare de descente (id ou iri)

        if (!$carParam) {
            return [];
        }

        // Isolation tenant : on ne sert que les sièges des cars de l'entreprise de l'appelant
        // (sinon IDOR : lecture du plan/occupation des cars d'une autre entreprise en devinant des ids).
        /** @var User|null $user */
        $user = $this->security->getUser();
        $entrepriseId = $user?->getEntreprise()?->getId();
        if ($entrepriseId === null) {
            return [];
        }

        $carId = $this->extractId($carParam);
        $sieges = $this->siegeRepository->findBy(['car' => $carId, 'identreprise' => $entrepriseId]);
        if (empty($sieges)) {
            return []; // car d'une autre entreprise (ou sans siège) → rien à exposer
        }

        if(!$voyageId) {
            foreach($sieges as $siege) {
                $siege->setStatut('LIBRE');
            }
            return $sieges;
        }

        $voyage = $this->voyageRepository->find($this->extractId($voyageId));
        // Seuls les billets VALIDE occupent un siège : un billet reporté/annulé est libéré.
        // Filtré par entreprise (tenant) en plus du voyage.
        $tickets = $this->ticketRepository->findBy([
            'voyage' => $this->extractId($voyageId),
            'identreprise' => $entrepriseId,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]);

        // Carte des ordres d'arrêt si la ligne est disponible → permet le calcul par tronçon
        $ordreParGare = [];
        $ligne = $voyage?->getLigne();
        if ($ligne) {
            foreach ($ligne->getArrets() as $arret) {
                $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
            }
        }

        // Tronçon demandé (si fourni et cohérent avec la ligne) → mode « par segment »
        $monteeId = $monteeParam ? $this->extractId($monteeParam) : null;
        $descenteId = $descenteParam ? $this->extractId($descenteParam) : null;
        $parSegment = $ligne
            && $monteeId !== null && $descenteId !== null
            && isset($ordreParGare[$monteeId], $ordreParGare[$descenteId])
            && $ordreParGare[$monteeId] < $ordreParGare[$descenteId];

        $ordreTerminus = $ligne ? ($ordreParGare[$ligne->getGareterminus()->getId()] ?? PHP_INT_MAX) : PHP_INT_MAX;
        $ordreMontee = $parSegment ? $ordreParGare[$monteeId] : null;
        $ordreDescente = $parSegment ? $ordreParGare[$descenteId] : null;

        // Carte siegeId => ticket bloquant (pour exposer l'occupant et permettre le « dégrisage »/revente)
        $siegesOccupes = [];
        // Nb de billets VALIDE par siège sur le voyage : ≥ 2 = siège REVENDU (réutilisé sur des tronçons disjoints)
        $ventesParSiege = [];
        foreach ($tickets as $ticket) {
            if (!$ticket->getSiege()) {
                continue;
            }
            $siegeId = $ticket->getSiege()->getId();
            $ventesParSiege[$siegeId] = ($ventesParSiege[$siegeId] ?? 0) + 1;

            if (!$parSegment) {
                // Mode legacy : un siège est occupé dès qu'un ticket actif le référence
                $siegesOccupes[$siegeId] = $ticket;
                continue;
            }

            // Mode par tronçon : occupé seulement si le ticket chevauche [montée, descente).
            // L'occupation utilise la descente EFFECTIVE (réelle si le passager est descendu en route,
            // sinon vendue) → un siège libéré via /tickets/{id}/descendre se dégrise en aval.
            $tm = $ordreParGare[$ticket->getGare()?->getId()] ?? null;
            $descenteEff = $ticket->getGaredescenteEffective();
            $td = $descenteEff
                ? ($ordreParGare[$descenteEff->getId()] ?? null)
                : $ordreTerminus;
            if ($tm === null || $td === null) {
                $siegesOccupes[$siegeId] = $ticket; // sécurité : ticket hors ligne → on bloque
                continue;
            }
            // Priorité à la gare amont : le siège n'est occupé pour qui embarque à $ordreMontee que si un
            // passager y est DÉJÀ assis à ce moment (embarqué avant/à ce point ET descend après). Les ventes
            // des gares en aval (tm > ordreMontee) ne grisent pas le siège — la gare amont reste prioritaire.
            if ($tm <= $ordreMontee && $td > $ordreMontee) {
                $siegesOccupes[$siegeId] = $ticket;
            }
        }

        foreach ($sieges as $siege) {
            // Siège revendu : porté par plusieurs billets VALIDE sur ce voyage (réutilisé sur des tronçons)
            $siege->setRevendu(($ventesParSiege[$siege->getId()] ?? 0) >= 2);

            $bloquant = $siegesOccupes[$siege->getId()] ?? null;
            if ($bloquant === null) {
                $siege->setStatut('LIBRE');
                continue;
            }
            $siege->setStatut('OCCUPE');
            // Infos de l'occupant → siège « libérable » (revente) UNIQUEMENT en mode par tronçon et si
            // l'occupant a embarqué STRICTEMENT avant le point de revente (il peut donc y descendre).
            // S'il embarque justement à cette gare, il monte ici : pas de libération possible.
            if ($parSegment) {
                $tmOcc = $ordreParGare[$bloquant->getGare()?->getId()] ?? null;
                if ($tmOcc !== null && $tmOcc < $ordreMontee) {
                    $siege->setOccupantTicketId($bloquant->getId());
                    $siege->setOccupantNom($bloquant->getNomclient());
                    $siege->setOccupantMontee($bloquant->getGare()?->getLibelle());
                    $siege->setOccupantDescente($bloquant->getGaredescente()?->getLibelle());
                }
            }
        }

        return $sieges;
    }

    private function extractId(string $iriOrId): int
    {
        if (str_contains($iriOrId, '/')) {
            $parts = explode('/', trim($iriOrId, '/'));
            return (int) end($parts);
        }
        return (int) $iriOrId;
    }
}
