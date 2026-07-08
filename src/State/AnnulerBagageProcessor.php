<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\BagageStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Bagage;
use App\Entity\User;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Annulation d'un bagage : passe ANNULE (le transport n'aura pas lieu) et le SORT de la recette.
 * Autorisé UNIQUEMENT tant que le bagage est encore ENREGISTRE (pas embarqué) ; une fois embarqué,
 * livré ou perdu, il n'est plus annulable. Réservé à la gare de DÉPÔT (celle qui l'a enregistré).
 */
class AnnulerBagageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private GareGuard $gareGuard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Bagage $data */

        /** @var User $user */
        $user = $this->security->getUser();

        if ($data->getStatut() !== BagageStatus::STATUT_ENREGISTRE->value) {
            throw new BadRequestHttpException('Seul un bagage encore enregistré (non embarqué) peut être annulé. Statut actuel : ' . $data->getStatut());
        }

        // Réservé à la gare de DÉPÔT (garedepart) qui a enregistré le bagage. Admin/central : non restreints.
        $this->gareGuard->assertEstGare($user, $data->getGaredepart(), 'Seule la gare de dépôt du bagage peut l\'annuler');

        $data
            ->setStatut(BagageStatus::STATUT_ANNULE->value)
            ->setUpdatedBy($user->getId())
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->activiteLogger->log(
            ActiviteLogger::BAGAGE_ANNULE,
            'Bagage ' . $data->getCodebagage() . ' annulé',
            'Bagage',
            $data->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
