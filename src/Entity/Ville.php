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
use App\Repository\VilleRepository;
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
 * Référentiel des VILLES (par entreprise). Remplace l'ancien champ texte libre 'Gare.ville' : une gare
 * appartient désormais à une Ville. Sert notamment de premier niveau de navigation côté client
 * (mobile/web) : ville → gares de la ville → destinations. La commune reste volontairement hors périmètre
 * (promouvable plus tard en champ nullable sur la gare).
 */
#[ORM\Entity(repositoryClass: VilleRepository::class)]
#[UniquePerEntreprise(
    fields: ['nom'],
    message: 'Cette ville existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Ville', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Ville']],
    order: ['nom' => 'ASC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Ville') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Liste des villes',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Une ville',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Ville')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'une ville',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'une ville',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/villes/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'une ville',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: ['nom' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'nom', 'createdAt'])]
class Ville extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Ville', 'read:Gare', 'read:Gare:item'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Ville', 'write:Ville', 'read:Gare', 'read:Gare:item'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $nom = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Gare>
     */
    #[ORM\OneToMany(targetEntity: Gare::class, mappedBy: 'ville')]
    private Collection $gares;

    public function __construct()
    {
        $this->gares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return string[] */
    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $garesActives = $this->gares->filter(fn(Gare $g) => $g->getDeletedAt() === null);
        if (!$garesActives->isEmpty()) {
            $errors[] = sprintf('La ville est rattachée à %d gare(s) active(s).', $garesActives->count());
        }

        return $errors;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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
}
