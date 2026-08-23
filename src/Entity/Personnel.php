<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\PersonnelRepository;
use App\State\PersonnelProcessor;
use App\State\SoftDeleteProcessor;
use App\State\SuspendrePersonnelProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[UniquePerEntreprise(
    fields: ['nom' ,'prenom', 'contact', 'typepersonnel'],
    message: 'Le personnel existe déjà dans votre entreprise'
)] # On peut un 'code' par entreprise mais prendre en compte le 'deletedAt'
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Personnel', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Personnel']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Personnel') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'La liste des personnels',
                description: 'Permet de voir la liste des personnels',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le personnel',
                description: 'Permet de voir un personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Personnel')",
            processor: PersonnelProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un personnel',
                description: 'Permet de créer un personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un personnel',
                description: 'Permet de modifier un personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            // Réservé à l'ADMIN d'entreprise : suspendre un agent est une décision RH, pas une modification
            // de sa fiche.
            security: "is_granted('ROLE_ADMIN')",
            uriTemplate: '/personnels/{id}/suspendre',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SuspendrePersonnelProcessor::class,
            openapi: new Operation(
                summary: 'Suspendre ou réactiver un personnel',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/personnels/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un personnel',
                description: 'Permet de mettre un personnel en corbeille',
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
    'statut' => 'exact'
])]
class Personnel extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'read:Trajet'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'write:Personnel', 'read:Trajet'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'write:Personnel', 'read:Trajet'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'write:Personnel', 'read:Trajet'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $contact = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'write:Personnel', 'read:Trajet'])]
    private ?string $code = null;

    #[ORM\ManyToOne(inversedBy: 'personnels')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Depannage', 'read:Voyage', 'read:Personnel', 'write:Personnel', 'read:Trajet'])]
    private ?Typepersonnel $typepersonnel = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailpersonnel>
     */
    #[ORM\OneToMany(targetEntity: Detailpersonnel::class, mappedBy: 'personnel')]
    private Collection $detailpersonnels;

    #[ORM\ManyToOne]
    #[Groups(['read:Personnel', 'write:Personnel'])]
    private ?MediaObject $image = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['read:Personnel'])]
    private ?string $statut = ReferenceStatus::ACTIF->value;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Depannage', 'read:Personnel', 'write:Personnel'])]
    private ?\DateTimeImmutable $dateembauche = null;

    public function __construct()
    {
        $this->detailpersonnels = new ArrayCollection();
    }

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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTypepersonnel(): ?Typepersonnel
    {
        return $this->typepersonnel;
    }

    public function setTypepersonnel(?Typepersonnel $typepersonnel): static
    {
        $this->typepersonnel = $typepersonnel;

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
     * @return Collection<int, Detailpersonnel>
     */
    public function getDetailpersonnels(): Collection
    {
        return $this->detailpersonnels;
    }

    public function addDetailpersonnel(Detailpersonnel $detailpersonnel): static
    {
        if (!$this->detailpersonnels->contains($detailpersonnel)) {
            $this->detailpersonnels->add($detailpersonnel);
            $detailpersonnel->setPersonnel($this);
        }

        return $this;
    }

    public function removeDetailpersonnel(Detailpersonnel $detailpersonnel): static
    {
        if ($this->detailpersonnels->removeElement($detailpersonnel)) {
            // set the owning side to null (unless already changed)
            if ($detailpersonnel->getPersonnel() === $this) {
                $detailpersonnel->setPersonnel(null);
            }
        }

        return $this;
    }

    /**
     * Empêche la mise en corbeille d'un personnel encore affecté à un voyage ou un dépannage EN COURS.
     * Les affectations à des voyages/dépannages déjà clôturés (historique) ne bloquent pas la suppression.
     */
    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $actifs = $this->detailpersonnels->filter(function (Detailpersonnel $d) {
            if ($d->getDeletedAt() !== null) {
                return false;
            }
            $voyageEnCours = $d->getVoyage() !== null && $d->getVoyage()->getDatearriveereelle() === null;
            $depannageEnCours = $d->getDepannage() !== null && $d->getDepannage()->getStatut() !== DepannageStatus::CLOTURE->value;
            return $voyageEnCours || $depannageEnCours;
        });

        if(!$actifs->isEmpty()) {
            $errors[] = sprintf(
                'Le personnel est affecté à %d voyage(s)/dépannage(s) en cours.',
                $actifs->count()
            );
        }

        return $errors;
    }

    public function getImage(): ?MediaObject
    {
        return $this->image;
    }

    public function setImage(?MediaObject $image): static
    {
        $this->image = $image;

        return $this;
    }

    /* Le count collection et l'avantage est que 'Doctrine' ne charge pas la collection mais 'COUNT(*)'
     */
    #[Groups(['read:Personnel'])]
    public function getVoyagesCount(): int
    {
        return $this->detailpersonnels->filter(
            fn(Detailpersonnel $dp) => $dp->getVoyage() !== null
        )->count();
    }

    #[Groups(['read:Personnel'])]
    public function getDepannagesCount(): int
    {
        return $this->detailpersonnels->filter(
            fn(Detailpersonnel $dp) => $dp->getDepannage() !== null
        )->count();
    }

    #[Groups(['read:Personnel'])]
    public function getAffectationsCount(): int
    {
        return $this->detailpersonnels->count();
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateembauche(): ?\DateTimeImmutable
    {
        return $this->dateembauche;
    }

    public function setDateembauche(?\DateTimeImmutable $dateembauche): static
    {
        $this->dateembauche = $dateembauche;

        return $this;
    }

}
