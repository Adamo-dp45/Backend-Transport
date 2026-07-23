<?php

namespace App\Domain\Service;

use App\Domain\Enum\TicketStatus;
use App\Entity\Reservation;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\SiegeRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Émission du BILLET (avec siège) d'une réservation — le « bon de réservation → billet de gare ».
 *
 * Attribue le premier siège LIBRE sur tout le tronçon [montée, descente) et crée le billet VALIDE.
 * Partagé entre l'émission normale ({@see App\State\EmettreBilletReservationProcessor}) et la
 * RÉGULARISATION d'une réservation reportée ({@see App\State\RegulariserReservationProcessor}).
 *
 * À appeler SOUS VERROU pessimiste sur $voyage (sérialisation des ventes/émissions concurrentes) —
 * le verrou reste la responsabilité de l'appelant.
 */
class EmissionBilletService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TicketRepository $ticketRepository,
        private SiegeRepository $siegeRepository
    )
    {
    }

    /**
     * Crée et persiste le billet VALIDE de la réservation sur $voyage, au prix $prix (verrouillé),
     * avec un siège libre attribué. Lève une 400 si le tronçon n'est pas desservi ou si le car est plein.
     */
    public function creer(Reservation $reservation, Voyage $voyage, int $prix, int $entrepriseId, User $user): Ticket
    {
        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Le voyage n\'est rattaché à aucune ligne');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $ordreMontee = $ordreParGare[$reservation->getGare()?->getId()] ?? null;
        $ordreDescente = $ordreParGare[$reservation->getGaredescente()?->getId()] ?? null;
        if ($ordreMontee === null || $ordreDescente === null || $ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('Le tronçon de la réservation n\'est pas desservi par ce voyage');
        }

        $siege = $this->attribuerSiege($voyage, $ordreParGare, $ordreMontee, $ordreDescente, $entrepriseId);
        if ($siege === null) {
            throw new BadRequestHttpException('Plus aucun siège disponible sur ce tronçon : la réservation ne peut être honorée (remboursement à prévoir)');
        }

        $ticket = (new Ticket())
            ->setVoyage($voyage)
            ->setSiege($siege)
            ->setReservation($reservation) // canal RÉSERVATION : exclu de la recette gare (payé sur compte admin)
            ->setGare($reservation->getGare())
            ->setGaredescente($reservation->getGaredescente())
            ->setNomclient($reservation->getNomclient())
            ->setContactclient($reservation->getContactclient())
            ->setClient($reservation->getClient())
            ->setPrix($prix)
            ->setRemise(0)
            ->setStatut(TicketStatus::STATUT_VALIDE->value)
            ->setCodeticket($voyage->getCodevoyage() . '-' . $this->genererCodeTicket($entrepriseId, $voyage->getId()))
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId());

        $this->em->persist($ticket);

        return $ticket;
    }

    /**
     * Premier siège du car libre AU POINT DE MONTÉE de la réservation. Renvoie null si tous sont
     * occupés à cet instant.
     *
     * Même règle que le plan des sièges et que CapaciteService : PRIORITÉ ABSOLUE À LA GARE AMONT.
     * On testait auparavant le CHEVAUCHEMENT du tronçon entier — si Bouaké avait rempli le car de
     * Bouaké à Korhogo, plus aucun siège ne paraissait libre et une réservation payée depuis Abidjan
     * devenait impossible à honorer (« remboursement à prévoir »), alors que le car est vide au départ
     * d'Abidjan. Le surbooking qui en découle est assumé : c'est l'aval qui cède.
     *
     * $ordreDescente n'entre plus dans le choix (seule la montée compte) mais reste au contrat :
     * l'appelant l'a validé juste avant, et le retirer masquerait ce contrôle.
     *
     * @param array<int,int> $ordreParGare
     */
    private function attribuerSiege(Voyage $voyage, array $ordreParGare, int $ordreMontee, int $ordreDescente, int $entrepriseId): ?Siege
    {
        $ordreTerminus = $ordreParGare[$voyage->getLigne()->getGareterminus()->getId()] ?? PHP_INT_MAX;

        $sieges = $this->siegeRepository->findBy(
            ['car' => $voyage->getCar(), 'identreprise' => $entrepriseId],
            ['numero' => 'ASC']
        );
        $tickets = $this->ticketRepository->findBy([
            'voyage' => $voyage,
            'identreprise' => $entrepriseId,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]);

        $intervallesParSiege = [];
        foreach ($tickets as $ticket) {
            if (!$ticket->getSiege()) {
                continue;
            }
            $tm = $ordreParGare[$ticket->getGare()?->getId()] ?? null;
            $descenteEff = $ticket->getGaredescenteEffective();
            $td = $descenteEff ? ($ordreParGare[$descenteEff->getId()] ?? $ordreTerminus) : $ordreTerminus;
            if ($tm !== null && $td !== null) {
                $intervallesParSiege[$ticket->getSiege()->getId()][] = [$tm, $td];
            }
        }

        foreach ($sieges as $siege) {
            $occupe = false;
            foreach ($intervallesParSiege[$siege->getId()] ?? [] as [$tm, $td]) {
                // Occupé à l'instant de la montée seulement : ce qui part d'une gare en aval ne compte pas.
                if ($tm <= $ordreMontee && $td > $ordreMontee) {
                    $occupe = true;
                    break;
                }
            }
            if (!$occupe) {
                return $siege;
            }
        }

        return null;
    }

    private function genererCodeTicket(int $entrepriseId, int $voyageId): string
    {
        $count = $this->ticketRepository->count([
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
            'voyage' => $voyageId,
        ]);

        return 'TCK-' . date('Y') . '-' . ($count + 1);
    }
}
