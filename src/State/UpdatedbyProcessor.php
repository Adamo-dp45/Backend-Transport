<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Ticket;
use App\Entity\User;
use App\Security\GareGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UpdatedbyProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private GareGuard $gareGuard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();

        // Modification d'un ticket : réservée à la gare ÉMETTRICE (gare de montée) ; la lecture reste large.
        // De plus, un billet dont le voyage est CLÔTURÉ n'est plus modifiable.
        if($data instanceof Ticket) {
            if ($data->getVoyage()?->getDatearriveereelle() !== null) {
                throw new BadRequestHttpException('Le voyage de ce billet est clôturé : modification impossible');
            }
            $this->gareGuard->assertEstGare($user, $data->getGare(), 'Seule la gare émettrice peut modifier ce ticket');
        }

        if(!$data instanceof EntrepriseOwnedInterface) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }
        $data->setUpdatedBy($user->getId());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
