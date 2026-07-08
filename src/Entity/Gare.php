<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Repository\GareRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\SuspendreGareProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: GareRepository::class)]
#[UniquePerEntreprise(
    fields: ['chefgare', 'libelle', 'contact1'],
    message: 'L\'enregistrement existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Gare', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Gare']],
    paginationEnabled: false,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Gare') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Liste des gares',
                description: 'Permet de voir la liste des gares',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => ['read:Gare', 'read:Gare:item']],
            openapi: new Operation(
                summary: 'La gare',
                description: 'Permet de voir une gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Gare')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création de la gare',
                description: 'Permet de créer une gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification de la gare',
                description: 'Permet de modifier une gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            uriTemplate: '/gares/{id}/suspendre',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SuspendreGareProcessor::class,
            openapi: new Operation(
                summary: 'Suspendre ou réactiver une gare',
                description: 'Bloque la connexion de tous les utilisateurs de cette gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/gares/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille de la gare',
                description: 'Permet de mettre une gare en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
class Gare extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Gare', 'read:User', 'read:Courrier', 'read:Ligne', 'read:Ligne:item', 'read:Voyage', 'read:Ticket', 'read:Tarif', 'read:Bagage', 'read:Role', 'read:Reservation'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Gare', 'write:Gare', 'read:Courrier'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $chefgare = null;

    /**
     * Ville de rattachement (référentiel). En ÉCRITURE, on l'envoie par IRI (clé JSON 'ville', groupe
     * write:Gare). En LECTURE, on n'expose PAS l'objet mais son nom en chaîne via getVilleNom() (clé
     * JSON 'ville' également) → rétrocompatible avec tous les consommateurs qui lisaient 'ville' string.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['write:Gare'])]
    private ?Ville $ville = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Gare', 'write:Gare', 'read:Courrier', 'read:User', 'read:Ligne', 'read:Ligne:item', 'read:Voyage', 'read:Ticket', 'read:Tarif', 'read:Bagage', 'read:Role', 'read:Reservation'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['read:Gare:item', 'write:Gare'])]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Gare', 'write:Gare', 'read:Courrier'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $contact1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Gare', 'write:Gare', 'read:Courrier'])]
    private ?string $contact2 = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'gare')]
    private Collection $tickets;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'garedepart')]
    private Collection $courriers;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Gare'])]
    private ?string $statut = ReferenceStatus::ACTIF->value;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'gare')]
    private Collection $users;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Gare', 'read:Gare:item', 'write:Gare'])]
    private ?\DateTimeImmutable $datecreation = null;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
        $this->courriers = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChefgare(): ?string
    {
        return $this->chefgare;
    }

    public function setChefgare(string $chefgare): static
    {
        $this->chefgare = $chefgare;

        return $this;
    }

    public function getVille(): ?Ville
    {
        return $this->ville;
    }

    public function setVille(?Ville $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Nom de la ville exposé en LECTURE sous la clé JSON 'ville' (chaîne) — rétrocompatibilité avec
     * tous les consommateurs (templates/React) qui lisaient 'ville' en texte. Reprend exactement les
     * groupes de lecture de l'ancien champ texte.
     */
    #[Groups(['read:Gare', 'read:Courrier', 'read:User', 'read:Ligne', 'read:Ligne:item', 'read:Voyage', 'read:Ticket', 'read:Tarif', 'read:Bagage', 'read:Role', 'read:Reservation'])]
    #[SerializedName('ville')]
    public function getVilleNom(): ?string
    {
        return $this->ville?->getNom();
    }

    /** Id de la ville exposé en lecture (pour présélectionner le formulaire d'édition de la gare). */
    #[Groups(['read:Gare', 'read:Gare:item'])]
    #[SerializedName('villeId')]
    public function getVilleId(): ?int
    {
        return $this->ville?->getId();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
     * @return Collection<int, Ticket>
     */
    public function getTickets(): Collection
    {
        return $this->tickets;
    }

    public function addTicket(Ticket $ticket): static
    {
        if (!$this->tickets->contains($ticket)) {
            $this->tickets->add($ticket);
            $ticket->setGare($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getGare() === $this) {
                $ticket->setGare(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Courrier>
     */
    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function addCourrier(Courrier $courrier): static
    {
        if (!$this->courriers->contains($courrier)) {
            $this->courriers->add($courrier);
            $courrier->setGaredepart($this);
        }

        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier)) {
            // set the owning side to null (unless already changed)
            if ($courrier->getGaredepart() === $this) {
                $courrier->setGaredepart(null);
            }
        }

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
            $user->setGare($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            // set the owning side to null (unless already changed)
            if ($user->getGare() === $this) {
                $user->setGare(null);
            }
        }

        return $this;
    }

    public function getDatecreation(): ?\DateTimeImmutable
    {
        return $this->datecreation;
    }

    public function setDatecreation(?\DateTimeImmutable $datecreation): static
    {
        $this->datecreation = $datecreation;

        return $this;
    }

    /**
     * Empêche la mise en corbeille d'une gare encore liée à des ressources actives
     * (billets émis depuis cette gare, courriers au départ, utilisateurs rattachés).
     */
    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $tickets = $this->tickets->filter(fn(Ticket $t) => $t->getDeletedAt() === null);
        if (!$tickets->isEmpty()) {
            $errors[] = sprintf('La gare est liée à %d ticket(s) actif(s).', $tickets->count());
        }

        $courriers = $this->courriers->filter(fn(Courrier $c) => $c->getDeletedAt() === null);
        if (!$courriers->isEmpty()) {
            $errors[] = sprintf('La gare est liée à %d courrier(s) actif(s).', $courriers->count());
        }

        if (!$this->users->isEmpty()) {
            $errors[] = sprintf('La gare compte %d utilisateur(s) rattaché(s).', $this->users->count());
        }

        return $errors;
    }

}
