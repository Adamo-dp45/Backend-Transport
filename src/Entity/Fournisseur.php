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
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\FournisseurRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FournisseurRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle', 'contact', 'email', 'adresse', 'pays'],
    message: 'L\'enregistrement existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Fournisseur', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Fournisseur']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Fournisseur') or is_granted('VOIR', 'Approvisionnement')",
            openapi: new Operation(
                summary: 'La liste des fournisseurs',
                description: 'Permet de voir la liste des fournisseurs',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le fournisseur',
                description: 'Permet de voir un fournisseur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Fournisseur')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un fournisseur',
                description: 'Permet de créer un fournisseur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un fournisseur',
                description: 'Permet de modifier un fournisseur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/fournisseurs/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un fournisseur',
                description: 'Permet de mettre un fournisseur en corbeille',
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
    'contact' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'libelle',
    'createdAt'
])]
class Fournisseur extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Fournisseur', 'read:Approvisionnement'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Fournisseur', 'write:Fournisseur', 'read:Approvisionnement'])]
    #[Assert\Length(min: 1)]
    private ?string $libelle = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Fournisseur', 'write:Fournisseur', 'read:Approvisionnement'])]
    #[Assert\Length(min: 3)]
    private ?string $contact = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Fournisseur', 'write:Fournisseur', 'read:Approvisionnement'])]
    #[Assert\Email()]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Fournisseur', 'write:Fournisseur', 'read:Approvisionnement'])]
    #[Assert\Length(min: 2)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Fournisseur', 'write:Fournisseur', 'read:Approvisionnement'])]
    #[Assert\Length(min: 2)]
    private ?string $pays = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Approvisionnement>
     */
    #[ORM\OneToMany(targetEntity: Approvisionnement::class, mappedBy: 'fournisseur')]
    private Collection $approvisionnements;

    public function __construct()
    {
        $this->approvisionnements = new ArrayCollection();
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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(string $pays): static
    {
        $this->pays = $pays;

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
     * @return Collection<int, Approvisionnement>
     */
    public function getApprovisionnements(): Collection
    {
        return $this->approvisionnements;
    }

    public function addApprovisionnement(Approvisionnement $approvisionnement): static
    {
        if (!$this->approvisionnements->contains($approvisionnement)) {
            $this->approvisionnements->add($approvisionnement);
            $approvisionnement->setFournisseur($this);
        }

        return $this;
    }

    public function removeApprovisionnement(Approvisionnement $approvisionnement): static
    {
        if ($this->approvisionnements->removeElement($approvisionnement)) {
            // set the owning side to null (unless already changed)
            if ($approvisionnement->getFournisseur() === $this) {
                $approvisionnement->setFournisseur(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $approvisionnementsNotDeleted = $this->approvisionnements->filter(
            fn(Approvisionnement $v) => $v->getDeletedAt() === null
        );

        if(!$approvisionnementsNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le fournisseurs est liée à %d approvisionnement(s) actif(s).',
                $approvisionnementsNotDeleted->count()
            );
        }

        return $errors;
    }

    /* Le count collection et l'avantage est que 'Doctrine' ne charge pas la collection mais 'COUNT(*)'
     */
    #[Groups(['read:Fournisseur'])]
    public function getApprovisionnementsCount(): int
    {
        return $this->approvisionnements->count();
    }

    #[Groups(['read:Fournisseur'])]
    public function getLastApprovisionnement()
    {
        return $this->approvisionnements->last();
    }

    #[Groups(['read:Fournisseur'])]
    public function getFirstApprovisionnement()
    {
        return $this->approvisionnements->first();
    }

}
