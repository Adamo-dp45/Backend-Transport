<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\MarqueRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: MarqueRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle'],
    message: 'L\'enregistrement existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Marque', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Marque']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
           security: "is_granted('VOIR', 'Marque') or is_granted('VOIR', 'Car')",
            openapi: new Operation(
                summary: 'Liste des marques',
                description: 'Permet de voir la liste des marques',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'La marque',
                description: 'Permet de voir une marque',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Marque')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création de la marque',
                description: 'Permet de créer une marque',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification de la marque',
                description: 'Permet de modifier une marque',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/marques/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille de la marque',
                description: 'Permet de mettre une marque en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Marque extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Marque', 'read:Car'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['write:Marque', 'read:Marque', 'read:Car', 'read:Depannage', 'read:Voyage'])]
    #[Assert\NotBlank()]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Car>
     */
    #[ORM\OneToMany(targetEntity: Car::class, mappedBy: 'marque')]
    private Collection $cars;

    public function __construct()
    {
        $this->cars = new ArrayCollection();
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
     * @return Collection<int, Car>
     */
    public function getCars(): Collection
    {
        return $this->cars;
    }

    public function addCar(Car $car): static
    {
        if (!$this->cars->contains($car)) {
            $this->cars->add($car);
            $car->setMarque($this);
        }

        return $this;
    }

    public function removeCar(Car $car): static
    {
        if ($this->cars->removeElement($car)) {
            // set the owning side to null (unless already changed)
            if ($car->getMarque() === $this) {
                $car->setMarque(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $carsNotDeleted = $this->cars->filter(
            fn(Car $v) => $v->getDeletedAt() === null
        );

        if(!$carsNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'La marque est liée à %d car(s) actif(s).',
                $carsNotDeleted->count()
            );
        }

        return $errors;
    }
}
