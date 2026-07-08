<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Courrier;
use App\Entity\User;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class LivrerCourrierProcessor implements ProcessorInterface
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
        /** @var Courrier $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();

        if($data->getStatut() !== CourrierStatus::STATUT_RECEPTIONNE->value) {
            throw new BadRequestHttpException('Seul un courrier réceptionné peut être marqué comme livré. Statut actuel : ' . $data->getStatut());
        }

        // Seule la gare de DESTINATION (où le colis est réceptionné) confirme la remise au destinataire.
        $this->gareGuard->assertEstGare($user, $data->getGarearrivee(), 'Seule la gare de destination peut confirmer la livraison de ce courrier');
        $data
            ->setStatut(CourrierStatus::STATUT_LIVRE->value)
            ->setDatelivraison(new \DateTimeImmutable())
            ->setUpdatedBy($user->getId())
            ->setUpdatedAt(new \DateTimeImmutable());
        /*
            if($data->getModepaiement() === Courrier::PAIEMENT_RECEPTION) {
                $data
                    ->setEtatpaiement(Courrier::ETAT_PAIEMENT_PAYE)
                    ->setDatepaiement(new \DateTimeImmutable());
            } -- Si paiement à la réception, on le marque comme payé maintenant
        */

        $this->activiteLogger->log(
            ActiviteLogger::COURRIER_LIVRE,
            'Courrier ' . $data->getCodecourrier() . ' livré',
            'Courrier',
            $data->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
