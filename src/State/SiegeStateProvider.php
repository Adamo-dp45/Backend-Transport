<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Enum\TicketStatus;
use App\Domain\Service\CapaciteService;
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
        private Security $security,
        private CapaciteService $capaciteService
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
        // Nb d'occupants RÉELS au point d'embarquement ($ordreMontee) par siège : sert à n'autoriser la
        // libération/revente QUE s'il y a un SEUL occupant (sinon libérer l'un ne libère pas le siège →
        // c'est un conflit amont/aval, pas une revente).
        $nbOccupants = [];
        // Nb de billets VALIDE par siège, et parmi eux ceux que la priorité amont a évincés : deux
        // billets sur un siège sont une REVENTE s'ils voyagent tous, un CONFLIT si l'un reste à quai.
        $ventesParSiege = [];
        $evincesParSiege = [];
        $evincesTickets = $voyage !== null ? $this->capaciteService->billetsEvinces($voyage, $entrepriseId) : [];
        /*
            Billets d'une gare AVAL qui seraient évincés si l'on vendait ce siège sur le tronçon
            demandé (cf. Siege::$venduAval). On retient le plus AMONT — celui qui monterait le
            premier — pour le nommer, et on compte les autres.

            @var array<int, array{ordre:int, ticket:\App\Entity\Ticket, nombre:int}>
        */
        $avalParSiege = [];
        foreach ($tickets as $ticket) {
            if (!$ticket->getSiege()) {
                continue;
            }
            $siegeId = $ticket->getSiege()->getId();
            $ventesParSiege[$siegeId] = ($ventesParSiege[$siegeId] ?? 0) + 1;
            if (isset($evincesTickets[$ticket->getId()])) {
                $evincesParSiege[$siegeId] = ($evincesParSiege[$siegeId] ?? 0) + 1;
            }

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
            /*
                PRIORITÉ ABSOLUE À LA GARE AMONT : le siège n'est occupé, pour qui embarque à
                $ordreMontee, que si un passager y est DÉJÀ assis à ce moment (embarqué avant ou à ce
                point, et descend après). Une vente d'une gare en AVAL ne grise rien — mais elle est
                désormais SIGNALÉE ('venduAval', plus bas) : la règle ne bouge pas, l'agent est juste
                prévenu qu'il évincerait quelqu'un en prenant ce siège plutôt qu'un autre.

                Conséquence ASSUMÉE : un siège vendu Bouaké → Korhogo peut être revendu Abidjan →
                Korhogo, donc porter deux passagers sur le tronçon commun. C'est la règle
                d'exploitation retenue — l'amont ne cède jamais sa place. La gare en aval qui perd
                ainsi des sièges le constate et ouvre un voyage supplémentaire pour ses passagers.

                Ne PAS confondre avec un chevauchement de tronçons : tester le chevauchement
                bloquerait la vente d'Abidjan, ce que cette règle refuse explicitement.
            */
            if ($tm <= $ordreMontee && $td > $ordreMontee) {
                $siegesOccupes[$siegeId] = $ticket;
                $nbOccupants[$siegeId] = ($nbOccupants[$siegeId] ?? 0) + 1;
                continue;
            }

            /*
                VENDU EN AVAL — le billet ne grise rien (il monte APRÈS l'acheteur, la priorité amont
                joue), mais sa montée tombe DANS le tronçon vendu : prendre ce siège l'évincerait.

                LES DEUX BORNES SONT INDISPENSABLES, et oublier la première a produit un faux positif
                qui décrédibilisait tout le repère :

                  * $tm > $ordreMontee — le billet monte APRÈS l'acheteur. Ne pas sortir de ce
                    contrôle du fait qu'on a échappé au test d'occupation ci-dessus : on y échappe
                    AUSSI quand le passager est monté AVANT et a DÉJÀ DESCENDU ($td <= $ordreMontee).
                    Un Abidjan → Bouaké signalait alors le siège sur le plan de Bouaké, alors que son
                    occupant vient précisément d'en descendre — le siège y est libre, sans personne à
                    évincer ;
                  * $tm < $ordreDescente — sa montée tombe AVANT la descente de l'acheteur. Au-delà,
                    le passager monte là où l'acheteur descend : c'est une REVENTE, le bon cas, et
                    surtout pas une alerte.

                Un billet DÉJÀ évincé est ignoré : son sort ne dépend pas de la vente en cours, et le
                siège porte déjà la pastille 'conflit'. Crier deux fois au loup pour la même place
                ferait douter des alertes qui, elles, sont évitables.
            */
            if ($tm > $ordreMontee && $tm < $ordreDescente && !isset($evincesTickets[$ticket->getId()])) {
                $courant = $avalParSiege[$siegeId] ?? null;
                $avalParSiege[$siegeId] = [
                    'ordre' => $courant === null ? $tm : min($courant['ordre'], $tm),
                    'ticket' => ($courant === null || $tm < $courant['ordre']) ? $ticket : $courant['ticket'],
                    'nombre' => ($courant['nombre'] ?? 0) + 1,
                ];
            }
        }

        foreach ($sieges as $siege) {
            $plusieursBillets = ($ventesParSiege[$siege->getId()] ?? 0) >= 2;
            $enConflit = ($evincesParSiege[$siege->getId()] ?? 0) > 0;
            /*
                REVENDU = plusieurs billets qui voyagent tous (tronçons disjoints) : une réutilisation
                réussie. CONFLIT = plusieurs billets dont l'un est évincé : une place perdue. Les
                marquer pareil laissait compter comme « reventes » des sièges où un client reste à quai.
            */
            $siege->setRevendu($plusieursBillets && !$enConflit);
            $siege->setConflit($enConflit);

            // AVERTISSEMENT (jamais un blocage) : le siège reste vendable, mais une gare aval l'a
            // déjà vendu et son passager sauterait. Posé avant le statut : il vaut pour un siège
            // LIBRE, c'est tout l'intérêt — on prévient AVANT que l'agent ne clique.
            $aval = $avalParSiege[$siege->getId()] ?? null;
            if ($aval !== null) {
                $siege->setVenduAval(true);
                $siege->setAvalNom($aval['ticket']->getNomclient());
                $siege->setAvalMontee($aval['ticket']->getGare()?->getLibelle());
                $siege->setAvalDescente($aval['ticket']->getGaredescente()?->getLibelle());
                $siege->setAvalNombre($aval['nombre']);
            }

            $bloquant = $siegesOccupes[$siege->getId()] ?? null;
            if ($bloquant === null) {
                $siege->setStatut('LIBRE');
                continue;
            }
            $siege->setStatut('OCCUPE');
            // Infos de l'occupant → siège « libérable » (revente) UNIQUEMENT en mode par tronçon, si le
            // siège n'a qu'UN SEUL occupant (sinon libérer l'un ne le libère pas — conflit amont/aval), et
            // si cet occupant a embarqué STRICTEMENT avant le point de revente (il peut donc y descendre).
            // S'il embarque justement à cette gare, il monte ici : pas de libération possible.
            if ($parSegment && ($nbOccupants[$siege->getId()] ?? 0) === 1) {
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
