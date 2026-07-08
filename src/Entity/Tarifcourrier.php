<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\TarifcourrierRepository;
use App\State\SoftDeleteProcessor;
use App\State\TarifcourrierProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TarifcourrierRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle', 'valeurmin', 'valeurmax'],
    message: 'Le tarif de courrier existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Tarifcourrier', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Tarifcourrier']],
    order: ['valeurmin' => 'ASC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Tarifcourrier') or is_granted('VOIR', 'Courrier')",
            openapi: new Operation(
                summary: 'Liste des tarifs courrier',
                description: 'Permet de voir la liste des tarifs courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Un tarif courrier',
                description: 'Permet de voir un tarif courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Tarifcourrier')",
            processor: TarifcourrierProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un tarif courrier',
                description: 'Permet de créer un tarif courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un tarif courrier',
                description: 'Permet de modifier un tarif courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/tarifcourriers/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Suppression d\'un tarif courrier',
                description: 'Permet de supprimer un tarif courrier',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Tarifcourrier extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Tarifcourrier', 'read:Detailcourrier', 'read:Courrier'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Tarifcourrier', 'read:Detailcourrier', 'write:Tarifcourrier'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['read:Tarifcourrier', 'read:Detailcourrier', 'write:Tarifcourrier'])]
    private ?int $valeurmin = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Tarifcourrier', 'read:Detailcourrier', 'write:Tarifcourrier'])]
    private ?int $valeurmax = null; // 'Null' dernière tranche 'illimitée' qui s'applique à tout ce qui dépasse 'valeurmin'

    #[ORM\Column]
    #[Groups(['read:Tarifcourrier', 'read:Detailcourrier', 'write:Tarifcourrier'])]
    private ?int $montanttaxe = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailcourrier>
     */
    #[ORM\OneToMany(targetEntity: Detailcourrier::class, mappedBy: 'tarifcourrier')]
    private Collection $detailcourriers;

    public function __construct()
    {
        $this->detailcourriers = new ArrayCollection();
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

    public function getValeurmin(): ?int
    {
        return $this->valeurmin;
    }

    public function setValeurmin(int $valeurmin): static
    {
        $this->valeurmin = $valeurmin;

        return $this;
    }

    public function getValeurmax(): ?int
    {
        return $this->valeurmax;
    }

    public function setValeurmax(?int $valeurmax): static
    {
        $this->valeurmax = $valeurmax;

        return $this;
    }

    public function getMontanttaxe(): ?int
    {
        return $this->montanttaxe;
    }

    public function setMontanttaxe(int $montanttaxe): static
    {
        $this->montanttaxe = $montanttaxe;

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
     * @return Collection<int, Detailcourrier>
     */
    public function getDetailcourriers(): Collection
    {
        return $this->detailcourriers;
    }

    public function addDetailcourrier(Detailcourrier $detailcourrier): static
    {
        if (!$this->detailcourriers->contains($detailcourrier)) {
            $this->detailcourriers->add($detailcourrier);
            $detailcourrier->setTarifcourrier($this);
        }

        return $this;
    }

    public function removeDetailcourrier(Detailcourrier $detailcourrier): static
    {
        if ($this->detailcourriers->removeElement($detailcourrier)) {
            // set the owning side to null (unless already changed)
            if ($detailcourrier->getTarifcourrier() === $this) {
                $detailcourrier->setTarifcourrier(null);
            }
        }

        return $this;
    }
}
