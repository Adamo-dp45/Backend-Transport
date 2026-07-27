<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        // Fuseau horaire applicatif FIXE, indépendant du php.ini de l'hôte.
        // Les datetime « prévus » (datedepartprevue…) sont saisis en heure murale et stockés
        // en DATETIME naïf (sans fuseau). À la lecture, Doctrine les réhydrate dans le fuseau
        // PHP par défaut du serveur, puis l'API les sérialise AVEC cet offset. Si l'hôte n'est
        // pas en UTC (ex. prod en Europe/Paris), le même « 15:00 » stocké ressort en « 15:00+02:00 »
        // et s'affiche 13:00 dans un navigateur à UTC+0 (Abidjan). La compagnie opère en UTC+0,
        // donc on épingle UTC : comportement identique en local et en production.
        date_default_timezone_set('UTC');

        parent::__construct($environment, $debug);
    }
}
