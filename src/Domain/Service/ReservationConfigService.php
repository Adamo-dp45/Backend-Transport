<?php

namespace App\Domain\Service;

use App\Entity\ParametreReservation;
use App\Repository\ParametreReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accès aux paramètres de réservation d'une entreprise (auto-créés avec les défauts si absents).
 */
class ReservationConfigService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParametreReservationRepository $repository
    )
    {
    }

    /**
     * @var array<int, ParametreReservation> mémo par entreprise, valable le temps de la REQUÊTE
     *      (les services Symfony ne survivent pas au-delà).
     */
    private array $memo = [];

    public function getParametre(int $identreprise): ParametreReservation
    {
        /*
            MÉMO indispensable : ce paramètre est lu à chaque calcul d'échéance, donc plusieurs fois
            par voyage ET par arrêt dans les listes (départs réservables, départs de report). Sans lui,
            'findOneBy' repartait en base à chaque appel — mesuré : une requête par couple
            voyage/arrêt, soit des centaines pour un simple affichage de sélecteur.

            Un réglage d'entreprise ne change pas en cours de requête ; s'il est modifié
            (MeParametreReservationProcessor), c'est dans une autre requête, avec un service neuf.
        */
        if (isset($this->memo[$identreprise])) {
            return $this->memo[$identreprise];
        }

        $parametre = $this->repository->findOneByEntreprise($identreprise);
        if ($parametre === null) {
            $parametre = (new ParametreReservation())->setIdentreprise($identreprise);
            $this->em->persist($parametre);
            $this->em->flush();
        }

        return $this->memo[$identreprise] = $parametre;
    }
}
