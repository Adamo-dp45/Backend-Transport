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
        private ActiviteLogger $activiteLogger,
        private \App\Domain\Service\SessioncaisseService $sessioncaisseService
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
            /*
                REMBOURSÉ, comme un billet désisté ou un bagage annulé : le client reprend son colis
                avant le départ, il repart avec son argent. TAXE ET FRAIS DE SUIVI ensemble — il a
                payé les deux, et le suivi SMS d'un colis qui ne part pas n'a pas plus lieu d'être
                que son transport. Le 'COALESCE' est la moitié du geste : la colonne est nullable,
                et une addition sans lui rendrait NULL pour tout courrier sans frais de suivi.
            */
            ->setMontantrembourse((int) $data->getMontant() + (int) ($data->getFraissuivi() ?? 0))
            ->setSessioncaisseremboursement($this->sessioncaisseService->courante($user))
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
