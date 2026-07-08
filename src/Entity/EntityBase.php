<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass] /*
    - Pour que 'Doctrine' l'intègre correctement dans le cycle de vie des entités enfants sinon les attributs 'PrePersist' et 'PreUpdate' peuvent ne pas être prises en compte correctement
*/
#[ORM\HasLifecycleCallbacks]
abstract class EntityBase // Ou.. 'PrePersist' et 'PreUpdate' via un subscriber
{
    #[ORM\Column(name: "created_at", type: "datetime_immutable", nullable: true)]
    #[Groups(['read:Base'])]
    protected ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: "created_from_ip", type: "string", nullable: true)]
    protected ?string $createdFromIp = null;

    #[ORM\Column(name: "updated_at", type: "datetime_immutable", nullable: true)]
    protected ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: "updated_from_ip", type: "string", nullable: true)]
    protected ?string $updatedFromIp = null;

    #[ORM\Column(name: "deleted_at", type: "datetime_immutable", nullable: true)]
    protected ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: "deleted_from_ip", type: "string", nullable: true)]
    protected ?string $deletedFromIp = null;

    #[ORM\Column(name: "created_by", type: 'integer', nullable: true)]
    protected ?int $createdBy = null;

    #[ORM\Column(name: "updated_by", type: 'integer', nullable: true)]
    protected ?int $updatedBy = null;

    #[ORM\Column(name: "deleted_by", type: 'integer', nullable: true)]
    protected ?int $deletedBy = null;

    #[ORM\Column(name: "etatdelete", type: 'boolean', nullable: true, options: ["default" => false])]
    private bool $etatdelete = false;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): static
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    public function getDeletedBy(): ?int
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?int $deletedBy): static
    {
        $this->deletedBy = $deletedBy;

        return $this;
    }

    public function getCreatedFromIp(): ?string
    {
        return $this->createdFromIp;
    }

    public function setCreatedFromIp(?string $createdFromIp): static
    {
        $this->createdFromIp = $createdFromIp;

        return $this;
    }

    public function getUpdatedFromIp(): ?string
    {
        return $this->updatedFromIp;
    }

    public function setUpdatedFromIp(?string $updatedFromIp): static
    {
        $this->updatedFromIp = $updatedFromIp;

        return $this;
    }

    public function getDeletedFromIp(): ?string
    {
        return $this->deletedFromIp;
    }

    public function setDeletedFromIp(?string $deletedFromIp): static
    {
        $this->deletedFromIp = $deletedFromIp;

        return $this;
    }

    public function isEtatdelete(): bool
    {
        return $this->etatdelete;
    }

    public function setIsEtatdelete(bool $isDeleted): static
    {
        $this->etatdelete = $isDeleted;

        return $this;
    }

    /*
        public function softDelete(): static
        {
            $this->deletedAt = new DateTimeImmutable();
            return $this;
        }

        public function restore(): static
        {
            $this->deletedAt = null;
            return $this;
        }

        public function isDeleted(): bool
        {
            return $this->deletedAt !== null;
        }
    */
}
