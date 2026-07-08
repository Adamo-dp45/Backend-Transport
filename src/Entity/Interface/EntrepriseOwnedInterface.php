<?php

namespace App\Entity\Interface;

interface EntrepriseOwnedInterface
{
    public function getIdentreprise(): ?int;

    public function setIdentreprise(?int $identreprise): static;

    public function getCreatedBy(): ?int;

    public function setCreatedBy(?int $createdBy): static;

    public function getUpdatedBy(): ?int;

    public function setUpdatedBy(?int $updatedBy): static;

}