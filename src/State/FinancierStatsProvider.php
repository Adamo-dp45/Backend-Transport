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
        private TicketRepository $ticketRepository,
        private CourrierRepository $courrierRepository,
        private BagageRepository $bagageRepository
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
        $recettesTickets = $this->ticketRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $recettesCourriers = $this->courrierRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $recettesBagages = $this->bagageRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $recettesTotales = $recettesTickets + $recettesCourriers + $recettesBagages;
        $coutDepannages = $this->depannageRepository->coutTotal($dateDebut, $dateFin, $identreprise);
        $coutApprovisionnements = $this->approvisionnementRepository->coutTotal($dateDebut, $dateFin, $identreprise);
        $beneficeNet = $recettesTotales - $coutDepannages - $coutApprovisionnements;

        // Recettes par jour — fusion tickets + courriers + bagages
        $rawTickets   = $this->ticketRepository->recettesParJour($dateDebut, $dateFin, $identreprise);
        $rawCourriers = $this->courrierRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise);
        $rawBagages   = $this->bagageRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise);

        $recettesIndex = [];
        foreach ($rawTickets as $row) {
            $recettesIndex[$row['label']]['tickets'] = (float)$row['montant'];
        }
        foreach ($rawCourriers as $row) {
            $recettesIndex[$row['label']]['courriers'] = (float)$row['montant'];
        }
        foreach ($rawBagages as $row) {
            $recettesIndex[$row['label']]['bagages'] = (float)$row['montant'];
        }
        ksort($recettesIndex);

        $recettesParJour = array_map(
            fn(string $label, array $vals) => new RecetteParJourDto( /*
                    - On l'a typé pour 'Intelephense'
                */
                label: $label,
                montant: round(
                    ($vals['tickets'] ?? 0) + ($vals['courriers'] ?? 0) + ($vals['bagages'] ?? 0),
                    2
                ),
            ),
            array_keys($recettesIndex),
            array_values($recettesIndex)
        );

        // Coûts par jour
        $depannagesParJour = $this->depannageRepository->coutParJour($dateDebut, $dateFin, $identreprise);
        $approsParJour     = $this->approvisionnementRepository->coutParJour($dateDebut, $dateFin, $identreprise);

        $coutsMap = [];
        foreach ($depannagesParJour as $row) {
            $coutsMap[$row['label']]['depannage'] = (float)$row['montant'];
        }
        foreach ($approsParJour as $row) {
            $coutsMap[$row['label']]['approvisionnement'] = (float)$row['montant'];
        }
        ksort($coutsMap);

        $coutsParJour = array_map(
            fn(string $label, array $valeurs) => new CoutParJourDto(
                label: $label,
                depannage: round($valeurs['depannage'] ?? 0, 2),
                approvisionnement: round($valeurs['approvisionnement'] ?? 0, 2),
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
            recettesCourriers: $recettesCourriers,
            recettesBagages: $recettesBagages,
            coutDepannages: $coutDepannages,
            coutApprovisionnements: $coutApprovisionnements,
            beneficeNet: $beneficeNet,
            recettesParJour: $recettesParJour,
            coutsParJour: $coutsParJour
        );
    }
}
