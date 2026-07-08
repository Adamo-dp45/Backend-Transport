<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordInput
{
    #[Assert\NotBlank]
    #[Groups(['write:ResetPasswordInput'])]
    public string $token;

    #[Assert\NotBlank]
    #[Assert\Length(min: 4)]
    #[Groups(['write:ResetPasswordInput'])]
    public string $password;
}