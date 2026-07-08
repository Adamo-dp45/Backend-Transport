<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Enum\DetailcourrierStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Detailcourrier;
use App\Entity\User;
use App\Security\GareGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PerduDetailcourrierProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private GareGuard $gareGuard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Detailcourrier $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();

        if($data->getStatut() === DetailcourrierStatus::STATUT_PERDU->value) {
            throw new BadRequestHttpException('Ce colis est déjà déclaré perdu');
        }

        $courrier = $data->getCourrier();
        if(!in_array($courrier->getStatut(), [
            CourrierStatus::STATUT_EN_TRANSIT->value,
            CourrierStatus::STATUT_RECEPTIONNE->value
        ])) {
            throw new BadRequestHttpException('Un colis ne peut être déclaré perdu que si le courrier est en transit ou réceptionné');
        }

        // Le courrier est embarqué (EN_TRANSIT/RECEPTIONNE) → c'est la gare de destination qui le détient.
        $this->gareGuard->assertEstGare($user, $courrier->getGarearrivee(), 'Seule la gare de destination peut déclarer ce colis perdu');

        $data->setStatut(DetailcourrierStatus::STATUT_PERDU->value);
        $tousLesColissPerdus = true; /*
            - On vérifie si tous les colis sont perdus
        */
        foreach($courrier->getDetailcourriers() as $detail) {
            if($detail->getId() === $data->getId()) {
                continue;
            }
            if($detail->getStatut() !== DetailcourrierStatus::STATUT_PERDU->value) {
                $tousLesColissPerdus = false;
                break;
            }
        }

        if($tousLesColissPerdus) {
            $courrier->setStatut(CourrierStatus::STATUT_PERDU->value);
            $this->em->persist($courrier);
        }

        // Tracé sur la cible Courrier (visible dans sa timeline) ; précise si tout le courrier est perdu.
        $this->activiteLogger->log(
            ActiviteLogger::COLIS_PERDU,
            'Colis « ' . $data->getNature() . ' » du courrier ' . $courrier->getCodecourrier() . ' déclaré perdu'
                . ($tousLesColissPerdus ? ' (courrier entièrement perdu)' : ''),
            'Courrier',
            $courrier->getId()
        );

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
