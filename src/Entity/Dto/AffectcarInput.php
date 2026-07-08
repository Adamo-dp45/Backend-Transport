<?php

namespace App\Entity\Dto;

use App\Entity\Car;
use Symfony\Component\Serializer\Attribute\Groups;

class AffectcarInput
{
    #[Groups(['write:AffectcarInput'])]
    public Car $car;
}