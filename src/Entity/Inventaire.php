<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Output\Inventaire\InventaireOutput;
use App\Repository\InventaireRepository;
use App\State\InventaireProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InventaireRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')", /*
        - normalizationContext: ['groups' => ['read:Inventaire', 'read:Base']], -- Ou.. 'normalizationContext: ['groups' => []]' dans l'opération pour qu'il normalize le 'InventaireOutput' sinon utilisé les groups dans le output
    */
    paginationItemsPerPage: 30,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Inventaire')",
            provider: InventaireProvider::class,
            output: InventaireOutput::class,
            openapi: new Operation(
                summary: 'La liste des inventaires',
                description: 'Permet de voir la liste des inventaires',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            provider: InventaireProvider::class,
            output: InventaireOutput::class,
            openapi: new Operation(
                summary: 'L\'inventaire',
                description: 'Permet de voir un inventaire',
                security: [['bearerAuth' => []]]
            )
        ) /*
        - On n'a pas de 'post' vu qu'on ne doit pas manipuler le stock    
    */
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'typemouvement' => 'exact',
    'reference_type' => 'exact',
    'piece.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'datemouvement',
    'quantite',
    'typemouvement',
    'createdAt'
])]
#[ApiFilter(DateFilter::class, properties: ['datemouvement'])]
class Inventaire extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Inventaire'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'inventaires')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Inventaire'])]
    private ?Piece $piece = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Inventaire'])]
    private ?string $typemouvement = null; // Ou.. enum 'Typemouvement'

    #[ORM\Column]
    #[Groups(['read:Inventaire'])]
    private ?int $quantite = null;

    #[ORM\Column]
    #[Groups(['read:Inventaire'])]
    private ?\DateTimeImmutable $datemouvement = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Inventaire'])]
    private ?string $reference_type = null; // Ou.. enum 'Referencetype'

    #[ORM\Column(nullable: true)]
    private ?int $referenceid = null;

    /**
     * Auteur du mouvement de stock (relation directe plutôt qu'un lookup sur 'createdBy'). EAGER : chargé
     * en une requête groupée avec la collection → pas de N+1 dans le provider.
     */
    #[ORM\ManyToOne(fetch: 'EAGER')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read:Inventaire'])]
    private ?User $auteur = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTypemouvement(): ?string
    {
        return $this->typemouvement;
    }

    public function setTypemouvement(string $typemouvement): static
    {
        $this->typemouvement = $typemouvement;

        return $this;
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

    public function getDatemouvement(): ?\DateTimeImmutable
    {
        return $this->datemouvement;
    }

    public function setDatemouvement(\DateTimeImmutable $datemouvement): static
    {
        $this->datemouvement = $datemouvement;

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

    public function getReferenceType(): ?string
    {
        return $this->reference_type;
    }

    public function setReferenceType(string $reference_type): static
    {
        $this->reference_type = $reference_type;

        return $this;
    }

    public function getReferenceid(): ?int
    {
        return $this->referenceid;
    }

    public function setReferenceid(?int $referenceid): static
    {
        $this->referenceid = $referenceid;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }
}
