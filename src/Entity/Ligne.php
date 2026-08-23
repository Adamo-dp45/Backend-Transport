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
use App\Entity\Dto\LigneInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Interface\LigneGareScopedInterface;
use App\Repository\LigneRepository;
use App\State\LigneProcessor;
use App\State\SoftDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LigneRepository::class)]
/*
    #[UniquePerEntreprise(
        fields: ['provenance', 'destination'],
        message: 'Le trajet existe déjà pour votre entreprise'
    )] 
    - Ne vas pas fonctionner pour le 'post' vu que j'utilise un 'input' sinon on le fais dans le 'processor'
*/
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Ligne', 'read:Base']],
    denormalizationContext: ['groups' => ['write:LigneInput']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Ligne') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Liste des lignes',
                description: 'Permet de voir la liste des lignes',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => ['read:Ligne', 'read:Ligne:item', 'read:Base']],
            openapi: new Operation(
                summary: 'La ligne',
                description: 'Permet de voir une ligne avec ses arrêts et sa grille tarifaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Ligne')",
            input: LigneInput::class,
            processor: LigneProcessor::class, // À cause du codeligne, des arrêts et de la matrice
            denormalizationContext: ['groups' => ['write:LigneInput']],
            openapi: new Operation(
                summary: 'Création d\'une ligne',
                description: 'Permet de créer une ligne avec ses arrêts ordonnés et sa grille tarifaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            input: LigneInput::class,
            processor: LigneProcessor::class,
            denormalizationContext: ['groups' => ['write:LigneInput']],
            openapi: new Operation(
                summary: 'Modification d\'une ligne',
                description: 'Permet de modifier une ligne, ses arrêts et sa grille tarifaire',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/lignes/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille de la ligne',
                description: 'Permet de mettre une ligne en corbeille',
                security: [['bearerAuth' => []]]
            )
        ),
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'libelle' => 'partial',      // recherche « Abidjan → Korhogo »
    'codeligne' => 'partial',    // recherche par code
    'gareorigine.id' => 'exact', // lignes dont l'origine est une gare donnée (création de voyage : origine-only)
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'codeligne', 'libelle', 'createdAt'])]
class Ligne extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard, LigneGareScopedInterface
{
    // La Ligne EST la ligne : 'GareScopeExtension' (chemin vide) joint directement ses 'arrets' et ne garde
    // que les lignes dont un arrêt = la gare de l'agent. Les admins/centraux voient toutes les lignes.
    public static function ligneScopePath(): array
    {
        return [];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Ligne', 'read:Voyage', 'read:Ticket'])]
    private ?string $codeligne = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?string $libelle = null; // ex: "Abidjan → Korhogo"

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?Gare $gareorigine = null; // dérivables des arrêts, dénormalisées pour confort

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?Gare $gareterminus = null; // dérivables des arrêts, dénormalisées pour confort

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    #[Groups(['read:Ligne'])]
    private ?\DateTimeImmutable $heuredepart = null; // Gabarit horaire (08H, 10H..)

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Arret>
     */
    #[ORM\OneToMany(targetEntity: Arret::class, mappedBy: 'ligne', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Groups(['read:Ligne'])]
    private Collection $arrets; // les arrêts ordonnés

    /**
     * @var Collection<int, Voyage>
     */
    #[ORM\OneToMany(targetEntity: Voyage::class, mappedBy: 'ligne')]
    private Collection $voyages;

    public function __construct()
    {
        $this->arrets = new ArrayCollection();
        $this->voyages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodeligne(): ?string
    {
        return $this->codeligne;
    }

    public function setCodeligne(string $codeligne): static
    {
        $this->codeligne = $codeligne;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getGareorigine(): ?Gare
    {
        return $this->gareorigine;
    }

    public function setGareorigine(?Gare $gareorigine): static
    {
        $this->gareorigine = $gareorigine;

        return $this;
    }

    public function getGareterminus(): ?Gare
    {
        return $this->gareterminus;
    }

    public function setGareterminus(?Gare $gareterminus): static
    {
        $this->gareterminus = $gareterminus;

        return $this;
    }

    public function getHeuredepart(): ?\DateTimeImmutable
    {
        return $this->heuredepart;
    }

    public function setHeuredepart(?\DateTimeImmutable $heuredepart): static
    {
        $this->heuredepart = $heuredepart;

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
     * @return Collection<int, Arret>
     */
    public function getArrets(): Collection
    {
        return $this->arrets;
    }

    public function addArret(Arret $arret): static
    {
        if (!$this->arrets->contains($arret)) {
            $this->arrets->add($arret);
            $arret->setLigne($this);
        }

        return $this;
    }

    public function removeArret(Arret $arret): static
    {
        if ($this->arrets->removeElement($arret)) {
            if ($arret->getLigne() === $this) {
                $arret->setLigne(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, Voyage>
     */
    public function getVoyages(): Collection
    {
        return $this->voyages;
    }

    public function addVoyage(Voyage $voyage): static
    {
        if (!$this->voyages->contains($voyage)) {
            $this->voyages->add($voyage);
            $voyage->setLigne($this);
        }

        return $this;
    }

    public function removeVoyage(Voyage $voyage): static
    {
        if ($this->voyages->removeElement($voyage)) {
            if ($voyage->getLigne() === $this) {
                $voyage->setLigne(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $voyagesNotDeleted = $this->voyages->filter(
            fn(Voyage $v) => $v->getDeletedAt() === null
        );

        if (!$voyagesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'La ligne est liée à %d voyage(s) actif(s).',
                $voyagesNotDeleted->count()
            );
        }

        return $errors;
    }

    /* Le count collection, l'avantage est que 'Doctrine' fait un 'COUNT(*)' et ne charge pas la collection
     */
    #[Groups(['read:Ligne'])]
    public function getVoyagesCount(): int
    {
        return $this->voyages->count();
    }
}
