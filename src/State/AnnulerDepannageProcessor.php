<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Domain\Service\CarStatutService;
use App\Domain\Service\StockmouvementService;
use App\Entity\Depannage;
use App\Entity\User;
use App\Repository\DepannageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Annulation d'un dépannage (PATCH /depannages/{id}/annuler).
 *
 * Approche par STATUT (vs corbeille) : le dépannage passe ANNULE et reste VISIBLE (audit), mais il est
 * exclu des coûts (DepannageRepository filtre statut != ANNULE). Comme un dépannage consomme du stock
 * (mouvements SORTIE), l'annulation le RESTAURE : un mouvement ENTREE par pièce remet les quantités en stock,
 * et le véhicule redevient disponible. On n'autorise l'annulation que pour un dépannage EN COURS (un dépannage
 * clôturé a réellement consommé les pièces).
 */
class AnnulerDepannageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private StockmouvementService $stockmouvementService,
        private CarStatutService $carStatutService,
        private DepannageRepository $depannageRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        /** @var Depannage|null $depannage */
        $depannage = $this->depannageRepository->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$depannage) {
            throw new NotFoundHttpException('Dépannage introuvable');
        }

        if ($depannage->getStatut() === DepannageStatus::ANNULE->value) {
            throw new BadRequestHttpException('Ce dépannage est déjà annulé');
        }
        if ($depannage->getStatut() === DepannageStatus::CLOTURE->value) {
            throw new BadRequestHttpException('Un dépannage clôturé ne peut pas être annulé (les pièces ont été consommées)');
        }

        // Restauration du stock : une ENTREE par pièce du dépannage (inverse de la SORTIE de création)
        foreach ($depannage->getDetaildepannages() as $detail) {
            $this->stockmouvementService->createMovement(
                $detail->getPiece(),
                Typemouvement::ENTREE->value,
                $detail->getQuantite(),
                Referencetype::DEPANNAGE->value,
                $depannage->getId(),
                $entrepriseId,
                $user->getId()
            );
        }

        // Le véhicule n'est plus en panne
        if ($depannage->getCar()) {
            $this->carStatutService->mettreDisponible($depannage->getCar());
        }

        $depannage
            ->setStatut(DepannageStatus::ANNULE->value)
            ->setUpdatedBy($user->getId());

        return $this->processor->process($depannage, $operation, $uriVariables, $context);
    }
}
