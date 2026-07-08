<?php

namespace App\Domain\Service;

use App\Entity\Activite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Journalise « qui a fait quoi » : crée une ligne 'Activite'. On se contente de persist() — le flush
 * est porté par le processor appelant (appeler log() AVANT son flush final). Scopé à l'entreprise de
 * l'acteur courant ; ne loggue rien hors contexte utilisateur (ex. CLI).
 */
class ActiviteLogger
{
    // Types d'événements (pour icône/filtre côté front)
    public const VOYAGE_CAR = 'VOYAGE_CAR';
    public const VOYAGE_COMMERCIAL = 'VOYAGE_COMMERCIAL';
    public const VOYAGE_POSITION = 'VOYAGE_POSITION';
    public const VOYAGE_RECEPTION = 'VOYAGE_RECEPTION';
    public const VOYAGE_DEPART = 'VOYAGE_DEPART';
    public const VOYAGE_CLOTURE = 'VOYAGE_CLOTURE';
    public const VOYAGE_PERSONNEL = 'VOYAGE_PERSONNEL';

    // Billetterie / courrier / bagage — événements critiques (annulation, suppression, perte, livraison)
    public const TICKET_ANNULE = 'TICKET_ANNULE';
    public const TICKET_REPORTE = 'TICKET_REPORTE';
    public const TICKET_SUPPRIME = 'TICKET_SUPPRIME';
    public const COURRIER_ANNULE = 'COURRIER_ANNULE';
    public const COURRIER_LIVRE = 'COURRIER_LIVRE';
    public const COURRIER_PERDU = 'COURRIER_PERDU';
    public const COLIS_PERDU = 'COLIS_PERDU';
    public const BAGAGE_PERDU = 'BAGAGE_PERDU';
    public const BAGAGE_ANNULE = 'BAGAGE_ANNULE';

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    )
    {
    }

    public function log(string $type, string $libelle, ?string $cibletype = null, ?int $cibleid = null): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return; // pas de contexte utilisateur → rien à tracer
        }
        $entrepriseId = $user->getEntreprise()?->getId();
        if ($entrepriseId === null) {
            return;
        }
        $activite = (new Activite())
            ->setType($type)
            ->setLibelle(mb_substr($libelle, 0, 255))
            ->setCibletype($cibletype)
            ->setCibleid($cibleid)
            ->setAuteur($user)
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId())
        ;
        $this->em->persist($activite);
    }

    /** Raccourci pour un événement rattaché à un voyage. */
    public function voyage(string $type, string $libelle, int $voyageId): void
    {
        $this->log($type, $libelle, 'Voyage', $voyageId);
    }
}
