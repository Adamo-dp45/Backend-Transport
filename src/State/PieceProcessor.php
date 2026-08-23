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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

        /*
            Ces trois références sont FACULTATIVES : un identifiant nul veut dire « aucun », et doit
            donc EFFACER la valeur existante.

            Le code posait un simple 'if($data->typepieceId) { setTypepiece(...) }' sans branche
            inverse : une fois un type choisi, plus aucun moyen de revenir en arrière — sélectionner
            « — Sélectionner un type — » ne faisait rien et l'ancienne valeur restait. Le formulaire
            proposait un choix vide que le serveur ignorait en silence.
        */
        $piece
            ->setTypepiece($this->resoudre(
                $this->typepieceRepository, $data->typepieceId, $entrepriseId, 'Type de pièce invalide'
            ))
            ->setMarquepiece($this->resoudre(
                $this->marquepieceRepository, $data->marquepieceId, $entrepriseId, 'Marque de pièce invalide'
            ))
            ->setModel($this->resoudre(
                $this->modelRepository, $data->modelepieceId, $entrepriseId, 'Modèle de pièce invalide'
            ));

        return $this->processor->process($piece, $operation, $uriVariables, $context);
    }

    /**
     * Référence facultative bornée à l'entreprise : null quand rien n'est choisi, 404 quand
     * l'identifiant fourni ne correspond à rien de visible (référence d'une autre compagnie,
     * enregistrement supprimé).
     */
    private function resoudre(
        ServiceEntityRepository $repository,
        int|string|null $id,
        int $entrepriseId,
        string $message
    ): ?object {
        if (empty($id)) {
            return null;
        }

        $reference = $repository->findOneBy([
            'id' => $id,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);

        if (!$reference) {
            throw new NotFoundHttpException($message);
        }

        return $reference;
    }
}
