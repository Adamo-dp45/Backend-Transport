<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Courrier;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AnnulerCourrierProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private GareGuard $gareGuard,
        private UserRepository $userRepository,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Courrier $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();

        if($data->getStatut() !== CourrierStatus::STATUT_EN_ATTENTE->value) {
            throw new BadRequestHttpException('Seul un courrier en attente peut être annulé. Statut actuel : ' . $data->getStatut());
        }

        // Un courrier EN_ATTENTE n'a pas encore de gares (pas de voyage) → on borne à la gare ÉMETTRICE,
        // déduite du créateur du courrier. (Admin / utilisateur central : non restreints.)
        $createur = $data->getCreatedBy() ? $this->userRepository->find($data->getCreatedBy()) : null;
        $this->gareGuard->assertEstGare($user, $createur?->getGare(), 'Seule la gare émettrice peut annuler ce courrier');

        $data
            ->setStatut(CourrierStatus::STATUT_ANNULE->value)
            ->setUpdatedBy($user->getId())
            ->setUpdatedAt(new \DateTimeImmutable())
        ;

        $this->activiteLogger->log(
            ActiviteLogger::COURRIER_ANNULE,
            'Courrier ' . $data->getCodecourrier() . ' annulé',
            'Courrier',
            $data->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
