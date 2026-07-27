<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\StockmouvementService;
use App\Entity\Dto\AjustementstockInput;
use App\Entity\User;
use App\Repository\PieceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AjustementstockProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private StockmouvementService $stockmouvementService,
        private PieceRepository $pieceRepository,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AjustementstockInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();
        $pieceId = $uriVariables['id']; // Le {id} dans l'url
        $piece = $this->pieceRepository->findOneBy([
            'id' => $pieceId,
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);

        if(!$piece) {
            throw new NotFoundHttpException('Pièce introuvable');
        }

        $type = $data->quantite >= 0 ? Typemouvement::ENTREE->value : Typemouvement::SORTIE->value;

        $this->stockmouvementService->createMovement(
            $piece,
            $type,
            abs($data->quantite),
            Referencetype::AJUSTEMENT->value,
            null,
            $entrepriseId,
            $user->getId()
        );

        /*
            Le registre 'Inventaire' porte déjà QUI a ajusté, QUOI et COMBIEN (mouvement typé
            AJUSTEMENT + auteur) : le journal n'a pas à le redire. En revanche le MOTIF saisi n'est
            stocké nulle part — or sur un ajustement de stock, c'est justement le « pourquoi » qui
            fait la valeur de l'audit. On le consigne donc ici.
        */
        $this->activiteLogger->log(
            ActiviteLogger::STOCK_AJUSTE,
            sprintf(
                'Stock ajusté sur %s : %+d — motif : %s',
                $piece->getLibelle() ?? '—',
                $data->quantite,
                trim((string) $data->motif) !== '' ? $data->motif : 'non renseigné'
            ),
            'Piece',
            $piece->getId()
        );

        return $this->processor->process($piece, $operation, $uriVariables, $context);
    }
}