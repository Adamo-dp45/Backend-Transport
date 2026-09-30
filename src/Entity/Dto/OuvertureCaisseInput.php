<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entrée d'une ouverture de caisse À LA MAIN : le fonds avancé par le chef de gare.
 *
 * L'ouverture explicite existe pour UNE raison, le FONDS : sans elle, toute caisse naîtrait de la
 * première vente avec un fonds à zéro ('SessioncaisseService'), et la monnaie remise au guichet le
 * matin apparaîtrait le soir comme un excédent inexpliqué. Tout le reste — gare, agent, date — se
 * déduit de l'acteur, et n'a donc rien à faire dans cette charge utile : un agent qui pourrait
 * désigner la gare ou le titulaire de la caisse ouvrirait le tiroir d'un collègue.
 */
class OuvertureCaisseInput
{
    /**
     * 'PositiveOrZero' : ouvrir sans fonds est le cas ordinaire d'un guichet qui rend la monnaie
     * sur ses premières ventes. C'est le NÉGATIF qui n'a pas de sens — on n'avance pas une dette.
     */
    #[Groups(['write:OuvertureCaisseInput'])]
    #[Assert\NotNull(message: 'Le fonds de caisse est obligatoire (zéro est une valeur valide)')]
    #[Assert\PositiveOrZero(message: 'Le fonds de caisse ne peut pas être négatif')]
    public ?int $fondsouverture = 0;
}
