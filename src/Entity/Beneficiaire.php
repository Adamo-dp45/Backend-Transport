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
use App\Domain\Enum\BeneficiaireCategorie;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\BeneficiaireRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BeneficiaireRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Beneficiaire', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Beneficiaire']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            // Les vendeurs de tickets doivent pouvoir lister les bénéficiaires pour la remise
            security: "is_granted('VOIR', 'Beneficiaire') or is_granted('VOIR', 'Ticket')",
            openapi: new Operation(
                summary: 'La liste des bénéficiaires',
                description: 'Permet de voir la liste des bénéficiaires de remise',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('VOIR', 'Ticket')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le bénéficiaire',
                description: 'Permet de voir un bénéficiaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            // Création inline possible pendant la vente d'un ticket
            security: "is_granted('CREER', 'Beneficiaire') or is_granted('CREER', 'Ticket')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un bénéficiaire',
                description: 'Permet de créer un bénéficiaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un bénéficiaire',
                description: 'Permet de modifier un bénéficiaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/beneficiaires/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un bénéficiaire',
                description: 'Permet de mettre un bénéficiaire en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'nom' => 'partial',
    'categorie' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'nom',
    'createdAt'
])]
class Beneficiaire extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Beneficiaire', 'read:Ticket'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Beneficiaire', 'write:Beneficiaire', 'read:Ticket'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Groups(['read:Beneficiaire', 'write:Beneficiaire', 'read:Ticket'])]
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [BeneficiaireCategorie::class, 'values'], message: 'Catégorie invalide')]
    private ?string $categorie = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Beneficiaire', 'write:Beneficiaire', 'read:Ticket'])]
    private ?string $contact = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(?string $contact): static
    {
        $this->contact = $contact;

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
