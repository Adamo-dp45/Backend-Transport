<?php

namespace App\Entity;

use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\ConfigRecetteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paramètres de RECETTE / chiffre d'affaires d'une entreprise (singleton par entreprise, auto-créé).
 * Permet d'adapter la composition du CA aux besoins de chaque compagnie. Séparé de l'entité Entreprise
 * (comme remise/fidélité/réservation).
 */
#[ORM\Entity(repositoryClass: ConfigRecetteRepository::class)]
class ConfigRecette extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:ConfigRecette'])]
    private ?int $id = null;

    /**
     * Si true, les revenus des COURRIERS sont exclus de TOUTES les recettes composites (CA global, bénéfice,
     * recette par gare/ligne, dashboards). Les stats courrier restent visibles, mais le courrier ne compte
     * pas dans le chiffre d'affaires. Défaut false = comportement historique (courriers inclus).
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['read:ConfigRecette'])]
    private bool $courriershorsca = false;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isCourriershorsca(): bool
    {
        return $this->courriershorsca;
    }

    public function setCourriershorsca(bool $courriershorsca): static
    {
        $this->courriershorsca = $courriershorsca;

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
