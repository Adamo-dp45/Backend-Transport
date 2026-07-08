<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\VoyageDepartService;
use App\Entity\User;
use App\Entity\Voyage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Action « Démarrer le voyage » : enregistre le DÉPART RÉEL (le car part de l'origine).
 * Pose datedepartreelle et bascule courriers EN_ATTENTE -> EN_TRANSIT, bagages ENREGISTRE -> EMBARQUE.
 */
class DemarrerVoyageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private VoyageDepartService $departService,
        private \App\Security\VoyageGuard $guard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Voyage $data */
        /** @var User $user */
        $user = $this->security->getUser();

        // Le car PART de la provenance du voyage → seule la gare d'origine EFFECTIVE (gareprovenance)
        // peut démarrer (+ admin/central). Une gare intermédiaire réceptionne, elle ne démarre pas.
        $this->guard->assertPeutPlanifier($user, $data);

        if ($data->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est déjà clôturé');
        }
        if ($data->getDatedepartreelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est déjà parti');
        }
        if ($data->getCar() === null) {
            throw new BadRequestHttpException('Affectez un car avant de démarrer le voyage');
        }

        $this->departService->marquerDepart($data, $user->getId());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
