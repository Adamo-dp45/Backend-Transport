<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Dto\EntrepriseInput;
use App\Repository\EntrepriseRepository;
use App\State\DesactiverEntrepriseProcessor;
use App\State\MeEntrepriseProcessor;
use App\State\MeEntrepriseProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Entreprise', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Entreprise']],
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_SUPER_ADMIN')",
            openapi: new Operation(
                summary: 'La liste des entreprises',
                description: 'Permet de voir la liste des entreprises',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('ROLE_SUPER_ADMIN')", // Ou Admin
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'L\'entreprise',
                description: 'Permet de voir une entreprise',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_SUPER_ADMIN')",
            uriTemplate: '/entreprises/{id}/desactiver',
            requirements: ['id' => '\d+'],
            input: false,
            processor: DesactiverEntrepriseProcessor::class,
            openapi: new Operation(
                summary: 'Activer ou désactiver une entreprise',
                security: [['bearerAuth' => []]]
            )
        ),
        /* Admin
         */
        new Get(
            uriTemplate: '/me/entreprise',
            security: "is_granted('ROLE_ADMIN') or object == user.getEntreprise()",
            provider: MeEntrepriseProvider::class,
            input: false,
            openapi: new Operation(
                summary: 'Voir mon entreprise',
                description: 'Permet de voir les informations d\'une entreprise',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            uriTemplate: '/me/entreprise',
            security: "is_granted('ROLE_ADMIN')",
            input: EntrepriseInput::class, /*
                - Vu qu'on n'a pas de moyen de récupérer l'entreprise ici..
            */
            processor: MeEntrepriseProcessor::class,
            denormalizationContext: ['groups' => ['write:EntrepriseInput']], /*
                - Pour éviter qu'il utilise 'write:Entreprise' sinon il ne vas pas remplir mon 'input'
            */
            openapi: new Operation(
                summary: 'Modifier une entreprise',
                description: 'Permet à l\'administrateur de modifier les informations de son entreprise',
                security: [['bearerAuth' => []]]
            )
        )
    ]
)]
class Entreprise extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Entreprise', 'read:User'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $libelle = null;

    /** Identifiant public (URL de réservation mobile/web). Généré depuis le libellé, unique. Lecture seule. */
    #[ORM\Column(length: 255, unique: true, nullable: true)]
    #[Groups(['read:Entreprise'])]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $contact1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $contact2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $email = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anneecreation = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $sigle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $siteweb = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $rccm = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $banque = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?string $centreimpot = null;

    #[ORM\Column(nullable: true)]
    #[Groups('read:Entreprise', 'write:Entreprise')]
    private ?int $tauxtva = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'entreprise')]
    private Collection $users;

    #[ORM\ManyToOne]
    #[Groups(['read:Entreprise', 'write:EntrepriseInput'])]
    private ?MediaObject $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Entreprise'])]
    private ?string $statut = ReferenceStatus::ACTIF->value;

    public function __construct()
    {
        $this->users = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getContact1(): ?string
    {
        return $this->contact1;
    }

    public function setContact1(string $contact1): static
    {
        $this->contact1 = $contact1;

        return $this;
    }

    public function getContact2(): ?string
    {
        return $this->contact2;
    }

    public function setContact2(?string $contact2): static
    {
        $this->contact2 = $contact2;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAnneecreation(): ?\DateTimeImmutable
    {
        return $this->anneecreation;
    }

    public function setAnneecreation(?\DateTimeImmutable $anneecreation): static
    {
        $this->anneecreation = $anneecreation;

        return $this;
    }

    public function getSigle(): ?string
    {
        return $this->sigle;
    }

    public function setSigle(?string $sigle): static
    {
        $this->sigle = $sigle;

        return $this;
    }

    public function getSiteweb(): ?string
    {
        return $this->siteweb;
    }

    public function setSiteweb(?string $siteweb): static
    {
        $this->siteweb = $siteweb;

        return $this;
    }

    public function getRccm(): ?string
    {
        return $this->rccm;
    }

    public function setRccm(?string $rccm): static
    {
        $this->rccm = $rccm;

        return $this;
    }

    public function getBanque(): ?string
    {
        return $this->banque;
    }

    public function setBanque(?string $banque): static
    {
        $this->banque = $banque;

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

    public function getCentreimpot(): ?string
    {
        return $this->centreimpot;
    }

    public function setCentreimpot(?string $centreimpot): static
    {
        $this->centreimpot = $centreimpot;

        return $this;
    }

    public function getTauxtva(): ?int
    {
        return $this->tauxtva;
    }

    public function setTauxtva(?int $tauxtva): static
    {
        $this->tauxtva = $tauxtva;

        return $this;
    }


    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setEntreprise($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getEntreprise() === $this) {
                $user->setEntreprise(null);
            }
        }

        return $this;
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
