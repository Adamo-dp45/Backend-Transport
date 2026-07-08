<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\DetailpersonnelRepository;
use App\State\SoftDeleteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DetailpersonnelRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Detailpersonnel', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Detailpersonnel']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new Delete(
            security: "is_granted('MODIFIER', 'Personnel')",
            requirements: ['id' => '\d+'],
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un détail personnel',
                description: 'Permet de mettre un détail personnel en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)] /*
    #[ApiFilter(SearchFilter::class, properties: ['personnel.id' => 'exact'])] -- pour avoir les voyages du personnel '/api/detailpersonnels?personnel.id=3&exists[voyage]=true'
    #[ApiFilter(ExistsFilter::class, properties: ['voyage', 'depannage'])] -- !! dépannages du personnel '/api/detailpersonnels?personnel.id=3&exists[depannage]=true'
*/
class Detailpersonnel extends EntityBase implements EntrepriseOwnedInterface
{
    use IdEntrepriseTrait; // identreprise + get/set → isolation tenant via EntrepriseScopeExtension

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Depannage', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Voyage'])]
    private ?string $motif = null;

    #[ORM\ManyToOne(inversedBy: 'detailpersonnels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] // Ou 'cascade: ..'
    #[Groups(['read:Depannage', 'read:Voyage'])]
    private ?Personnel $personnel = null;

    #[ORM\ManyToOne(inversedBy: 'detailpersonnels')] // '#[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]' fait automatiquement
    private ?Depannage $depannage = null;

    #[ORM\ManyToOne(inversedBy: 'detailpersonnels')]
    private ?Voyage $voyage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;

        return $this;
    }

    public function getDepannage(): ?Depannage
    {
        return $this->depannage;
    }

    public function setDepannage(?Depannage $depannage): static
    {
        $this->depannage = $depannage;

        return $this;
    }

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): static
    {
        $this->voyage = $voyage;

        return $this;
    }
}
