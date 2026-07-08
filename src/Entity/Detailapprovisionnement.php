<?php

namespace App\Entity;

use App\Repository\DetailapprovisionnementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DetailapprovisionnementRepository::class)]
class Detailapprovisionnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['read:Approvisionnement'])]
    private ?int $quantite = null;

    #[ORM\Column]
    #[Groups(['read:Approvisionnement'])]
    private ?int $prixunitaire = null; // Vu que le prix d'une pièce change dans le temps

    #[ORM\ManyToOne(inversedBy: 'detailapprovisionnements', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] // --
    private ?Approvisionnement $approvisionnement = null;

    #[ORM\ManyToOne(inversedBy: 'detailapprovisionnements')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Approvisionnement'])]
    private ?Piece $piece = null;

    #[ORM\Column(type: 'bigint', nullable: true)] // BIGINT : un total (prix × quantité) peut dépasser la limite INT (~2,1 milliards) en FCFA
    #[Groups(['read:Approvisionnement'])]
    private ?int $couttotal = null;

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

    public function getPrixunitaire(): ?int
    {
        return $this->prixunitaire;
    }

    public function setPrixunitaire(int $prixunitaire): static
    {
        $this->prixunitaire = $prixunitaire;

        return $this;
    }

    public function getApprovisionnement(): ?Approvisionnement
    {
        return $this->approvisionnement;
    }

    public function setApprovisionnement(?Approvisionnement $approvisionnement): static
    {
        $this->approvisionnement = $approvisionnement;

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

    public function getCouttotal(): ?int
    {
        return $this->couttotal;
    }

    public function setCouttotal(?int $couttotal): static
    {
        $this->couttotal = $couttotal;

        return $this;
    }
}
