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
use App\Repository\TypepanneRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypepanneRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle'],
    message: 'Le type de panne existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Typepanne', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Typepanne']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Typepanne') or is_granted('VOIR', 'Depannage')",
            openapi: new Operation(
                summary: 'Liste des types de panne',
                description: 'Permet de voir la liste des types de panne',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le type de panne',
                description: 'Permet de voir un type de panne',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Typepanne')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un type de panne',
                description: 'Permet de créer un type de panne',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification du type de panne',
                description: 'Permet de modifier un type de panne',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/typepannes/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du type de panne',
                description: 'Permet de mettre un type de panne en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Typepanne extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Typepanne', 'read:Depannage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    #[Groups(['read:Typepanne', 'write:Typepanne', 'read:Depannage'])]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Depannage>
     */
    #[ORM\OneToMany(targetEntity: Depannage::class, mappedBy: 'typepanne')]
    private Collection $depannages;

    public function __construct()
    {
        $this->depannages = new ArrayCollection();
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
            $depannage->setTypepanne($this);
        }

        return $this;
    }

    public function removeDepannage(Depannage $depannage): static
    {
        if ($this->depannages->removeElement($depannage)) {
            // set the owning side to null (unless already changed)
            if ($depannage->getTypepanne() === $this) {
                $depannage->setTypepanne(null);
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
                'Le type de panne est lié à %d dépannage(s) actif(s).',
                $depannagesNotDeleted->count()
            );
        }

        return $errors;
    }
}
