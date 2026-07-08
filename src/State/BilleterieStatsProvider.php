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
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class BilleterieStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private TicketRepository $ticketRepository
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

        // Ventilation par canal : guichet (méthode dédiée) + réservation (dédiée) ; le commercial se
        // déduit du total pour garantir guichet + commercial + réservation = recetteTotale.
        $recetteTotale = $this->ticketRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $recetteGuichet = $this->ticketRepository->recettesGuichet($dateDebut, $dateFin, $identreprise);
        $recetteReservation = $this->ticketRepository->recettesReservation($dateDebut, $dateFin, $identreprise);
        $recetteCommercial = round($recetteTotale - $recetteGuichet - $recetteReservation, 2);

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
