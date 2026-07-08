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
use App\Repository\TarifbagageRepository;
use App\State\SoftDeleteProcessor;
use App\State\TarifbagageProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TarifbagageRepository::class)]
#[UniquePerEntreprise(
    fields: ['libelle', 'poidsmin', 'poidsmax'],
    message: 'Le tarif de bagage existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Tarifbagage', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Tarifbagage']],
    order: ['poidsmin' => 'ASC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Tarifbagage') or is_granted('VOIR', 'Bagage')",
            openapi: new Operation(
                summary: 'Liste des tarifs bagage',
                description: 'Permet de voir la liste des tarifs bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Un tarif bagage',
                description: 'Permet de voir un tarif bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Tarifbagage')",
            processor: TarifbagageProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un tarif bagage',
                description: 'Permet de créer un tarif bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: TarifbagageProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un tarif bagage',
                description: 'Permet de modifier un tarif bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/tarifbagages/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Suppression d\'un tarif bagage',
                description: 'Permet de supprimer un tarif bagage',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Tarifbagage extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Tarifbagage', 'read:Bagage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage'])]
    private ?string $libelle = null;

    #[ORM\Column]
    #[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage'])]
    private ?int $poidsmin = null; // En 'kg'

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage'])]
    private ?int $poidsmax = null; // 'Null' dernière tranche 'illimitée' qui s'applique à tout ce qui dépasse 'poidsmin'

    #[ORM\Column]
    #[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage'])]
    private ?int $montant = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Bagage>
     */
    #[ORM\OneToMany(targetEntity: Bagage::class, mappedBy: 'tarifbagage')]
    private Collection $bagages;

    public function __construct()
    {
        $this->bagages = new ArrayCollection();
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

    public function getPoidsmin(): ?int
    {
        return $this->poidsmin;
    }

    public function setPoidsmin(int $poidsmin): static
    {
        $this->poidsmin = $poidsmin;

        return $this;
    }

    public function getPoidsmax(): ?int
    {
        return $this->poidsmax;
    }

    public function setPoidsmax(?int $poidsmax): static
    {
        $this->poidsmax = $poidsmax;

        return $this;
    }

    public function getMontant(): ?int
    {
        return $this->montant;
    }

    public function setMontant(int $montant): static
    {
        $this->montant = $montant;

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
     * @return Collection<int, Bagage>
     */
    public function getBagages(): Collection
    {
        return $this->bagages;
    }

    public function addBagage(Bagage $bagage): static
    {
        if (!$this->bagages->contains($bagage)) {
            $this->bagages->add($bagage);
            $bagage->setTarifbagage($this);
        }

        return $this;
    }

    public function removeBagage(Bagage $bagage): static
    {
        if ($this->bagages->removeElement($bagage)) {
            // set the owning side to null (unless already changed)
            if ($bagage->getTarifbagage() === $this) {
                $bagage->setTarifbagage(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $bagagesNotDeleted = $this->bagages->filter(
            fn(Bagage $v) => $v->getDeletedAt() === null
        );

        if(!$bagagesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le tarif est liée à %d bagages(s) actif(s).',
                $bagagesNotDeleted->count()
            );
        }

        return $errors;
    }
}
