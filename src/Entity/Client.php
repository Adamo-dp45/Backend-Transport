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
use App\Repository\ClientRepository;
use App\State\AdhererFideliteProcessor;
use App\State\EntrepriseInjectionProcessor;
use App\State\ResilierFideliteProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Identité durable d'un client (passager) au sein d'une entreprise.
 *
 * Le passager était jusqu'ici du texte libre dénormalisé sur chaque billet
 * (Ticket.nomclient/contactclient). Cette entité fournit une identité STABLE, dédupliquée par
 * TÉLÉPHONE ('contact', clé naturelle), pour les besoins à venir (carte de fidélité, réservation).
 *
 * Périmètre : la BILLETTERIE uniquement (le courrier n'est volontairement PAS rattaché au client).
 *
 * - Le rattachement est NON-CASSANT : les champs texte libre du billet restent en place
 *   comme SNAPSHOT (nom tel que vendu) ; ce 'client' vient les enrichir.
 * - Le rattachement au point de vente est automatique (find-or-create par téléphone, cf.
 *   App\Domain\Service\ClientResolver), pas besoin de le saisir explicitement.
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[UniquePerEntreprise(
    fields: ['contact'],
    message: 'Un client avec ce numéro existe déjà pour votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Client', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Client']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            // Les vendeurs de billets doivent pouvoir rechercher un client existant
            security: "is_granted('VOIR', 'Client') or is_granted('VOIR', 'Ticket')",
            openapi: new Operation(
                summary: 'La liste des clients',
                description: 'Permet de voir la liste des clients',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('VOIR', 'Ticket')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Le client',
                description: 'Permet de voir un client',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Client')",
            processor: EntrepriseInjectionProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'un client',
                description: 'Permet de créer un client',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification d\'un client',
                description: 'Permet de modifier un client',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/clients/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille d\'un client',
                description: 'Permet de mettre un client en corbeille',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/clients/{id}/adherer',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AdhererFideliteProcessor::class,
            openapi: new Operation(
                summary: 'Adhésion d\'un client au programme de fidélité',
                description: 'Inscrit le client (génère un n° de carte et fixe la date d\'adhésion)',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/clients/{id}/resilier',
            requirements: ['id' => '\d+'],
            input: false,
            processor: ResilierFideliteProcessor::class,
            openapi: new Operation(
                summary: 'Résiliation de l\'adhésion fidélité d\'un client',
                description: 'Désactive l\'adhésion (l\'historique de carte est conservé)',
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
    'contact' => 'partial', // recherche d'un client par téléphone (autocomplete vente)
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'nom',
    'createdAt'
])]
class Client extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Client', 'read:Ticket', 'read:Courrier'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Client', 'write:Client', 'read:Ticket', 'read:Courrier'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $nom = null;

    /**
     * Téléphone — clé naturelle de déduplication du client (un client = un numéro par entreprise).
     */
    #[ORM\Column(length: 255)]
    #[Groups(['read:Client', 'write:Client', 'read:Ticket', 'read:Courrier'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private ?string $contact = null;

    /**
     * Optionnel — prépare la réservation en ligne (canal de contact / futur identifiant de compte).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Client', 'write:Client'])]
    #[Assert\Email]
    private ?string $email = null;

    // -- Fidélité (carte à tampons, opt-in) -- //

    /** Membre du programme de fidélité (adhésion explicite). */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['read:Client'])]
    private bool $fidelite = false;

    /** N° de carte de fidélité, attribué à l'adhésion. */
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['read:Client'])]
    private ?string $cartefidelite = null;

    /** Date d'adhésion : les tampons ne se cumulent que sur les billets émis à partir de cette date. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read:Client'])]
    private ?\DateTimeImmutable $dateadhesion = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'client')]
    private Collection $tickets;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
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

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): static
    {
        $this->contact = $contact;

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

    public function isFidelite(): bool
    {
        return $this->fidelite;
    }

    public function setFidelite(bool $fidelite): static
    {
        $this->fidelite = $fidelite;

        return $this;
    }

    public function getCartefidelite(): ?string
    {
        return $this->cartefidelite;
    }

    public function setCartefidelite(?string $cartefidelite): static
    {
        $this->cartefidelite = $cartefidelite;

        return $this;
    }

    public function getDateadhesion(): ?\DateTimeImmutable
    {
        return $this->dateadhesion;
    }

    public function setDateadhesion(?\DateTimeImmutable $dateadhesion): static
    {
        $this->dateadhesion = $dateadhesion;

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
            $ticket->setClient($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            if ($ticket->getClient() === $this) {
                $ticket->setClient(null);
            }
        }

        return $this;
    }

    /* 'COUNT(*)' sans hydrater la collection (cf. note dans Fournisseur). */
    #[Groups(['read:Client'])]
    public function getTicketsCount(): int
    {
        return $this->tickets->count();
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $ticketsActifs = $this->tickets->filter(
            fn(Ticket $t) => $t->getDeletedAt() === null
        );
        if (!$ticketsActifs->isEmpty()) {
            $errors[] = sprintf('Le client est lié à %d billet(s) actif(s).', $ticketsActifs->count());
        }

        return $errors;
    }
}
