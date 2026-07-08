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
use App\Repository\ModelvehiculeRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ModelvehiculeRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle'],
    message: 'Le modèle de véhicule existe déjà dans votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Modelvehicule', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Modelvehicule']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Modelvehicule') or is_granted('VOIR', 'Car')",
            openapi: new Operation(
                summary: 'Liste des modèles de véhicule',
                description: 'Permet de voir la liste des modèles de véhicule',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le modèle de véhicule',
                description: 'Permet de voir un modèle de véhicule',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Modelvehicule')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création du modèle de véhicule',
                description: 'Permet de créer un modèle de véhicule',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification du modèle de véhicule',
                description: 'Permet de modifier un modèle de véhicule',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/modelvehicules/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du modèle de véhicule',
                description: 'Permet de mettre un modèle de véhicule en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Modelvehicule extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Modelvehicule', 'read:Car'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Modelvehicule', 'write:Modelvehicule', 'read:Car'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Car>
     */
    #[ORM\OneToMany(targetEntity: Car::class, mappedBy: 'modelvehicule')]
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
            $car->setModelvehicule($this);
        }

        return $this;
    }

    public function removeCar(Car $car): static
    {
        if ($this->cars->removeElement($car)) {
            // set the owning side to null (unless already changed)
            if ($car->getModelvehicule() === $this) {
                $car->setModelvehicule(null);
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
                'Le modèle de véhicule est liée à %d véhicule(s) actif(s).',
                $carsNotDeleted->count()
            );
        }

        return $errors;
    }
}
