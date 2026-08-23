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
use App\Repository\TarifRepository;
use App\State\SoftDeleteProcessor;
use App\State\TarifProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Grille tarifaire GLOBALE : un prix unique par couple de gares (garedepart → garearrivee) pour l'entreprise.
 * Le prix d'un segment (ex. Abidjan → Bouaké) est défini UNE fois et réutilisé par toutes les lignes qui le desservent.
 */
#[ORM\Entity(repositoryClass: TarifRepository::class)]
#[UniquePerEntreprise(
    fields: ['garedepart', 'garearrivee'],
    message: 'Un tarif existe déjà pour ce couple de gares'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Tarif', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Tarif']],
    paginationClientItemsPerPage: true, // pagination SERVEUR (perPage pilotable par le listing)
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Tarif') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'La grille tarifaire',
                description: 'Permet de voir la grille tarifaire (prix gare → gare)',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Un tarif',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Tarif')",
            processor: TarifProcessor::class,
            openapi: new Operation(
                summary: 'Créer un tarif gare → gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modifier un tarif',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/tarifs/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du tarif',
                security: [['bearerAuth' => []]]
            )
        ),
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'garedepart.id' => 'exact',
    'garearrivee.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'montant', 'createdAt'])]
class Tarif extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Tarif'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Tarif', 'write:Tarif'])]
    private ?Gare $garedepart = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Tarif', 'write:Tarif'])]
    private ?Gare $garearrivee = null;

    #[ORM\Column]
    #[Groups(['read:Tarif', 'write:Tarif'])]
    #[Assert\NotNull]
    #[Assert\Positive(message: 'Le montant doit être strictement positif')]
    private ?int $montant = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGaredepart(): ?Gare
    {
        return $this->garedepart;
    }

    public function setGaredepart(?Gare $garedepart): static
    {
        $this->garedepart = $garedepart;

        return $this;
    }

    public function getGarearrivee(): ?Gare
    {
        return $this->garearrivee;
    }

    public function setGarearrivee(?Gare $garearrivee): static
    {
        $this->garearrivee = $garearrivee;

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

    public function getSoftDeleteBlockers(): array
    {
        return [];
    }
}
