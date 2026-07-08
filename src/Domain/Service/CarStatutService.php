<?php

namespace App\Domain\Service;

use App\Domain\Enum\CarStatus;
use App\Entity\Car;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CarStatutService
{
    /**
     * Permet de vérifier que le car peut être affecté à un voyage
     */
    public function verifierDisponibiliteVoyage(Car $car): void
    {
        if($car->getEtat() === CarStatus::EN_PANNE->value) {
            throw new BadRequestHttpException('Ce véhicule est en panne et ne peut pas être affecté à un voyage');
        }

        if($car->getEtat() === CarStatus::EN_VOYAGE->value) {
            throw new BadRequestHttpException('Ce véhicule est déjà en voyage et ne peut pas être affecté à un nouveau voyage');
        }
    }

    /**
     * Permet de vérifier que le car peut être affecté à un dépannage
     */
    public function verifierDisponibiliteDepannage(Car $car): void
    {
        /*
            if($car->getEtat() === CarStatus::EN_VOYAGE->value) {
                throw new BadRequestHttpException('Ce véhicule est en voyage et ne peut pas être mis en dépannage');
            }
        */
        if($car->getEtat() === CarStatus::EN_PANNE->value) {
            throw new BadRequestHttpException('Ce véhicule est déjà en dépannage et ne peut pas être affecté à un nouveau dépannage tant que celui-ci n\'est pas clôturé ou annulé');
        }
    }

    public function mettreEnVoyage(Car $car): void
    {
        $car->setEtat(CarStatus::EN_VOYAGE->value); /*
            - '$this->em->persist($car)' -- Pas nécéssaire
        */
    }

    public function mettreEnPanne(Car $car): void
    {
        $car->setEtat(CarStatus::EN_PANNE->value);
    }

    public function mettreDisponible(Car $car): void
    {
        $car->setEtat(CarStatus::DISPONIBLE->value);
    }
}