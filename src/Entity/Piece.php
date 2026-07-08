<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Dto\AjustementstockInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\PieceRepository;
use App\State\AjustementstockProcessor;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PieceRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle', 'typepiece', 'marquepiece', 'model'], 
    message: 'L\'enregistrement existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Piece', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Piece']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true, // On autorise l'utilisateur à pouvoir choisir le nombre de page qu'il veut ce qui lui permet de taper '&itemsPerPage=100' dans l'url
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Piece')",
            openapi: new Operation(
                summary: 'La liste des pièces',
                description: 'Permet de voir la liste des pièces',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'La pièce',
                description: 'Permet de voir une pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Piece')",
            /* -- Si on veut utiliser un 'input'
                input: PieceInput::class,
                processor: PieceProcessor::class,
                denormalizationContext: ['groups' => ['write:PieceInput']]
            */
            denormalizationContext: ['groups' => ['write:Piece', 'create:Piece']],
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Créer une pièce',
                description: 'Permet de créer une pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modifier une pièce',
                description: 'Permet de modifier une pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/pieces/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'une pièce',
                description: 'Permet de mettre une pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)", // Ou.. 'is_granted('AJUSTER', object)'
            uriTemplate: '/pieces/{id}/ajuster',
            requirements: ['id' => '\d+'],
            input: AjustementstockInput::class,
            denormalizationContext: ['groups' => ['write:AjustementstockInput']],
            processor: AjustementstockProcessor::class,
            openapi: new Operation(
                summary: 'Ajuster le stock d\'une pièce',
                description: 'Permet d\'ajuster le stock d\'une pièce',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'libelle' => 'partial',
    'typepiece.id' => 'exact',
    'marquepiece.id' => 'exact',
    'model.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'libelle',
    'prixunitaire',
    'stockinitial',
    'createdAt'
])]
class Piece extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard // On peut s'en passé vu qu'on a déjà des détails.. qui contienent ce qu'on veut sion pour l'inventaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Piece', 'read:Approvisionnement', 'read:Depannage', 'read:Inventaire'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Piece', 'write:Piece', 'read:Depannage', 'read:Approvisionnement', 'read:Inventaire'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(inversedBy: 'pieces')] // Si non 'nullable' - onDelete: 'RESTRICT'
    #[Groups(['read:Piece', 'write:Piece'])]
    private ?Typepiece $typepiece = null;

    #[ORM\ManyToOne(inversedBy: 'pieces')] // --
    #[Groups(['read:Piece', 'write:Piece'])]
    private ?Marquepiece $marquepiece = null;

    #[ORM\ManyToOne(inversedBy: 'pieces')] // --
    #[Groups(['read:Piece', 'write:Piece'])]
    private ?Model $model = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailapprovisionnement>
     */
    #[ORM\OneToMany(targetEntity: Detailapprovisionnement::class, mappedBy: 'piece')]
    private Collection $detailapprovisionnements;

    /**
     * @var Collection<int, Detaildepannage>
     */
    #[ORM\OneToMany(targetEntity: Detaildepannage::class, mappedBy: 'piece')]
    private Collection $detaildepannages;

    /**
     * @var Collection<int, Inventaire>
     */
    #[ORM\OneToMany(targetEntity: Inventaire::class, mappedBy: 'piece')]
    private Collection $inventaires;

    #[ORM\Column]
    #[Groups(['read:Piece', 'create:Piece'])] /*
        - On ne modifie pas directement le 'stock' car il doit l'être par 'Approvisionnement', 'Dépannage'..
    */
    private ?int $stockinitial = null; // Le renommé en 'stock'

    #[ORM\Column]
    #[Groups(['read:Piece', 'write:Piece'])]
    private ?int $prixunitaire = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Piece'])]
    private ?int $seuilstock = 5;

    #[ORM\ManyToOne]
    #[Groups(['read:Piece', 'write:Piece'])]
    private ?MediaObject $image = null; // Ou 'seuil_alerte'

    public function __construct()
    {
        $this->detailapprovisionnements = new ArrayCollection();
        $this->detaildepannages = new ArrayCollection();
        $this->inventaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getTypepiece(): ?Typepiece
    {
        return $this->typepiece;
    }

    public function setTypepiece(?Typepiece $typepiece): static
    {
        $this->typepiece = $typepiece;

        return $this;
    }

    public function getMarquepiece(): ?Marquepiece
    {
        return $this->marquepiece;
    }

    public function setMarquepiece(?Marquepiece $marquepiece): static
    {
        $this->marquepiece = $marquepiece;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): static
    {
        $this->model = $model;

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

    /**
     * @return Collection<int, Detailapprovisionnement>
     */
    public function getDetailapprovisionnements(): Collection
    {
        return $this->detailapprovisionnements;
    }

    public function addDetailapprovisionnement(Detailapprovisionnement $detailapprovisionnement): static
    {
        if (!$this->detailapprovisionnements->contains($detailapprovisionnement)) {
            $this->detailapprovisionnements->add($detailapprovisionnement);
            $detailapprovisionnement->setPiece($this);
        }

        return $this;
    }

    public function removeDetailapprovisionnement(Detailapprovisionnement $detailapprovisionnement): static
    {
        if ($this->detailapprovisionnements->removeElement($detailapprovisionnement)) {
            // set the owning side to null (unless already changed)
            if ($detailapprovisionnement->getPiece() === $this) {
                $detailapprovisionnement->setPiece(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Detaildepannage>
     */
    public function getDetaildepannages(): Collection
    {
        return $this->detaildepannages;
    }

    public function addDetaildepannage(Detaildepannage $detaildepannage): static
    {
        if (!$this->detaildepannages->contains($detaildepannage)) {
            $this->detaildepannages->add($detaildepannage);
            $detaildepannage->setPiece($this);
        }

        return $this;
    }

    public function removeDetaildepannage(Detaildepannage $detaildepannage): static
    {
        if ($this->detaildepannages->removeElement($detaildepannage)) {
            // set the owning side to null (unless already changed)
            if ($detaildepannage->getPiece() === $this) {
                $detaildepannage->setPiece(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Inventaire>
     */
    public function getInventaires(): Collection
    {
        return $this->inventaires;
    }

    public function addInventaire(Inventaire $inventaire): static
    {
        if (!$this->inventaires->contains($inventaire)) {
            $this->inventaires->add($inventaire);
            $inventaire->setPiece($this);
        }

        return $this;
    }

    public function removeInventaire(Inventaire $inventaire): static
    {
        if ($this->inventaires->removeElement($inventaire)) {
            // set the owning side to null (unless already changed)
            if ($inventaire->getPiece() === $this) {
                $inventaire->setPiece(null);
            }
        }

        return $this;
    }

    public function getStockinitial(): ?int
    {
        return $this->stockinitial;
    }

    public function setStockinitial(int $stockinitial): static
    {
        $this->stockinitial = $stockinitial;

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

    public function getSeuilstock(): ?int
    {
        return $this->seuilstock;
    }

    public function setSeuilstock(?int $seuilstock): static
    {
        $this->seuilstock = $seuilstock;

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $inventairesNotDeleted = $this->inventaires->filter(
            fn(Inventaire $v) => $v->getDeletedAt() === null
        );

        if(!$inventairesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'La pièce est liée à %d inventaire(s) actif(s).',
                $inventairesNotDeleted->count()
            );
        }

        return $errors;
    }

    public function getImage(): ?MediaObject
    {
        return $this->image;
    }

    public function setImage(?MediaObject $image): static
    {
        $this->image = $image;

        return $this;
    }
}
