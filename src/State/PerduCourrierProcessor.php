<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Enum\DetailcourrierStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Courrier;
use App\Entity\User;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PerduCourrierProcessor implements ProcessorInterface
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

        if(!in_array($data->getStatut(), [
            CourrierStatus::STATUT_EN_TRANSIT->value,
            CourrierStatus::STATUT_RECEPTIONNE->value, // après clôture du voyage le courrier est réceptionné : on autorise quand même la perte
        ], true)) {
            throw new BadRequestHttpException('Seul un courrier en transit ou réceptionné peut être déclaré perdu. Statut actuel : ' . $data->getStatut());
        }

        // Le courrier est embarqué (EN_TRANSIT/RECEPTIONNE) → c'est la gare de descente/destination qui le détient.
        $this->gareGuard->assertEstGare($user, $data->getGarearrivee(), 'Seule la gare de destination peut déclarer ce courrier perdu');

        $now = new \DateTimeImmutable();
        $data
            ->setStatut(CourrierStatus::STATUT_PERDU->value)
            ->setUpdatedBy($user->getId())
            ->setUpdatedAt($now)
        ;

        // Le courrier entier est perdu → tous ses colis encore NORMAL le sont aussi (entités managées,
        // flushées avec le courrier). On ne re-touche pas un colis déjà PERDU.
        foreach($data->getDetailcourriers() as $colis) {
            if($colis->getStatut() !== DetailcourrierStatus::STATUT_PERDU->value) {
                $colis
                    ->setStatut(DetailcourrierStatus::STATUT_PERDU->value)
                    ->setUpdatedBy($user->getId())
                    ->setUpdatedAt($now)
                ;
            }
        }

        $this->activiteLogger->log(
            ActiviteLogger::COURRIER_PERDU,
            'Courrier ' . $data->getCodecourrier() . ' déclaré perdu',
            'Courrier',
            $data->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
