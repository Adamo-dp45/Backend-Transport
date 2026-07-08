<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Output\Stock\MouvementRecentDto;
use App\Entity\Output\Stock\StockPieceDto;
use App\Entity\Output\Stock\StockStatistiqueOutput;
use App\Entity\User;
use App\Repository\InventaireRepository;
use App\Repository\PieceRepository;
use Symfony\Bundle\SecurityBundle\Security;

class StockStatsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private PieceRepository $pieceRepository,
        private InventaireRepository $inventaireRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // -- Le stock n'a pas de filtre par période vu que les données reflètent l'état actuel -- //

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        $pieces = $this->pieceRepository->stockParPiece($identreprise);

        $stockParPiece = [];
        $piecesCritiques = 0;

        foreach ($pieces as $piece) {
            $stockactuel = $piece['stockinitial'];
            $critique = $stockactuel <= $piece['seuilstock'];
            if($critique) {
                $piecesCritiques++;
            }
            $stockParPiece[] = new StockPieceDto(
                id:          $piece['id'],
                libelle:     $piece['libelle'],
                stockactuel: $stockactuel,
                seuilstock:  $piece['seuilstock'],
                critique:    $critique,
            );
        }

        $mouvementsRecents = array_map(
            fn($row) => new MouvementRecentDto(
                piece: $row['piece'],
                typemouvement: $row['typemouvement'],
                quantite: (int)$row['quantite'],
                date: $row['date']->format('Y-m-d')
            ),
            $this->inventaireRepository->mouvementsRecents($identreprise) /*
                - On l'a limité au 10 derniers
            */
        );

        return new StockStatistiqueOutput(
            totalPieces: count($pieces),
            piecesCritiques: $piecesCritiques,
            stockParPiece: $stockParPiece,
            mouvementsRecents: $mouvementsRecents
        );
    }
}
