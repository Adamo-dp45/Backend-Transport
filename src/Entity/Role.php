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
use App\Entity\Interface\GareOwnedInterface;
use App\Repository\RoleRepository;
use App\State\RoleProcessor;
use App\State\SoftDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints\Valid;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
/*
    - Unicité du nom gérée par 'RoleProcessor' au périmètre (entreprise, gare) : un rôle de gare et un
      rôle entreprise (gare = null) peuvent porter le même nom, et deux gares peuvent réutiliser un nom.
      ('UniquePerEntreprise' ne convient pas ici car il ignore la vérif dès qu'un champ scope est null.)
*/
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Role', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Role']],
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Role') or is_granted('VOIR', 'User')",
            openapi: new Operation(
                summary: 'La liste des rôles',
                description: 'Permet de voir la liste des rôles',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le rôle',
                description: 'Permet de voir un rôle',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Role')",
            processor: RoleProcessor::class,
            name: 'RolePost',
            openapi: new Operation(
                summary: 'Créer un rôle',
                description: 'Permet de créer un rôle',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: RoleProcessor::class,
            name: 'RolePatch',
            openapi: new Operation(
                summary: 'Modifier un rôle',
                description: 'Permet de modifier un rôle',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/roles/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un rôle',
                description: 'Permet de mettre un rôle en corbeille',
                security: [['bearerAuth' => []]]
            )
        ) /*
            - On peut affichés les utiliasteurs liées à un rôle dans le 'get' en faisant une boucle sur 'userRoles' et récupérer chaque '->getUser()'
        */
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'gare.id' => 'exact',
    'typerole' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'createdAt'])]
class Role extends EntityBase implements EntrepriseOwnedInterface, GareOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Role', 'read:User'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Role', 'read:User', 'write:Role'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Role', 'write:Role', 'read:User'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * Gare « propriétaire » du rôle : null = rôle entreprise (créé par l'admin entreprise, visible
     * partout) ; sinon rôle de cette gare, visible/éditable uniquement par ses acteurs via
     * 'GareScopeExtension'. Exposé en LECTURE seule (objet léger {id, libelle, ville} via 'read:Role')
     * pour l'affichage/filtre côté front ; non exposé en écriture : auto-affecté par 'RoleProcessor'.
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Role'])]
    private ?Gare $gare = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Role', 'write:Role'])]
    private ?string $typerole = null;

    /**
     * @var Collection<int, Permission>
     */
    #[
        ORM\OneToMany(targetEntity: Permission::class, mappedBy: 'role', orphanRemoval: true, cascade: ['persist']),
        Valid()
    ] // 'Valid' va valider les permissions
    #[Groups(['read:Role', 'write:Role', 'read:User'])]
    private Collection $permissions;

    /**
     * @var Collection<int, UserRole>
     */
    #[ORM\OneToMany(targetEntity: UserRole::class, mappedBy: 'role', orphanRemoval: true, cascade: ['persist'])]
    private Collection $userRoles;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->userRoles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public static function gareScopeField(): string
    {
        return 'gare';
    }

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }

    public function getTyperole(): ?string
    {
        return $this->typerole;
    }

    public function setTyperole(?string $typerole): static
    {
        $this->typerole = $typerole;

        return $this;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->setRole($this);
        }

        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        if ($this->permissions->removeElement($permission)) {
            // set the owning side to null (unless already changed)
            if ($permission->getRole() === $this) {
                $permission->setRole(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(UserRole $userRole): static
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $userRole->setRole($this);
        }

        return $this;
    }

    public function removeUserRole(UserRole $userRole): static
    {
        if ($this->userRoles->removeElement($userRole)) {
            // set the owning side to null (unless already changed)
            if ($userRole->getRole() === $this) {
                $userRole->setRole(null);
            }
        }

        return $this;
    }
}
