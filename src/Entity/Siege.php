<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Repository\SiegeRepository;
use App\State\SiegeStateProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SiegeRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Siege']],
    // provider: SiegeStateProvider::class,
    operations: [
        new GetCollection(
            // security: "is_granted('VOIR', 'Siege')",
            provider: SiegeStateProvider::class,
            openapi: new Operation(
                summary: 'La liste des sièges',
                description: 'Retourne les sièges d\'un car avec leur statut pour un voyage donné',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            // security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Un siège',
                description: 'Permet de voir un siège',
                security: [['bearerAuth' => []]]
            )
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'car' => 'exact'
])]
class Siege
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Siege', 'read:Ticket', 'read:Car'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['read:Siege', 'read:Ticket', 'read:Voyage', 'read:Car'])]
    private ?int $numero = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Siege', 'read:Car'])]
    private ?int $rangee = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Siege', 'read:Car'])]
    private ?int $colonne = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Siege', 'read:Car'])]
    private ?string $cote = null; // GAUCHE | DROITE

    #[ORM\ManyToOne(inversedBy: 'sieges')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Siege'])]
    private ?Car $car = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    // Champ virtuel — non persisté, calculé à la volée
    #[Groups(['read:Siege', 'read:Car'])]
    private string $statut = 'LIBRE'; // LIBRE | OCCUPE

    // Infos virtuelles de l'occupant (billet qui grise le siège sur le tronçon demandé) — non persistées.
    // Permettent à l'agent de « libérer » le siège quand le passager descend en route, pour le revendre.
    #[Groups(['read:Siege'])]
    private ?int $occupantTicketId = null;

    #[Groups(['read:Siege'])]
    private ?string $occupantNom = null;

    #[Groups(['read:Siege'])]
    private ?string $occupantMontee = null;

    #[Groups(['read:Siege'])]
    private ?string $occupantDescente = null;

    /*
        REVENDU : le siège porte plusieurs billets VALIDE qui voyagent TOUS — réutilisation légitime sur
        des tronçons disjoints (l'un descend là où l'autre monte). C'est une bonne nouvelle commerciale.

        À ne pas confondre avec CONFLIT : là aussi le siège porte plusieurs billets, mais leurs trajets
        se recouvrent et la priorité amont en évince un — ce passager ne montera pas. Compter les deux
        cas ensemble laissait croire à des reventes là où il y avait des places perdues.
    */
    #[Groups(['read:Siege'])]
    private bool $revendu = false;

    #[Groups(['read:Siege'])]
    private bool $conflit = false;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'siege')]
    private Collection $tickets;

    public function __construct()
    {
        $this->tickets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getRangee(): ?int
    {
        return $this->rangee;
    }

    public function setRangee(?int $rangee): static
    {
        $this->rangee = $rangee;

        return $this;
    }

    public function getColonne(): ?int
    {
        return $this->colonne;
    }

    public function setColonne(?int $colonne): static
    {
        $this->colonne = $colonne;

        return $this;
    }

    public function getCote(): ?string
    {
        return $this->cote;
    }

    public function setCote(?string $cote): static
    {
        $this->cote = $cote;

        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): static
    {
        $this->car = $car;

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
            $ticket->setSiege($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getSiege() === $this) {
                $ticket->setSiege(null);
            }
        }

        return $this;
    }


    /**
     * Get the value of statut
     */ 
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * Set the value of statut
     *
     * @return  self
     */ 
    public function setStatut($statut)
    {
        $this->statut = $statut;

        return $this;
    }

    public function getOccupantTicketId(): ?int
    {
        return $this->occupantTicketId;
    }

    public function setOccupantTicketId(?int $occupantTicketId): static
    {
        $this->occupantTicketId = $occupantTicketId;

        return $this;
    }

    public function getOccupantNom(): ?string
    {
        return $this->occupantNom;
    }

    public function setOccupantNom(?string $occupantNom): static
    {
        $this->occupantNom = $occupantNom;

        return $this;
    }

    public function getOccupantMontee(): ?string
    {
        return $this->occupantMontee;
    }

    public function setOccupantMontee(?string $occupantMontee): static
    {
        $this->occupantMontee = $occupantMontee;

        return $this;
    }

    public function getOccupantDescente(): ?string
    {
        return $this->occupantDescente;
    }

    public function setOccupantDescente(?string $occupantDescente): static
    {
        $this->occupantDescente = $occupantDescente;

        return $this;
    }

    public function isRevendu(): bool
    {
        return $this->revendu;
    }

    public function setRevendu(bool $revendu): static
    {
        $this->revendu = $revendu;

        return $this;
    }

    public function isConflit(): bool
    {
        return $this->conflit;
    }

    public function setConflit(bool $conflit): static
    {
        $this->conflit = $conflit;

        return $this;
    }
}
