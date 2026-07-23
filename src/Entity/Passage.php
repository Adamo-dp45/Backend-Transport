<?php

namespace App\Entity;

use App\Repository\PassageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Passage RÉEL d'un voyage à une gare : horodate l'ARRIVÉE et le DÉPART effectifs du car.
 *
 * Le modèle ne connaissait, en temps réel, que le départ de l'origine (Voyage::datedepartreelle) et
 * l'arrivée au terminus (Voyage::datearriveereelle). 'Passage' ajoute les gares INTERMÉDIAIRES et unifie
 * la vue : un enregistrement par (voyage, gare). Alimenté aux points d'exploitation existants (départ,
 * réception, avance commercial, clôture) + une action « repartir » pour le départ d'une gare intermédiaire.
 *
 * Donnée d'exploitation IMMUABLE (comme un mouvement d'inventaire) : pas de soft-delete. Le RETARD se
 * calcule à la lecture (arrivée réelle − heure de passage prévue, cf. ReservationEcheanceService) — il
 * dépend d'un voyage précis, donc il n'est pas porté par l'entité.
 */
#[ORM\Entity(repositoryClass: PassageRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_passage_voyage_gare', columns: ['voyage_id', 'gare_id'])]
class Passage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Passage', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'passages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Passage'])]
    private ?Voyage $voyage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Passage', 'read:Voyage'])]
    private ?Gare $gare = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read:Passage', 'read:Voyage'])]
    private ?\DateTimeImmutable $arriveeReelle = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read:Passage', 'read:Voyage'])]
    private ?\DateTimeImmutable $departReelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Temps d'arrêt du car à cette gare (minutes entre arrivée et départ réels), ou null si l'un des
     * deux manque (origine sans arrivée, terminus sans départ, ou passage en cours).
     */
    #[Groups(['read:Passage', 'read:Voyage'])]
    public function getTempsArretMinutes(): ?int
    {
        if ($this->arriveeReelle === null || $this->departReelle === null) {
            return null;
        }

        return (int) round(($this->departReelle->getTimestamp() - $this->arriveeReelle->getTimestamp()) / 60);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): static
    {
        $this->voyage = $voyage;

        return $this;
    }

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }

    public function getArriveeReelle(): ?\DateTimeImmutable
    {
        return $this->arriveeReelle;
    }

    public function setArriveeReelle(?\DateTimeImmutable $arriveeReelle): static
    {
        $this->arriveeReelle = $arriveeReelle;

        return $this;
    }

    public function getDepartReelle(): ?\DateTimeImmutable
    {
        return $this->departReelle;
    }

    public function setDepartReelle(?\DateTimeImmutable $departReelle): static
    {
        $this->departReelle = $departReelle;

        return $this;
    }

    public function getIdentreprise(): ?int
    {
        return $this->identreprise;
    }

    public function setIdentreprise(?int $identreprise): static
    {
        $this->identreprise = $identreprise;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
