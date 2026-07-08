<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

class RestoreProcessor implements ProcessorInterface
{
    public function __construct(private ProcessorInterface $processor)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if(method_exists($data, 'setDeletedAt')) {
            $data
                ->setDeletedAt(null)
                ->setDeletedBy(null)
                ->setIsEtatdelete(false);;
            // - On a pas besoin de 'flush' il le fait automatiquement
        }
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
