<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Symfony\Action\NotFoundAction;
use App\Repository\PermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
// #[ORM\UniqueConstraint(columns: ['entity', 'action', 'role_id', 'identreprise'])] -- Au niveau de la base de données
#[ApiResource(
    operations: [
        new Get(
            controller: NotFoundAction::class, /*
                - Permet de désactiver la route vu  qu'on n'a seulement besoin de l'iri
            */
            read: false,
            output: false, // Pour afficher le résultat
            openapi: new Operation(
                summary: 'hidden' // On a caché la route dans `OpenApiFactory`
            )
        )
    ]
)]
class Permission extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Role'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Role', 'write:Role', 'read:User'])]
    private ?string $entity = null; // User, Product..

    #[ORM\Column(length: 255)]
    #[Groups(['read:Role', 'write:Role', 'read:User'])]
    private ?string $action = null; // VIEW, CREATE, EDIT, DELETE..

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    #[ORM\ManyToOne(inversedBy: 'permissions')]
    private ?Role $role = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): static
    {
        $this->entity = $entity;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

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

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }
}
