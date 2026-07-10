<?php

namespace App\Entity;

use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\ConfigRemiseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paramètres de REMISE d'une entreprise (singleton par entreprise, auto-créé). Séparé de l'entité
 * Entreprise (comme la fidélité) : anti-abus des remises manuelles. Lu par TicketProcessor à la vente.
 */
#[ORM\Entity(repositoryClass: ConfigRemiseRepository::class)]
class ConfigRemise extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:ConfigRemise'])]
    private ?int $id = null;

    /** Plafond de remise MANUELLE en % du tarif (null = pas de plafond). N'affecte pas la fidélité. */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:ConfigRemise'])]
    private ?int $maxpourcentage = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMaxpourcentage(): ?int
    {
        return $this->maxpourcentage;
    }

    public function setMaxpourcentage(?int $maxpourcentage): static
    {
        $this->maxpourcentage = $maxpourcentage;

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
}
