<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TarifBagage;
use App\Entity\User;
use App\Repository\TarifbagageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TarifbagageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private TarifbagageRepository $tarifbagageRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var TarifBagage $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $data
                ->setIdentreprise($identreprise)
                ->setCreatedBy($user->getId());
        }
        $this->validateTranche($data, $identreprise);
        $data->setUpdatedAt(new \DateTimeImmutable());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function validateTranche(Tarifbagage $data, int $identreprise): void
    {
        $poidsMin = (int)$data->getPoidsmin();
        $poidsMax = $data->getPoidsmax() !== null ? (int) $data->getPoidsmax() : null;

        if($poidsMax !== null && $poidsMax <= $poidsMin) {
            throw new BadRequestHttpException('Le poids maximum doit être supérieur au poids minimum');
        }

        if($poidsMax === null) {
            $existante = $this->tarifbagageRepository->findTrancheIllimitee($identreprise, $data->getId());
            if($existante) {
                throw new BadRequestHttpException('Une tranche illimitée existe déjà : "' . $existante->getLibelle() . '"');
            }
        }

        $chevauchement = $this->tarifbagageRepository->findChevauchement(
            $poidsMin,
            $poidsMax,
            $identreprise,
            $data->getId()
        );

        if($chevauchement) {
            throw new BadRequestHttpException('Cette tranche chevauche une tranche existante : "' . $chevauchement->getLibelle() . '"');
        }
    }
}
