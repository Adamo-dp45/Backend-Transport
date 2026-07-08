<?php

namespace App\Domain\Service;

use App\Entity\Client;
use App\Entity\ProgrammeFidelite;
use App\Repository\ProgrammeFideliteRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique du programme de fidélité « carte à tampons ».
 *
 * L'état est ENTIÈREMENT DÉRIVÉ de l'historique des billets (aucun compteur stocké → aucune dérive) :
 *  - tampons = billets VALIDE, non-récompense, émis depuis l'adhésion ;
 *  - chaque récompense déjà utilisée consomme 'seuil' tampons.
 */
class FideliteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProgrammeFideliteRepository $programmeRepository,
        private TicketRepository $ticketRepository
    )
    {
    }

    /**
     * Programme de l'entreprise, auto-créé avec les valeurs par défaut s'il n'existe pas encore.
     */
    public function getProgramme(int $identreprise): ProgrammeFidelite
    {
        $programme = $this->programmeRepository->findOneByEntreprise($identreprise);
        if ($programme === null) {
            $programme = (new ProgrammeFidelite())->setIdentreprise($identreprise);
            $this->em->persist($programme);
            $this->em->flush();
        }

        return $programme;
    }

    /**
     * État de fidélité d'un client (pour l'affichage et la décision de récompense au guichet).
     *
     * @return array{
     *   membre: bool, cartefidelite: ?string, dateadhesion: ?string, programmeActif: bool,
     *   seuil: int, recompensePourcentage: int, voyagesEligibles: int, stampsDisponibles: int,
     *   progression: int, recompensesGagnees: int, recompenseDisponible: bool
     * }
     */
    public function getStatut(Client $client): array
    {
        $programme = $this->getProgramme($client->getIdentreprise());
        $seuil = max(1, $programme->getSeuil());

        $base = [
            'membre' => $client->isFidelite(),
            'cartefidelite' => $client->getCartefidelite(),
            'dateadhesion' => $client->getDateadhesion()?->format(\DATE_ATOM),
            'programmeActif' => $programme->isActif(),
            'seuil' => $programme->getSeuil(),
            'recompensePourcentage' => $programme->getRecompensePourcentage(),
        ];

        if (!$client->isFidelite()) {
            return $base + [
                'voyagesEligibles' => 0,
                'stampsDisponibles' => 0,
                'progression' => 0,
                'recompensesGagnees' => 0,
                'recompenseDisponible' => false,
            ];
        }

        $voyagesEligibles = $this->ticketRepository->countVoyagesFidelite($client, $client->getDateadhesion());
        $recompensesUtilisees = $this->ticketRepository->countRecompensesFidelite($client);

        $stampsDisponibles = max(0, $voyagesEligibles - ($recompensesUtilisees * $seuil));
        $recompensesGagnees = intdiv($stampsDisponibles, $seuil);

        return $base + [
            'voyagesEligibles' => $voyagesEligibles,
            'stampsDisponibles' => $stampsDisponibles,
            // tampons de la carte EN COURS (0..seuil-1) — la part au-delà d'un multiple du seuil
            'progression' => $stampsDisponibles % $seuil,
            'recompensesGagnees' => $recompensesGagnees,
            'recompenseDisponible' => $programme->isActif() && $recompensesGagnees >= 1,
        ];
    }

    /**
     * Une récompense est-elle utilisable MAINTENANT pour ce client ? (garde pour la vente, Phase 2)
     */
    public function recompenseDisponible(Client $client): bool
    {
        return $this->getStatut($client)['recompenseDisponible'];
    }
}
