<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Billetterie\BilleterieStatistiqueOutput;
use App\Entity\Output\Billetterie\RecetteParCarDto;
use App\Entity\Output\Billetterie\RecetteParJourDto;
use App\Entity\Output\Billetterie\RecetteParTrajetDto;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class BilleterieStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private TicketRepository $ticketRepository,
        private ReservationRepository $reservationRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        $request = $this->requestStack->getCurrentRequest();
        [$dateDebut, $dateFin] = $this->parsePeriode($request);

        $recettesParJour = array_map(
            fn($row) => new RecetteParJourDto(
                label: $row['label'],
                montant: round((float)$row['montant'], 2),
                nbtickets: (int)$row['nbtickets']
            ),
            $this->ticketRepository->recettesParJour($dateDebut, $dateFin, $identreprise)
        );

        $recettesParTrajet = array_map(
            fn($row) => new RecetteParTrajetDto(
                trajet:    $row['trajet'],
                montant:   round((float)$row['montant'], 2),
                nbtickets: (int)$row['nbtickets'],
            ),
            $this->ticketRepository->recettesParTrajet($dateDebut, $dateFin, $identreprise)
        );

        $recettesParCar = array_map(
            fn($row) => new RecetteParCarDto(
                matricule: $row['matricule'],
                montant:   round((float)$row['montant'], 2),
                nbtickets: (int)$row['nbtickets'],
            ),
            $this->ticketRepository->recettesParCar($dateDebut, $dateFin, $identreprise)
        );

        // Recette billets ventilée par 3 CANAUX : guichet (comptoir) + commercial (à bord) + réservation
        // (payée au paiement, compte admin). Les ventes directes (guichet + commercial) sont « hors résa » ;
        // on y ADDITIONNE les réservations payées pour que la recette totale reflète tous les canaux.
        $recetteDirecte = $this->ticketRepository->recettesTotales($dateDebut, $dateFin, $identreprise); // hors résa
        $recetteGuichet = $this->ticketRepository->recettesGuichet($dateDebut, $dateFin, $identreprise);
        $recetteCommercial = round($recetteDirecte - $recetteGuichet, 2); // le reste des ventes directes = à bord
        $recetteReservation = $this->reservationRepository->recettesPayees($dateDebut, $dateFin, $identreprise);
        $recetteTotale = round($recetteDirecte + $recetteReservation, 2); // 3 canaux réunis

        return new BilleterieStatistiqueOutput(
            totalTickets:      $this->ticketRepository->countTotal($dateDebut, $dateFin, $identreprise),
            recetteTotale:     $recetteTotale,
            recettesParJour:   $recettesParJour,
            recettesParTrajet: $recettesParTrajet,
            recettesParCar:    $recettesParCar,
            recetteGuichet:    $recetteGuichet,
            recetteCommercial: $recetteCommercial,
            recetteReservation: $recetteReservation,
        );
    }
}
