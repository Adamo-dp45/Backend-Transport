<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\DetailcourrierStatus;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\DetailcourrierRepository;
use App\State\PerduDetailcourrierProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DetailcourrierRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Detailcourrier', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Detailcourrier']],
    operations: [
        new Patch(
            security: "is_granted('MODIFIER', 'Courrier')",
            uriTemplate: '/detailcourriers/{id}/perdu',
            requirements: ['id' => '\d+'],
            input: false,
            processor: PerduDetailcourrierProcessor::class,
            openapi: new Operation(
                summary: 'Déclarer un colis comme perdu',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Detailcourrier extends EntityBase implements EntrepriseOwnedInterface
{
    use IdEntrepriseTrait; // identreprise + get/set → isolation tenant via EntrepriseScopeExtension

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Detailcourrier', 'read:Courrier'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'detailcourriers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Courrier $courrier = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?string $nature = null; // ex: document, marchandise, électronique..

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?string $designation = null; // La description précise du colis

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?string $emballage = null; // ex: Sachet Blanc, Carton

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?string $type = null; // ex: FRAGILE, NORMAL, VOLUMINEUX

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?int $poids = null;

    #[ORM\Column(type: 'bigint')] // BIGINT : une valeur déclarée peut être élevée (colis de grande valeur)
    #[Groups(['read:Detailcourrier', 'read:Courrier', 'write:Detailcourrier'])]
    private ?int $valeur = null; // La base de calcul de la taxe

    #[ORM\Column(type: 'bigint')] // BIGINT : cohérence avec valeur / total courrier
    #[Groups(['read:Detailcourrier', 'read:Courrier'])]
    private ?int $montant = null; // La taxe de ce colis calculée via 'TarifCourrier'

    #[ORM\ManyToOne(inversedBy: 'detailcourriers')]
    #[Groups(['read:Detailcourrier', 'read:Courrier'])]
    private ?Tarifcourrier $tarifcourrier = null;

    #[ORM\Column(length: 80)]
    #[Groups(['read:Detailcourrier', 'read:Courrier'])]
    private ?string $statut = DetailcourrierStatus::STATUT_NORMAL->value; /*
        - On le conserve pour l'historique même si la grille tarifaire change 
    */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourrier(): ?Courrier
    {
        return $this->courrier;
    }

    public function setCourrier(?Courrier $courrier): static
    {
        $this->courrier = $courrier;

        return $this;
    }

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;

        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(?string $designation): static
    {
        $this->designation = $designation;

        return $this;
    }

    public function getEmballage(): ?string
    {
        return $this->emballage;
    }

    public function setEmballage(?string $emballage): static
    {
        $this->emballage = $emballage;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPoids(): ?int
    {
        return $this->poids;
    }

    public function setPoids(?int $poids): static
    {
        $this->poids = $poids;

        return $this;
    }

    public function getValeur(): ?int
    {
        return $this->valeur;
    }

    public function setValeur(int $valeur): static
    {
        $this->valeur = $valeur;

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

    public function getTarifcourrier(): ?Tarifcourrier
    {
        return $this->tarifcourrier;
    }

    public function setTarifcourrier(?Tarifcourrier $tarifcourrier): static
    {
        $this->tarifcourrier = $tarifcourrier;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
