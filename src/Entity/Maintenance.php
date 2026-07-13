<?php

namespace App\Entity;

use App\Repository\MaintenanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Mode maintenance GLOBAL de la plateforme (singleton, une seule ligne). Piloté par le SUPER ADMIN.
 * Volontairement NON rattaché à une entreprise (EntrepriseOwnedInterface non implémenté) : c'est un
 * réglage plateforme, hors périmètre tenant.
 */
#[ORM\Entity(repositoryClass: MaintenanceRepository::class)]
class Maintenance extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Maintenance'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['read:Maintenance'])]
    private bool $actif = false;

    /** Message affiché aux utilisateurs pendant la maintenance (optionnel). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['read:Maintenance'])]
    private ?string $message = null;

    /** Horodatage de la dernière activation. */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Maintenance'])]
    private ?\DateTimeImmutable $depuis = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getDepuis(): ?\DateTimeImmutable
    {
        return $this->depuis;
    }

    public function setDepuis(?\DateTimeImmutable $depuis): static
    {
        $this->depuis = $depuis;

        return $this;
    }
}
