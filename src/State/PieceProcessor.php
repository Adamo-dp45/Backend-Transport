<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Dto\PieceInput;
use App\Entity\Piece;
use App\Entity\User;
use App\Repository\MarquepieceRepository;
use App\Repository\ModelRepository;
use App\Repository\TypepieceRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PieceProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private TypepieceRepository $typepieceRepository,
        private MarquepieceRepository $marquepieceRepository,
        private ModelRepository $modelRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var PieceInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();
        $piece = new Piece();
        $piece
            ->setLibelle($data->libelle)
            ->setStockinitial($data->stockInitial)
            ->setIdentreprise($entrepriseId)
            ->setPrixunitaire($data->prixunitaire)
            ->setCreatedBy($user->getId());

        if($data->typepieceId) {
            $typepiece = $this->typepieceRepository->findOneBy([
                'id' => $data->typepieceId,
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$typepiece) {
                throw new NotFoundHttpException('Type de pièce invalide');
            }
            $piece->setTypepiece($typepiece);
        }

        if($data->marquepieceId) {
            $marquepiece = $this->marquepieceRepository->findOneBy([
                'id' => $data->marquepieceId,
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$marquepiece) {
                throw new NotFoundHttpException('Marque de pièce invalide');
            }
            $piece->setMarquepiece($marquepiece);
        }

        if($data->modelepieceId) {
            $model = $this->modelRepository->findOneBy([
                'id' => $data->modelepieceId,
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$model) {
                throw new NotFoundHttpException('Modèle de pièce invalide');
            }
            $piece->setModel($model);
        }

        return $this->processor->process($piece, $operation, $uriVariables, $context);
    }
}
