<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\PassageService;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\PassageRepository;
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
        private PassageService $passageService,
        private PassageRepository $passageRepository,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Voyage $data */
        /** @var User $user */
        $user = $this->security->getUser();

        if ($data->getDatedepartreelle() === null) {
            throw new BadRequestHttpException('Le voyage n\'est pas encore parti.');
        }
        if ($data->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage est clôturé.');
        }

        $courante = $data->getGarecourante();
        $origine = $data->getOrigineEffective();
        $terminus = $data->getLigne()?->getGareterminus();
        if ($courante === null || $origine === null) {
            throw new BadRequestHttpException('La position courante du car est inconnue.');
        }
        if ($courante->getId() === $origine->getId()) {
            throw new BadRequestHttpException('Le car est encore à l\'origine : son départ a été enregistré au démarrage.');
        }
        if ($terminus !== null && $courante->getId() === $terminus->getId()) {
            throw new BadRequestHttpException('Le car est au terminus : il n\'en repart pas.');
        }

        // Autorisation : commercial du voyage, agent de la gare où se trouve le car, ou admin.
        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        $estCommercial = $data->getCommercial() && $data->getCommercial()->getId() === $user->getId();
        $estAgentGareCourante = $user->getGare() && $user->getGare()->getId() === $courante->getId();
        if (!$estAdmin && !$estCommercial && !$estAgentGareCourante) {
            throw new AccessDeniedHttpException('Seul le commercial, un agent de la gare où se trouve le car, ou un admin peut enregistrer le départ.');
        }

        // Le car doit être ARRIVÉ à cette gare (réception/avance), et pas déjà reparti.
        $passage = $this->passageRepository->findOneParVoyageGare((int) $data->getId(), (int) $courante->getId());
        if ($passage === null || $passage->getArriveeReelle() === null) {
            throw new BadRequestHttpException('L\'arrivée du car à ' . $courante->getLibelle() . ' n\'a pas encore été enregistrée.');
        }
        if ($passage->getDepartReelle() !== null) {
            throw new BadRequestHttpException('Le départ de ' . $courante->getLibelle() . ' a déjà été enregistré.');
        }

        $this->passageService->marquerDepart($data, $courante, new \DateTimeImmutable());

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_POSITION,
            sprintf('Départ du car de %s', $courante->getLibelle()),
            $data->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
