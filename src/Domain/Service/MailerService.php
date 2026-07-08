<?php

namespace App\Domain\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private ParameterBagInterface $params
    )
    {
    }

    public function send(string $to, string $link)
    {
        $email = (new Email())
            ->from("{$this->params->get('app.mailer.name')} <{$this->params->get('app.mailer.email')}>")
            ->to($to)
            ->subject('Réinitialisation de votre mot de passe')
            ->html("
                <h2>Réinitialisation du mot de passe</h2>
                <p>Cliquez sur le lien ci-dessous :</p>
                <a href='$link'>Réinitialiser mon mot de passe</a>
                <p>Ce lien expire dans 1 heure.</p>
            ")
        ;
        $this->mailer->send($email);
    }
}