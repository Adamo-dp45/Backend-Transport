<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\DepenseGareService;
use App\Domain\Service\RecetteGareService;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Depense\DepenseParGareDto;
use App\Entity\Output\Depense\DepenseParModeDto;
use App\Entity\Output\Depense\DepenseParPeriodeDto;
use App\Entity\Output\Depense\DepenseParTypeDto;
use App\Entity\Output\Depense\DepenseStatistiqueOutput;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use App\Repository\DepannageRepository;
use App\Repository\DepenseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * L'analyse des charges, à côté de la synthèse de '/stats/financiere'.
 *
 * Écran séparé et non quelques champs de plus sur le financier : celui-ci porte déjà huit requêtes
 * d'agrégation et répond à « combien reste-t-il ? », tandis qu'ici on répond à « où part l'argent ? ».
 * Ce sont deux questions, deux écrans, comme le stock et la flotte ont les leurs.
 */
class DepenseStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private DepenseRepository $depenseRepository,
        private DepenseGareService $depenseGareService,
        private RecetteGareService $recetteGareService,
        private DepannageRepository $depannageRepository,
        private ApprovisionnementRepository $approvisionnementRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($this->requestStack->getCurrentRequest());

        $depenses = $this->depenseGareService->parGare($debut, $fin, $identreprise);
        $total = $depenses['total'];
        $totalSiege = $depenses['siege']['montant'];
        $totalGares = $total - $totalSiege;

        // -- Où part l'argent : les postes, du plus lourd au plus léger -- //
        $parType = [];
        $nb = 0;
        foreach ($this->depenseRepository->totalParType($debut, $fin, $identreprise) as $r) {
            $montant = (int) $r['montant'];
            $nb += (int) $r['nbdepenses'];
            $parType[] = new DepenseParTypeDto(
                libelle: $r['typelibelle'] ?? '—',
                montant: $montant,
                nb: (int) $r['nbdepenses'],
                // Calculée ici : l'écran ne doit pas refaire une division dont il ignore le total.
                part: $total > 0 ? round($montant / $total * 100, 1) : 0.0,
            );
        }

        /*
            Par gare, AVEC la recette en regard : c'est la seule lecture qui dise laquelle gagne
            vraiment de l'argent. Le siège figure en dernier, sans recette — il n'en produit pas,
            et lui en prêter une n'aurait aucun sens.
        */
        $recettes = $this->recetteGareService->parGare($debut, $fin, $identreprise);
        $parGare = [];
        foreach ($depenses['gares'] as $gareId => $g) {
            $recette = (int) ($recettes[$gareId]['recetteTotale'] ?? 0);
            $parGare[] = new DepenseParGareDto(
                gareId: $gareId,
                libelle: $g['libelle'],
                depenses: $g['montant'],
                nb: $g['nb'],
                recette: $recette,
                resultat: $recette - $g['montant'],
            );
        }
        // Une gare qui encaisse sans rien dépenser doit figurer au classement : sinon la plus
        // rentable du réseau serait précisément celle qu'on ne verrait pas.
        foreach ($recettes as $gareId => $r) {
            if (!isset($depenses['gares'][$gareId])) {
                $parGare[] = new DepenseParGareDto(
                    gareId: (int) $gareId,
                    libelle: (string) $r['libelle'],
                    depenses: 0,
                    nb: 0,
                    recette: (int) $r['recetteTotale'],
                    resultat: (int) $r['recetteTotale'],
                );
            }
        }
        usort($parGare, fn (DepenseParGareDto $a, DepenseParGareDto $b) => $b->depenses <=> $a->depenses);

        if ($totalSiege > 0 || $depenses['siege']['nbdepenses'] > 0) {
            $parGare[] = new DepenseParGareDto(
                gareId: null,
                libelle: 'Siège',
                depenses: $totalSiege,
                nb: $depenses['siege']['nbdepenses'],
                recette: 0,
                resultat: -$totalSiege,
            );
        }

        $parMois = array_map(
            fn (array $r) => new DepenseParPeriodeDto(label: (string) $r['label'], montant: (int) $r['montant']),
            $this->depenseRepository->totalParMois($debut, $fin, $identreprise)
        );

        $parMode = array_map(
            fn (array $r) => new DepenseParModeDto(
                mode: (string) $r['mode'],
                montant: (int) $r['montant'],
                nb: (int) $r['nbdepenses'],
            ),
            $this->depenseRepository->totalParMode($debut, $fin, $identreprise)
        );

        /*
            Les charges que ce module ne porte PAS, rappelées à côté du total : elles pèsent sur le
            bénéfice mais ne sont imputables à aucune gare. Les taire laisserait croire que la
            somme des postes ci-dessus est tout ce que la compagnie dépense.
        */
        $coutDepannages = (int) $this->depannageRepository->coutTotal($debut, $fin, $identreprise);
        $coutApprovisionnements = (int) $this->approvisionnementRepository->coutTotal($debut, $fin, $identreprise);

        return new DepenseStatistiqueOutput(
            total: $total,
            nb: $nb,
            totalGares: $totalGares,
            totalSiege: $totalSiege,
            nonImputeAUneGare: $coutDepannages + $coutApprovisionnements,
            coutDepannages: $coutDepannages,
            coutApprovisionnements: $coutApprovisionnements,
            perimetreResultat: 'DEPENSES_GARE',
            parType: $parType,
            parGare: $parGare,
            parMois: $parMois,
            parMode: $parMode,
        );
    }
}
