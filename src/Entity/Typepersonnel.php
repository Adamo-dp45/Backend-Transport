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
use App\Repository\TypepersonnelRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TypepersonnelRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle'],
    message: 'L\'enregistrement existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Typepersonnel', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Typepersonnel']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Typepersonnel') or is_granted('VOIR', 'Personnel')",
            openapi: new Operation(
                summary: 'Liste des types de personnel',
                description: 'Permet de voir la liste des types de personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le type de personnel',
                description: 'Permet de voir un type de personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Typepersonnel')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création du type de personnel',
                description: 'Permet de créer un type de personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification du type de personnel',
                description: 'Permet de modifier un type de personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/typepersonnels/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du type de personnel',
                description: 'Permet de mettre un type de personnel en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Typepersonnel extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Typepersonnel', 'read:Personnel'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Typepersonnel', 'read:Personnel', 'write:Typepersonnel', 'read:Depannage', 'read:Voyage', 'read:Trajet', 'read:Depannage'])]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Personnel>
     */
    #[ORM\OneToMany(targetEntity: Personnel::class, mappedBy: 'typepersonnel')]
    private Collection $personnels;

    public function __construct()
    {
        $this->personnels = new ArrayCollection();
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
     * @return Collection<int, Personnel>
     */
    public function getPersonnels(): Collection
    {
        return $this->personnels;
    }

    public function addPersonnel(Personnel $personnel): static
    {
        if (!$this->personnels->contains($personnel)) {
            $this->personnels->add($personnel);
            $personnel->setTypepersonnel($this);
        }

        return $this;
    }

    public function removePersonnel(Personnel $personnel): static
    {
        if ($this->personnels->removeElement($personnel)) {
            // set the owning side to null (unless already changed)
            if ($personnel->getTypepersonnel() === $this) {
                $personnel->setTypepersonnel(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $personnelsNotDeleted = $this->personnels->filter(
            fn(Personnel $v) => $v->getDeletedAt() === null
        );

        if(!$personnelsNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le type de personnel est liée à %d personnel(s) actif(s)',
                $personnelsNotDeleted->count()
            );
        }

        return $errors;
    }
}
