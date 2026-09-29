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
    normalizationContext: ['groups' => ['read:Approvisionnement', 'read:Base']],
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
            validationContext: ['groups' => ['Default', 'creation']], /*
                - 'creation' active les contraintes « obligatoire » du DTO, que le PATCH n'active pas :
                  un PATCH doit pouvoir être PARTIEL. Sans cette séparation, la validation refusait en
                  422 ce que le processeur savait traiter
            */
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
            // Réservé à l'ADMIN d'entreprise : écrit un mouvement de STOCK. Sous 'MODIFIER', la même
            // permission servait à corriger un libellé et à bouger des quantités.
            /*
                Action DÉDIÉE, pas 'MODIFIER' : cette opération écrit un MOUVEMENT DE STOCK. Sous
                'MODIFIER', la même permission servait à corriger un libellé et à bouger des
                quantités — un magasinier devant rectifier une saisie héritait de l'inventaire.
            */
            security: "is_granted('ANNULER', object)",
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
            security: "is_granted('ROLE_ADMIN')", // suppression d'un document comptable : admin d'entreprise UNIQUEMENT (la sortie normale est l'annulation, tracée)
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

    /**
     * Coût total de l'approvisionnement = somme du `couttotal` de ses lignes (28/09/2026).
     *
     * DÉRIVÉ MAIS STOCKÉ, comme `Depannage::$couttotal` — exception assumée à la doctrine « rien de
     * dérivable stocké », et ce n'est pas un cran de plus : chaque LIGNE stocke déjà son propre total
     * (`quantite × prixunitaire`), l'entête n'est que la suite. Il est RECOMPOSÉ par
     * `ApprovisionnementProcessor` à chaque écriture, jamais saisi : `ApprovisionnementInput` ne
     * l'expose pas.
     *
     * CE QU'IL CHANGE, exactement — et pas ce qu'on pourrait croire. Les agrégats lisaient
     * `SUM(da.couttotal)` derrière un `join(...)`, soit un INNER JOIN, qui écarte un approvisionnement
     * SANS AUCUNE LIGNE. Sur le MONTANT, cela ne faussait rien : sa contribution vaut zéro, son absence
     * de la somme ne la déplace pas — vérifié en réintroduisant la jointure, le total ne bouge pas d'un
     * franc. Le tort portait sur les COMPTEURS : `COUNT(DISTINCT a.id) AS nbappros`
     * (`achatsParFournisseur`) sous-comptait cet approvisionnement, qui existe pourtant.
     *
     * Le vrai gain du champ est donc ailleurs, et il suffit : un coût lisible sur l'entête comme pour un
     * dépannage, des agrégats sur UNE table au lieu de deux, un client qui n'a plus à resommer les
     * lignes, et un compteur juste.
     *
     * BIGINT : un total en FCFA dépasse la limite INT (~2,1 milliards) sur un gros marché de pièces.
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Approvisionnement', 'read:Fournisseur'])]
    private ?int $couttotal = null;

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

    public function getCouttotal(): ?int
    {
        return $this->couttotal;
    }

    public function setCouttotal(?int $couttotal): static
    {
        $this->couttotal = $couttotal;

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
