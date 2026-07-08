<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Entity\Dto\ApprovisionnementInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\ApprovisionnementRepository;
use App\State\AnnulerApprovisionnementProcessor;
use App\State\ApprovisionnementProcessor;
use App\State\SoftDeleteProcessor;
use ArrayObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ApprovisionnementRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Approvisionnement', 'read:Base'], 'skip_null_values' => false],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Approvisionnement')",
            openapi: new Operation(
                summary: 'La liste des approvisionnements',
                description: 'Permet de voir la liste des approvisionnements',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'L\'approvisionnement',
                description: 'Permet de voir un approvisionnement',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Approvisionnement')",
            input: ApprovisionnementInput::class,
            processor: ApprovisionnementProcessor::class,
            denormalizationContext: ['groups' => ['write:ApprovisionnementInput']],
            openapi: new Operation(
                summary: 'Créer un approvisionnement',
                description: 'Permet de créer un approvisionnement',
                security: [['bearerAuth' => []]],
                requestBody: new RequestBody(
                    required: true,
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'fournisseur' => [
                                        'type' => 'int',
                                        'example' => '1'
                                    ],
                                    'details' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'piece' => [
                                                    'type' => 'int',
                                                    'example' => '2'
                                                ],
                                                'quantite' => [
                                                    'type' => 'int',
                                                    'example' => '10'
                                                ],
                                                'prixunitaire' => [
                                                    'type' => 'int',
                                                    'example' => '35000'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            input: ApprovisionnementInput::class,
            processor: ApprovisionnementProcessor::class, /*
                - Lorsqu'on modifie un approvisionnement, on réconcilie ses détails par différence (clé = pièce) : on ne génère dans l'inventaire que les mouvements réellement nécessaires (pièce ajoutée/retirée ou delta de quantité), une pièce inchangée ne crée aucun mouvement
            */
            denormalizationContext: ['groups' => ['write:ApprovisionnementInput']],
            openapi: new Operation(
                summary: 'Modification d\'un personnel',
                description: 'Permet de modifier un personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/approvisionnements/{id}/annuler',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AnnulerApprovisionnementProcessor::class,
            openapi: new Operation(
                summary: 'Annuler un approvisionnement',
                description: 'Annule un approvisionnement non verrouillé : retire du stock les pièces entrées (SORTIE) et passe le statut à ANNULE. Refusé si une pièce a déjà été consommée (stock insuffisant). Reste visible mais exclu des coûts.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/approvisionnements/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un approvisionnement',
                description: 'Permet de mettre un approvisionnement en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'fournisseur.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'dateappro',
    'createdAt'
])]
#[ApiFilter(DateFilter::class, properties: ['dateappro'])] /*
    - Permet d'activer les filtres 'dateappro[after]', 'dateappro[before]', 'dateappro[strictly_after]' et 'dateappro[strictly_before]'
*/
class Approvisionnement extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Approvisionnement', 'read:Fournisseur'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['read:Approvisionnement', 'read:Fournisseur'])]
    private ?\DateTimeImmutable $dateappro = null;

    #[ORM\ManyToOne(inversedBy: 'approvisionnements')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Approvisionnement'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailapprovisionnement>
     */
    #[ORM\OneToMany(targetEntity: Detailapprovisionnement::class, mappedBy: 'approvisionnement')]
    #[Groups(['read:Approvisionnement'])]
    private Collection $detailapprovisionnements;

    #[ORM\Column(length: 20, options: ['default' => 'VALIDE'])]
    #[Groups(['read:Approvisionnement'])]
    private string $statut = 'VALIDE'; // VALIDE | ANNULE (cf. App\Domain\Enum\ApprovisionnementStatus)

    public function __construct()
    {
        $this->detailapprovisionnements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateappro(): ?\DateTimeImmutable
    {
        return $this->dateappro;
    }

    public function setDateappro(\DateTimeImmutable $dateappro): static
    {
        $this->dateappro = $dateappro;

        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

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
     * @return Collection<int, Detailapprovisionnement>
     */
    public function getDetailapprovisionnements(): Collection
    {
        return $this->detailapprovisionnements;
    }

    public function addDetailapprovisionnement(Detailapprovisionnement $detailapprovisionnement): static
    {
        if (!$this->detailapprovisionnements->contains($detailapprovisionnement)) {
            $this->detailapprovisionnements->add($detailapprovisionnement);
            $detailapprovisionnement->setApprovisionnement($this);
        }

        return $this;
    }

    public function removeDetailapprovisionnement(Detailapprovisionnement $detailapprovisionnement): static
    {
        if ($this->detailapprovisionnements->removeElement($detailapprovisionnement)) {
            // set the owning side to null (unless already changed)
            if ($detailapprovisionnement->getApprovisionnement() === $this) {
                $detailapprovisionnement->setApprovisionnement(null);
            }
        }

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

}
