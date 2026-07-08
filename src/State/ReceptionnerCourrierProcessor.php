<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\CourrierStatus;
use App\Entity\Courrier;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Réception d'un courrier à sa gare d'arrivée (un arrêt de la ligne).
 *
 * Avec les arrêts intermédiaires, un colis destiné à une gare intermédiaire arrive AVANT le terminus.
 * C'est donc l'agent de la gare d'arrivée du colis qui confirme la réception « à l'arrêt »
 * (la clôture du voyage n'auto-réceptionne plus que les colis destinés au terminus — cf. VoyageClotureSatutSubscriber).
 */
class ReceptionnerCourrierProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
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

        if ($data->getStatut() !== CourrierStatus::STATUT_EN_TRANSIT->value) {
            throw new BadRequestHttpException('Seul un courrier en transit peut être réceptionné. Statut actuel : ' . $data->getStatut());
        }
        /*
            - On pourra restreindre à l'agent de la gare d'arrivée du colis quand le GareScopeExtension sera en place :
              if ($user->getGare()?->getId() !== $data->getGarearrivee()?->getId()) { throw ...; }
        */

        $data
            ->setStatut(CourrierStatus::STATUT_RECEPTIONNE->value)
            ->setUpdatedBy($user->getId())
            ->setUpdatedAt(new \DateTimeImmutable());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
