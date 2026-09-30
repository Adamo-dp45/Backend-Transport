<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\SessioncaisseService;
use App\Entity\Dto\OuvertureCaisseInput;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * L'OUVERTURE d'une caisse à la prise de poste, avec le fonds avancé par le chef de gare.
 *
 * !! LA CAISSE EST TOUJOURS CELLE DE L'ACTEUR. Ni l'agent, ni la gare ne s'envoient : on ouvre SA
 * caisse, jamais celle d'un collègue. Les laisser entrer offrirait à qui a le droit d'ouvrir la
 * sienne celui d'ouvrir un tiroir au nom d'un autre — et un écart, au soir, désignerait alors une
 * personne qui n'a jamais touché l'argent.
 *
 * Toute l'écriture vit dans 'SessioncaisseService', avec son verrou : c'est le même service qui
 * ouvre la caisse à la première vente quand l'agent a oublié de le faire, et deux chemins d'écriture
 * pour un même objet finissent toujours par diverger.
 */
class OuvrirCaisseProcessor implements ProcessorInterface
{
    public function __construct(
        private SessioncaisseService $sessioncaisseService,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var OuvertureCaisseInput $data */
        /** @var User $user */
        $user = $this->security->getUser();

        /*
            Pas d'appel au processor de persistance derrière : le service a déjà écrit et flushé —
            il le doit, l'index unique qui garantit « une seule caisse ouverte par agent » ne joue
            qu'une fois la ligne en base. Rendre l'entité suffit à ApiPlatform pour la sérialiser.
        */
        return $this->sessioncaisseService->ouvrirManuellement($user, (int) $data->fondsouverture);
    }
}
