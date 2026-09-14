<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\AvanceePositionService;
use App\Entity\Dto\AvancerCommercialInput;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Le commercial à bord fait avancer la position du car : PATCH /voyages/{id}/avancer.
 *
 * Couvre le cas d'une gare intermédiaire SANS agent (pas de réception) : le commercial, qui est
 * physiquement dans le car, déclare la gare où il se trouve désormais. Avancement MONOTONE (jamais
 * en arrière). Réservé au commercial du voyage (ou à un admin).
 */
class AvancerCommercialProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private AvanceePositionService $avanceePosition
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AvancerCommercialInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if ($data->gare === null) {
            throw new BadRequestHttpException('La gare où se trouve le car est obligatoire');
        }

        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        // Réservé au commercial du voyage (ou à un admin)
        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        $estCommercial = $voyage->getCommercial() && $voyage->getCommercial()->getId() === $user->getId();
        if (!$estCommercial && !$estAdmin) {
            throw new AccessDeniedHttpException('Seul le commercial du voyage peut faire avancer la position du car');
        }

        /*
            La règle vit dans 'AvanceePositionService' : clôture, appartenance à la ligne, monotonie,
            horodatage du passage et journal. Le REJEU d'une file hors ligne l'emprunte à l'identique
            — une position calculée de deux façons finirait par diverger, et elle décide aussi bien
            des trajets vendables que des réservations honorées.
        */
        if (!$this->avanceePosition->avancer($voyage, $data->gare)) {
            /*
                Le service rend 'false' quand le car y était déjà : un rejeu. En ligne, c'est une
                erreur de l'utilisateur — il croit avancer et n'avance pas, autant le lui dire.
            */
            throw new BadRequestHttpException('Le car ne peut avancer que vers une gare située plus loin sur la ligne');
        }

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}
