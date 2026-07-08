<?php

namespace App\Entity;

use App\Repository\DetaildepannageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DetaildepannageRepository::class)]
class Detaildepannage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Depannage'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['read:Depannage'])]
    private ?int $quantite = null;

    #[ORM\ManyToOne(inversedBy: 'detaildepannages', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)] // Ou.. 'cascade: ['persist', 'remove']' au niveau de 'Depannage'
    private ?Depannage $depannage = null;

    #[ORM\ManyToOne(inversedBy: 'detaildepannages')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Depannage'])]
    private ?Piece $piece = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Depannage'])]
    private ?int $prixunitaire = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getDepannage(): ?Depannage
    {
        return $this->depannage;
    }

    public function setDepannage(?Depannage $depannage): static
    {
        $this->depannage = $depannage;

        return $this;
    }

    public function getPiece(): ?Piece
    {
        return $this->piece;
    }

    public function setPiece(?Piece $piece): static
    {
        $this->piece = $piece;

        return $this;
    }

    public function getPrixunitaire(): ?int
    {
        return $this->prixunitaire;
    }

    public function setPrixunitaire(?int $prixunitaire): static
    {
        $this->prixunitaire = $prixunitaire;

        return $this;
    }
}
