<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\TypedepenseRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le POSTE d'une dépense : carburant, péage, pneus, salaires, loyer, imprévu…
 *
 * Un référentiel par entreprise, et non un enum figé : les postes d'une compagnie de brousse ne sont
 * pas ceux d'une compagnie urbaine, et chaque nouveau poste aurait coûté une migration. C'est aussi
 * l'axe principal des statistiques de dépenses (« où part l'argent »).
 */
#[ORM\Entity(repositoryClass: TypedepenseRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle'],
    message: 'Le type de dépense existe déjà dans votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: [
        'groups' => ['read:Typedepense', 'read:Base'],
        'openapi_definition_name' => 'Collection'
    ],
    denormalizationContext: ['groups' => ['write:Typedepense']],
    paginationEnabled: false, // Référentiel court : la liste entière alimente le 'DataTable' client
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Typedepense') or is_granted('VOIR', 'Depense')", /*
                - Le second droit sert le SELECT du formulaire de dépense : sans lui, un agent
                  autorisé à saisir une dépense ne pourrait pas choisir son poste
            */
            openapi: new Operation(
                summary: 'Liste des types de dépense',
                description: 'Permet de voir la liste des types de dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le type de dépense',
                description: 'Permet de voir un type de dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Typedepense')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création du type de dépense',
                description: 'Permet de créer un type de dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification du type de dépense',
                description: 'Permet de modifier un type de dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            name: 'Remove_Typedepense',
            uriTemplate: '/typedepenses/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du type de dépense',
                description: 'Permet de mettre un type de dépense en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Typedepense extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    use IdEntrepriseTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Typedepense', 'read:Depense'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Typedepense', 'write:Typedepense', 'read:Depense'])]
    #[ApiProperty(
        types: 'string',
        description: 'Le libellé du type de dépense',
        example: 'Carburant'
    )]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Depense>
     */
    #[ORM\OneToMany(targetEntity: Depense::class, mappedBy: 'typedepense')]
    private Collection $depenses;

    public function __construct()
    {
        $this->depenses = new ArrayCollection();
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

    /**
     * @return Collection<int, Depense>
     */
    public function getDepenses(): Collection
    {
        return $this->depenses;
    }

    public function addDepense(Depense $depense): static
    {
        if (!$this->depenses->contains($depense)) {
            $this->depenses->add($depense);
            $depense->setTypedepense($this);
        }

        return $this;
    }

    public function removeDepense(Depense $depense): static
    {
        if ($this->depenses->removeElement($depense)) {
            if ($depense->getTypedepense() === $this) {
                $depense->setTypedepense(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $depensesNotDeleted = $this->depenses->filter(
            fn(Depense $v) => $v->getDeletedAt() === null
        );

        if(!$depensesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le type de dépense est lié à %d dépense(s) active(s).',
                $depensesNotDeleted->count()
            );
        }

        return $errors;
    }
}
