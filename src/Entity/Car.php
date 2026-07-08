<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\CarStatus;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\CarRepository;
use App\State\CarProcessor;
use App\State\SoftDeleteProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CarRepository::class)]
#[UniquePerEntreprise(
    fields: ['matricule'],
    message: 'Le matricule existe déjà pour un car'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Car', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Car']],
    paginationEnabled: false, 
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Car') or is_granted('VOIR', 'Voyage') or is_granted('VOIR', 'Depannage')",
            openapi: new Operation(
                summary: 'La liste des cars',
                description: 'Permet de voir la liste des cars',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le car',
                description: 'Permet de voir un car',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Car')",
            processor: CarProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un car',
                description: 'Permet de créer un car',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: CarProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un car',
                description: 'Permet de modifier un car',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/cars/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un car',
                description: 'Permet de mettre un car en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'etat' => 'exact',
    'matricule' => 'partial' // recherche autocomplete par matricule (resource cars / cars_disponibles)
])]
class Car extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Car', 'read:Voyage', 'read:Personnel', 'read:Depannage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Car', 'write:Car', 'read:Voyage', 'read:Personnel'])]
    private ?string $matricule = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Car', 'read:Depannage', 'write:Car', 'read:Voyage'])]
    private ?int $nbrsiege = null;

    #[ORM\Column]
    #[Groups(['read:Car', 'read:Depannage', 'write:Car', 'read:Voyage'])]
    private ?\DateTimeImmutable $datearrivee = null;

    #[ORM\Column(length: 20)]
    #[Groups(['read:Depannage', 'read:Car', 'write:Car'])]
    private ?string $etat = CarStatus::DISPONIBLE->value;

    #[ORM\ManyToOne(inversedBy: 'cars')]
    #[Groups(['read:Car', 'write:Car', 'read:Depannage', 'read:Voyage'])]
    private ?Marque $marque = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Depannage>
     */
    #[ORM\OneToMany(targetEntity: Depannage::class, mappedBy: 'car')]
    private Collection $depannages;

    /**
     * @var Collection<int, Voyage>
     */
    #[ORM\OneToMany(targetEntity: Voyage::class, mappedBy: 'car')]
    private Collection $voyages;

    #[ORM\ManyToOne(inversedBy: 'cars')]
    #[Groups(['read:Car', 'write:Car', 'read:Depannage'])]
    private ?Typevehicule $typevehicule = null;

    #[ORM\ManyToOne(inversedBy: 'cars')]
    #[Groups(['read:Car', 'write:Car', 'read:Depannage'])]
    private ?Modelvehicule $modelvehicule = null;

    /**
     * @var Collection<int, Siege>
     */
    #[ORM\OneToMany(targetEntity: Siege::class, mappedBy: 'car', orphanRemoval: true)] // cascade: ['persist', 'remove']
    #[Groups(['read:Car'])]
    private Collection $sieges;

    #[ORM\Column] // nullable: true
    #[Groups(['read:Car', 'write:Car', 'read:Voyage'])]
    private ?int $sieges_gauche = null;

    #[ORM\Column] // nullable: true
    #[Groups(['read:Car', 'write:Car', 'read:Voyage'])]
    private ?int $sieges_droite = null;

    public function __construct()
    {
        $this->depannages = new ArrayCollection();
        $this->voyages = new ArrayCollection();
        $this->sieges = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getNbrsiege(): ?int
    {
        return $this->nbrsiege;
    }

    public function setNbrsiege(?int $nbrsiege): static
    {
        $this->nbrsiege = $nbrsiege;

        return $this;
    }

    public function getDatearrivee(): ?\DateTimeImmutable
    {
        return $this->datearrivee;
    }

    public function setDatearrivee(\DateTimeImmutable $datearrivee): static
    {
        $this->datearrivee = $datearrivee;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getMarque(): ?Marque
    {
        return $this->marque;
    }

    public function setMarque(?Marque $marque): static
    {
        $this->marque = $marque;

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
     * @return Collection<int, Depannage>
     */
    public function getDepannages(): Collection
    {
        return $this->depannages;
    }

    public function addDepannage(Depannage $depannage): static
    {
        if (!$this->depannages->contains($depannage)) {
            $this->depannages->add($depannage);
            $depannage->setCar($this);
        }

        return $this;
    }

    public function removeDepannage(Depannage $depannage): static
    {
        if ($this->depannages->removeElement($depannage)) {
            // set the owning side to null (unless already changed)
            if ($depannage->getCar() === $this) {
                $depannage->setCar(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Voyage>
     */
    public function getVoyages(): Collection
    {
        return $this->voyages;
    }

    public function addVoyage(Voyage $voyage): static
    {
        if (!$this->voyages->contains($voyage)) {
            $this->voyages->add($voyage);
            $voyage->setCar($this);
        }

        return $this;
    }

    public function removeVoyage(Voyage $voyage): static
    {
        if ($this->voyages->removeElement($voyage)) {
            // set the owning side to null (unless already changed)
            if ($voyage->getCar() === $this) {
                $voyage->setCar(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $depannagesNotDeleted = $this->depannages->filter(
            fn(Depannage $v) => $v->getDeletedAt() === null
        );

        if(!$depannagesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le car est liée à %d dépannage(s) actif(s)',
                $depannagesNotDeleted->count()
            );
        }

        $voyagesNotDeleted = $this->voyages->filter(
            fn(Voyage $v) => $v->getDeletedAt() === null
        );

        if(!$voyagesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le car est liée à %d voyage(s) actif(s)',
                $voyagesNotDeleted->count()
            );
        }

        return $errors;
    }

    public function getTypevehicule(): ?Typevehicule
    {
        return $this->typevehicule;
    }

    public function setTypevehicule(?Typevehicule $typevehicule): static
    {
        $this->typevehicule = $typevehicule;

        return $this;
    }

    public function getModelvehicule(): ?Modelvehicule
    {
        return $this->modelvehicule;
    }

    public function setModelvehicule(?Modelvehicule $modelvehicule): static
    {
        $this->modelvehicule = $modelvehicule;

        return $this;
    }

    /**
     * @return Collection<int, Siege>
     */
    public function getSieges(): Collection
    {
        return $this->sieges;
    }

    public function addSiege(Siege $siege): static
    {
        if (!$this->sieges->contains($siege)) {
            $this->sieges->add($siege);
            $siege->setCar($this);
        }

        return $this;
    }

    public function removeSiege(Siege $siege): static
    {
        if ($this->sieges->removeElement($siege)) {
            // set the owning side to null (unless already changed)
            if ($siege->getCar() === $this) {
                $siege->setCar(null);
            }
        }

        return $this;
    }

    public function getSiegesGauche(): ?int
    {
        return $this->sieges_gauche;
    }

    public function setSiegesGauche(int $sieges_gauche): static
    {
        $this->sieges_gauche = $sieges_gauche;

        return $this;
    }

    public function getSiegesDroite(): ?int
    {
        return $this->sieges_droite;
    }

    public function setSiegesDroite(int $sieges_droite): static
    {
        $this->sieges_droite = $sieges_droite;

        return $this;
    }

    /* Le count collection et l'avantage est que 'Doctrine' ne charge pas la collection mais 'COUNT(*)'
     */
    #[Groups(['read:Car'])]
    public function getVoyagesCount(): int
    {
        return $this->voyages->count();
    }

    #[Groups(['read:Car'])]
    public function getDepannagesCount(): int
    {
        return $this->depannages->count();
    }
}
