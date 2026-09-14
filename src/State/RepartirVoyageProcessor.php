<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\DepartGareService;
use App\Entity\User;
use App\Entity\Voyage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Départ du car d'une gare INTERMÉDIAIRE : PATCH /voyages/{id}/repartir.
 *
 * Horodate le DÉPART réel du car de sa position courante — le pendant de la réception (qui horodate
 * l'arrivée). Sans cette action, on connaîtrait l'arrivée à chaque gare mais pas le départ, donc pas le
 * temps d'arrêt. Réservé au commercial du voyage, à l'agent de la gare où se trouve le car, ou à un admin.
 *
 * L'origine (départ marqué au démarrage) et le terminus (pas de départ après lui) sont exclus.
 */
class RepartirVoyageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private DepartGareService $departGareService
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Voyage $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $courante = $data->getGarecourante();

        /*
            L'AUTORISATION reste ici : elle diffère selon la voie. En ligne, trois profils peuvent
            déclarer le départ ; hors ligne, le contrôleur de synchronisation a déjà établi que
            l'appelant est le commercial du voyage. Les règles MÉTIER, elles, sont communes et vivent
            dans le service — les dupliquer les ferait dériver.
        */
        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        $estCommercial = $data->getCommercial() && $data->getCommercial()->getId() === $user->getId();
        $estAgentGareCourante = $user->getGare() && $courante && $user->getGare()->getId() === $courante->getId();
        if (!$estAdmin && !$estCommercial && !$estAgentGareCourante) {
            throw new AccessDeniedHttpException('Seul le commercial, un agent de la gare où se trouve le car, ou un admin peut enregistrer le départ.');
        }

        /*
            'false' = le départ était déjà horodaté. En ligne c'est un refus : le commercial croit
            déclarer quelque chose, il ne déclare rien de neuf et doit le savoir. Dans un lot hors
            ligne, le même 'false' vaut « déjà synchronisé ».
        */
        if (!$this->departGareService->repartir($data)) {
            throw new BadRequestHttpException('Le départ de ' . ($courante?->getLibelle() ?? 'cette gare') . ' a déjà été enregistré.');
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
