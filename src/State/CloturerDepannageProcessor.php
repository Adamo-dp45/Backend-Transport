<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Service\CarStatutService;
use App\Entity\Depannage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CloturerDepannageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private CarStatutService $carStatutService
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Depannage $data */

        if($data->getStatut() === DepannageStatus::CLOTURE->value) {
            throw new BadRequestHttpException('Dépannage déjà clôturé');
        }
        /*
            if($depannage->getDetaildepannages()->isEmpty()) {
                throw new BadRequestHttpException('Impossible de clôturer sans détails');
            } -- Pas utile vu que dans mon cas on a au moins un détail
        */
        $data->setStatut(DepannageStatus::CLOTURE->value);
        /*
            - Ou utiliser '$depannage = $context['previous_data']' pour récupérer le dépannage en base de données ensuite '$context['object_to_populate'] = $depannage' pour dire à 'ApiPlatform' que c'est un update sinon il va persister ce qui crée de nouvel enregistrement
        */
        if($data->getCar()) {
            $this->carStatutService->mettreDisponible($data->getCar());
        }
        return $this->processor->process($data, $operation, $uriVariables, $context); 
    }
}
