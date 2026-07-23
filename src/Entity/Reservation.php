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
use App\Domain\Enum\ReservationStatus;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\MultiGareScopedInterface;
use App\Entity\Output\Reservation\RegularisationApercuOutput;
use App\Entity\Output\Reservation\ReportPossibleDto;
use App\Repository\ReservationRepository;
use App\State\AnnulerReservationProcessor;
use App\State\ConfirmerReservationProcessor;
use App\State\EmettreBilletReservationProcessor;
use App\State\RegularisationApercuProvider;
use App\State\RegulariserReservationProcessor;
use App\State\ReportsPossiblesProvider;
use App\State\ReservationProcessor;
use App\State\SoftDeleteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Réservation d'une PLACE sur un tronçon d'un voyage (montée → descente), SANS siège précis.
 *
 * Le siège concret n'est attribué qu'à l'ÉMISSION DU BILLET, moment où un car est forcément affecté
 * → permet de réserver à l'avance, avant l'affectation du car (capacité bornée par
 * 'voyage.placesprevues' tant qu'il n'y a pas de car, puis par la capacité réelle du car).
 *
 * Une réservation TIENT sa place tant que son échéance court, même sans billet émis : payée, jusqu'à
 * l'heure de présentation ; impayée, le temps court du paiement. On ne vend donc plus par-dessus —
 * c'est ce qui évite d'encaisser un client à qui aucun billet ne pourra être émis. Le hold se libère
 * seul à l'échéance (cf. ReservationStatus::tenantsPlace et App\Domain\Service\CapaciteService).
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Reservation', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Reservation']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Reservation') or is_granted('VOIR', 'Ticket')",
            openapi: new Operation(
                summary: 'Liste des réservations',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('VOIR', 'Ticket')",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Une réservation',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Reservation') or is_granted('CREER', 'Ticket')",
            processor: ReservationProcessor::class,
            openapi: new Operation(
                summary: 'Création d\'une réservation (place tenue)',
                description: 'Réserve une place sur un tronçon ; le siège est attribué à la confirmation.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object) or is_granted('CREER', 'Ticket')",
            uriTemplate: '/reservations/{id}/confirmer',
            requirements: ['id' => '\d+'],
            input: false,
            processor: ConfirmerReservationProcessor::class,
            openapi: new Operation(
                summary: 'Confirmer une réservation (encaisser le paiement)',
                description: 'Encaisse le paiement (sans car requis). La réservation devient payée et « tient » sa place ; le siège et le billet sont émis ensuite (émission du billet).',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object) or is_granted('CREER', 'Ticket')",
            uriTemplate: '/reservations/{id}/emettre-billet',
            requirements: ['id' => '\d+'],
            input: false,
            processor: EmettreBilletReservationProcessor::class,
            openapi: new Operation(
                summary: 'Émettre le billet d\'une réservation payée (attribue un siège)',
                description: 'Attribue un siège libre sur le tronçon et émet le billet VALIDE (prix verrouillé). Le car doit être affecté.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/reservations/{id}/regularisation',
            security: "is_granted('VOIR', object) or is_granted('VOIR', 'Ticket')",
            requirements: ['id' => '\d+'],
            provider: RegularisationApercuProvider::class,
            output: RegularisationApercuOutput::class,
            normalizationContext: ['groups' => ['read:RegularisationApercu']],
            openapi: new Operation(
                summary: 'Aperçu d\'une régularisation (pénalité + complément à encaisser)',
                description: 'Pour une réservation A_REGULARISER et un départ cible (?voyage=), renvoie le décompte à encaisser sans rien persister.',
                security: [['bearerAuth' => []]]
            )
        ),
        new GetCollection(
            uriTemplate: '/reservations/{id}/reports',
            security: "is_granted('VOIR', 'Reservation') or is_granted('VOIR', 'Ticket')",
            requirements: ['id' => '\d+'],
            provider: ReportsPossiblesProvider::class,
            paginationEnabled: false,
            output: ReportPossibleDto::class,
            // Forme stable : on conserve les champs nuls, comme le reste de la ressource.
            normalizationContext: ['groups' => ['read:ReportPossible'], 'skip_null_values' => false],
            openapi: new Operation(
                summary: 'Départs sur lesquels cette réservation peut être reportée',
                description: 'Chaque départ est soumis au calcul de régularisation : la liste ne peut donc pas diverger de ce que le report acceptera. Renvoie aussi le décompte de chacun. Liste vide si la réservation n\'est pas à régulariser.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object) or is_granted('CREER', 'Ticket')",
            uriTemplate: '/reservations/{id}/regulariser',
            requirements: ['id' => '\d+'],
            input: false,
            processor: RegulariserReservationProcessor::class,
            openapi: new Operation(
                summary: 'Régulariser une réservation no-show (report + pénalité)',
                description: 'Reporte une réservation A_REGULARISER sur un nouveau départ (?voyage=), encaisse pénalité + complément et émet le billet.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object) or is_granted('CREER', 'Ticket')",
            uriTemplate: '/reservations/{id}/annuler',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AnnulerReservationProcessor::class,
            openapi: new Operation(
                summary: 'Annuler une réservation en attente',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'code' => 'partial',
    'nomclient' => 'partial',
    'voyage.id' => 'exact',
    'client.id' => 'exact',
    'statut' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'code',
    'createdAt',
])]
class Reservation extends EntityBase implements EntrepriseOwnedInterface, MultiGareScopedInterface
{
    // Périmètre B (2 gares) : une réservation ne concerne QUE sa gare de montée et sa gare de descente,
    // pas les gares intermédiaires (contrairement au ticket, ligne-scoped : le passager y transite).
    // La montée agit dessus (confirme/émet le billet) ; la descente ne fait que la « voir arriver ».
    public static function gareScopeFields(): array
    {
        return ['gare', 'garedescente'];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Reservation'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Reservation'])]
    private ?string $code = null;

    /** Identité durable du client (dédupliquée par téléphone), rattachée à la création. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Reservation'])]
    private ?Client $client = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private ?string $nomclient = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private ?string $contactclient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private ?Voyage $voyage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private ?Gare $gare = null; // gare de MONTÉE

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private ?Gare $garedescente = null;

    #[ORM\Column]
    #[Groups(['read:Reservation'])]
    private ?int $prix = null; // prix verrouillé à la réservation (grille tarifaire)

    #[ORM\Column(length: 20, options: ['default' => 'EN_ATTENTE'])]
    #[Groups(['read:Reservation'])]
    private string $statut = ReservationStatus::STATUT_EN_ATTENTE->value;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['read:Reservation'])]
    private ?\DateTimeImmutable $dateexpiration = null;

    #[ORM\Column(length: 20, options: ['default' => 'GUICHET'])]
    #[Groups(['read:Reservation', 'write:Reservation'])]
    private string $source = 'GUICHET'; // GUICHET | MOBILE

    /** Billet émis à la confirmation (la place honorée). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Reservation'])]
    private ?Ticket $ticket = null;

    // -- Paiement (en ligne, simulé pour l'instant) -- //

    #[ORM\Column(length: 30, options: ['default' => 'EN_ATTENTE_PAIEMENT'])]
    #[Groups(['read:Reservation'])]
    private string $etatpaiement = 'EN_ATTENTE_PAIEMENT'; // EN_ATTENTE_PAIEMENT | PAYE | ECHEC

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Reservation'])]
    private ?string $referencepaiement = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read:Reservation'])]
    private ?\DateTimeImmutable $datepaiement = null;

    // -- Régularisation (no-show payé récupéré : report sur un nouveau départ) -- //

    /** Pénalité encaissée lors de la régularisation (0 si aucune ou pas encore régularisée). */
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['read:Reservation'])]
    private int $penalitemontant = 0;

    /** Complément tarifaire encaissé si le nouveau départ était plus cher (0 sinon). */
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['read:Reservation'])]
    private int $montantcomplement = 0;

    /*
        NO-SHOW DU FAIT DE LA COMPAGNIE : posé quand le départ a été AVANCÉ après le paiement. Le
        client s'était engagé sur un horaire ; ce n'est pas lui qui l'a changé, il ne doit donc pas
        payer la pénalité de report. Le complément tarifaire, lui, reste dû (il paie un trajet, pas
        un retard). Un rétablissement de l'horaire initial ne le retire pas — le doute profite au
        client, pas à celui qui a bougé la date ; seule la régularisation le consomme.
    */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['read:Reservation'])]
    private bool $penaliteexoneree = false;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getNomclient(): ?string
    {
        return $this->nomclient;
    }

    public function setNomclient(?string $nomclient): static
    {
        $this->nomclient = $nomclient;

        return $this;
    }

    public function getContactclient(): ?string
    {
        return $this->contactclient;
    }

    public function setContactclient(?string $contactclient): static
    {
        $this->contactclient = $contactclient;

        return $this;
    }

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): static
    {
        $this->voyage = $voyage;

        return $this;
    }

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }

    public function getGaredescente(): ?Gare
    {
        return $this->garedescente;
    }

    public function setGaredescente(?Gare $garedescente): static
    {
        $this->garedescente = $garedescente;

        return $this;
    }

    public function getPrix(): ?int
    {
        return $this->prix;
    }

    public function setPrix(int $prix): static
    {
        $this->prix = $prix;

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

    public function getDateexpiration(): ?\DateTimeImmutable
    {
        return $this->dateexpiration;
    }

    public function setDateexpiration(?\DateTimeImmutable $dateexpiration): static
    {
        $this->dateexpiration = $dateexpiration;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function getEtatpaiement(): string
    {
        return $this->etatpaiement;
    }

    public function setEtatpaiement(string $etatpaiement): static
    {
        $this->etatpaiement = $etatpaiement;

        return $this;
    }

    public function getReferencepaiement(): ?string
    {
        return $this->referencepaiement;
    }

    public function setReferencepaiement(?string $referencepaiement): static
    {
        $this->referencepaiement = $referencepaiement;

        return $this;
    }

    public function getDatepaiement(): ?\DateTimeImmutable
    {
        return $this->datepaiement;
    }

    public function setDatepaiement(?\DateTimeImmutable $datepaiement): static
    {
        $this->datepaiement = $datepaiement;

        return $this;
    }

    public function getPenalitemontant(): int
    {
        return $this->penalitemontant;
    }

    public function setPenalitemontant(int $penalitemontant): static
    {
        $this->penalitemontant = $penalitemontant;

        return $this;
    }

    public function getMontantcomplement(): int
    {
        return $this->montantcomplement;
    }

    public function setMontantcomplement(int $montantcomplement): static
    {
        $this->montantcomplement = $montantcomplement;

        return $this;
    }

    public function isPenaliteexoneree(): bool
    {
        return $this->penaliteexoneree;
    }

    public function setPenaliteexoneree(bool $penaliteexoneree): static
    {
        $this->penaliteexoneree = $penaliteexoneree;

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
