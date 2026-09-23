<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Financier\CoutParJourDto;
use App\Entity\Output\Financier\FinancierStatistiqueOutput;
use App\Entity\Output\Financier\RecetteParJourDto;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\DepannageRepository;
use App\Repository\DepenseRepository;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class FinancierStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private ApprovisionnementRepository $approvisionnementRepository,
        private DepannageRepository $depannageRepository,
        private DepenseRepository $depenseRepository,
        private TicketRepository $ticketRepository,
        private CourrierRepository $courrierRepository,
        private BagageRepository $bagageRepository,
        private ReservationRepository $reservationRepository,
        private \App\Domain\Service\ConfigRecetteService $configRecetteService
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

        // Totaux
        $recettesTickets = $this->ticketRepository->recettesTotales($dateDebut, $dateFin, $identreprise); // hors réservation
        $recettesReservations = $this->reservationRepository->recettesPayees($dateDebut, $dateFin, $identreprise); // reconnues au paiement
        $recettesCourriers = $this->courrierRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $recettesBagages = $this->bagageRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        // Config entreprise : certaines compagnies excluent les courriers de leur chiffre d'affaires.
        $courriersHorsCa = $this->configRecetteService->courriersHorsCa($identreprise);
        $recettesTotales = $recettesTickets + $recettesReservations + ($courriersHorsCa ? 0 : $recettesCourriers) + $recettesBagages;
        $coutDepannages = $this->depannageRepository->coutTotal($dateDebut, $dateFin, $identreprise);
        $coutApprovisionnements = $this->approvisionnementRepository->coutTotal($dateDebut, $dateFin, $identreprise);
        /*
            TROISIÈME poste : les charges saisies à la main (carburant, salaires, péage…), qui ne
            passent par aucun module. Elles ne recouvrent NI les dépannages NI les approvisionnements
            — aucune dépense n'est dérivée de ceux-ci, sans quoi le double comptage serait technique
            donc invisible (cf. l'en-tête de 'Depense'). C'est ce qui rend le bénéfice enfin complet.
        */
        $coutDepenses = $this->depenseRepository->coutTotal($dateDebut, $dateFin, $identreprise);
        $beneficeNet = $recettesTotales - $coutDepannages - $coutApprovisionnements - $coutDepenses;

        // Recettes par jour — fusion tickets (hors résa) + réservations payées + courriers + bagages
        $rawTickets   = $this->ticketRepository->recettesParJour($dateDebut, $dateFin, $identreprise);
        $rawReservations = $this->reservationRepository->recettesPayeesParJour($dateDebut, $dateFin, $identreprise);
        $rawCourriers = $this->courrierRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise);
        $rawBagages   = $this->bagageRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise);

        $recettesIndex = [];
        foreach ($rawTickets as $row) {
            $recettesIndex[$row['label']]['tickets'] = (float)$row['montant'];
        }
        foreach ($rawReservations as $row) {
            $recettesIndex[$row['label']]['reservations'] = (float)$row['montant'];
        }
        foreach ($rawCourriers as $row) {
            $recettesIndex[$row['label']]['courriers'] = (float)$row['montant'];
        }
        foreach ($rawBagages as $row) {
            $recettesIndex[$row['label']]['bagages'] = (float)$row['montant'];
        }
        ksort($recettesIndex);

        $recettesParJour = array_map(
            function (string $label, array $vals) use ($courriersHorsCa): RecetteParJourDto { /*
                    - On l'a typé pour 'Intelephense'
                    - On expose AUSSI la répartition par canal (et pas seulement le total) : elle permet
                      d'empiler la courbe par type côté interface, sans requête supplémentaire — le
                      détail est déjà chargé ci-dessus.
                */
                $tickets = round($vals['tickets'] ?? 0, 2);
                $reservations = round($vals['reservations'] ?? 0, 2);
                // Courriers hors CA : mis à 0 ici aussi, pour que l'empilement totalise bien 'montant'.
                $courriers = $courriersHorsCa ? 0.0 : round($vals['courriers'] ?? 0, 2);
                $bagages = round($vals['bagages'] ?? 0, 2);

                return new RecetteParJourDto(
                    label: $label,
                    montant: round($tickets + $reservations + $courriers + $bagages, 2),
                    tickets: $tickets,
                    reservations: $reservations,
                    courriers: $courriers,
                    bagages: $bagages,
                );
            },
            array_keys($recettesIndex),
            array_values($recettesIndex)
        );

        // Coûts par jour
        $depannagesParJour = $this->depannageRepository->coutParJour($dateDebut, $dateFin, $identreprise);
        $approsParJour     = $this->approvisionnementRepository->coutParJour($dateDebut, $dateFin, $identreprise);
        $depensesParJour   = $this->depenseRepository->coutParJour($dateDebut, $dateFin, $identreprise);

        $coutsMap = [];
        foreach ($depannagesParJour as $row) {
            $coutsMap[$row['label']]['depannage'] = (float)$row['montant'];
        }
        foreach ($approsParJour as $row) {
            $coutsMap[$row['label']]['approvisionnement'] = (float)$row['montant'];
        }
        foreach ($depensesParJour as $row) {
            $coutsMap[$row['label']]['depense'] = (float)$row['montant'];
        }
        ksort($coutsMap);

        $coutsParJour = array_map(
            fn(string $label, array $valeurs) => new CoutParJourDto(
                label: $label,
                depannage: round($valeurs['depannage'] ?? 0, 2),
                approvisionnement: round($valeurs['approvisionnement'] ?? 0, 2),
                depense: round($valeurs['depense'] ?? 0, 2),
            ),
            array_keys($coutsMap),
            array_values($coutsMap)
        );
        /*  - Ou..
            $coutsParJour = [];
            foreach ($coutsMap as $label => $valeurs) {
                $coutsParJour[] = new CoutParJourDto(
                    label: $label,
                    depannage: round($valeurs['depannage'] ?? 0, 2),
                    approvisionnement: round($valeurs['approvisionnement'] ?? 0, 2),
                );
            }
        */
        return new FinancierStatistiqueOutput(
            recettesTotales: $recettesTotales,
            recettesTickets: $recettesTickets,
            recettesReservations: $recettesReservations,
            recettesCourriers: $recettesCourriers,
            recettesBagages: $recettesBagages,
            coutDepannages: $coutDepannages,
            coutApprovisionnements: $coutApprovisionnements,
            coutDepenses: $coutDepenses,
            beneficeNet: $beneficeNet,
            recettesParJour: $recettesParJour,
            coutsParJour: $coutsParJour
        );
    }
}
