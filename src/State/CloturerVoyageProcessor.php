<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CarStatutService;
use App\Entity\Dto\CloturerInput;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\VoyageRepository;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CLÔTURE d'un voyage via sa route dédiée (/voyages/{id}/cloturer), sortie du Patch « fourre-tout » de
 * VoyageProcessor. Pose l'arrivée réelle (datearriveereelle), réservée à la gare de DESTINATION
 * (terminus) via VoyageGuard, et libère le car. La propagation courrier/bagage reste gérée par
 * VoyageClotureStautSubscriber (écouteur Doctrine sur le flush).
 */
class CloturerVoyageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private VoyageRepository $voyageRepository,
        private VoyageGuard $guard,
        private CarStatutService $carStatutService,
        private ActiviteLogger $activiteLogger,
        private \App\Domain\Service\PassageService $passageService
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Voyage
    {
        /** @var CloturerInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        $voyage = $this->voyageRepository->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $identreprise,
            'deletedAt' => null,
        ]);
        if (!$voyage) {
            throw new NotFoundHttpException('Voyage invalide');
        }
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est déjà clôturé');
        }

        // Clôture réservée à la gare de destination (terminus) — ni la provenance, ni une intermédiaire.
        $this->guard->assertPeutCloturer($user, $voyage);

        $arrivee = $data->datearriveereelle ?? new \DateTimeImmutable();
        if($voyage->getDatedepartprevue() !== null && $arrivee <= $voyage->getDatedepartprevue()) {
            throw new BadRequestHttpException('La date d\'arrivée doit être postérieure à la date de départ prévue');
        }

        $voyage
            ->setDatearriveereelle($arrivee)
            ->setUpdatedBy($user->getId());

        // Passage réel : arrivée du car au TERMINUS (fin du trajet, pas de départ après lui).
        $this->passageService->marquerArrivee($voyage, $voyage->getLigne()?->getGareterminus(), $arrivee);

        if($voyage->getCar()) {
            $this->carStatutService->mettreDisponible($voyage->getCar()); // le car redevient disponible
        }

        $this->activiteLogger->voyage(ActiviteLogger::VOYAGE_CLOTURE, 'Voyage clôturé', $voyage->getId());

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}
