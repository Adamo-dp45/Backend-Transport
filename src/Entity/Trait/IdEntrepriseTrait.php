<?php

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait IdEntrepriseTrait
{
    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getIdentreprise(): ?int
    {
        return $this->identreprise;
    }

    public function setIdentreprise(?int $identreprise): static
    {
        $this->identreprise = $identreprise;

        return $this;
    }
}