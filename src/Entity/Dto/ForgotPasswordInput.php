<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['write:ForgotPasswordInput'])]
    public string $email;

    #[Assert\NotBlank]
    /*
        #[Assert\Url( 
            protocols: ['https'], -- 'http' pour le developpement
            message: 'L\'URL doit être une URL HTTPS valide'
        )]
    */
    #[Groups(['write:ForgotPasswordInput'])]
    public string $frontResetUrl;
}