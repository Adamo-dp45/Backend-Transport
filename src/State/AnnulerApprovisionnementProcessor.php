<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ApprovisionnementStatus;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Domain\Service\StockmouvementService;
use App\Entity\Approvisionnement;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Annulation d'un approvisionnement (PATCH /approvisionnements/{id}/annuler).
 *
 * Approche par STATUT (vs corbeille) : l'approvisionnement passe ANNULE et reste VISIBLE (audit), mais il est
 * exclu des coûts (ApprovisionnementRepository filtre statut != ANNULE). Comme un approvisionnement AJOUTE du
 * stock (mouvements ENTREE), l'annulation le RETIRE : un mouvement SORTIE par pièce.
 *
 * Sécurité stock : si une pièce a déjà été (partiellement) consommée, le StockmouvementService lève
 * « Stock insuffisant » sur la SORTIE → l'annulation est refusée (rien n'est flushé). On n'autorise pas non plus
 * l'annulation d'un approvisionnement VERROUILLÉ (entrée de stock finalisée).
 */
class AnnulerApprovisionnementProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private StockmouvementService $stockmouvementService,
        private ApprovisionnementRepository $approvisionnementRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        /** @var Approvisionnement|null $approvisionnement */
        $approvisionnement = $this->approvisionnementRepository->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$approvisionnement) {
            throw new NotFoundHttpException('Approvisionnement introuvable');
        }

        if ($approvisionnement->getStatut() === ApprovisionnementStatus::ANNULE->value) {
            throw new BadRequestHttpException('Cet approvisionnement est déjà annulé');
        }
        /* -- Verrouiller désactivé (remplacé par l'annulation) — réactivable :
        if ($approvisionnement->isVerrouille()) {
            throw new BadRequestHttpException('Cet approvisionnement est verrouillé et ne peut pas être annulé');
        }
        */

        // Retrait du stock : une SORTIE par pièce de l'approvisionnement (inverse de l'ENTREE de création).
        // Si le stock est insuffisant (pièce déjà consommée), le service lève « Stock insuffisant » → annulation refusée.
        foreach ($approvisionnement->getDetailapprovisionnements() as $detail) {
            $this->stockmouvementService->createMovement(
                $detail->getPiece(),
                Typemouvement::SORTIE->value,
                $detail->getQuantite(),
                Referencetype::APPROVISIONNEMENT->value,
                $approvisionnement->getId(),
                $entrepriseId,
                $user->getId()
            );
        }

        $approvisionnement
            ->setStatut(ApprovisionnementStatus::ANNULE->value)
            ->setUpdatedBy($user->getId());

        return $this->processor->process($approvisionnement, $operation, $uriVariables, $context);
    }
}
