<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\TicketStatus;
use App\Entity\Dto\AffectcarInput;
use App\Entity\Dto\AffectCommercialInput;
use App\Entity\Dto\AffectpersonnelInput;
use App\Entity\Dto\AvancerCommercialInput;
use App\Entity\Dto\RattrapagePassageInput;
use App\Entity\Dto\CloturerInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\LigneGareScopedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Output\Bordereau\BordereauOutput;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurOutput;
use App\Entity\Output\Reservation\VoyageReservableDto;
use App\Filter\PersonnelFilter;
use App\Repository\VoyageRepository;
use App\State\AffectcarProcessor;
use App\State\AffectCommercialProcessor;
use App\State\AffectpersonnelProcessor;
use App\State\AvancerCommercialProcessor;
use App\State\CloturerVoyageProcessor;
use App\State\BordereauChauffeurProvider;
use App\State\BordereauProvider;
use App\State\DemarrerVoyageProcessor;
use App\State\RattraperPassageProcessor;
use App\State\ReceptionnerVoyageProcessor;
use App\State\RepartirVoyageProcessor;
use App\State\SoftDeleteProcessor;
use App\State\VoyageProcessor;
use App\State\VoyageProvider;
use App\State\VoyagesReservablesProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
/*
    - L'unicité (datedepartprevue, ligne) est vérifiée dans 'VoyageProcessor' : robuste à la transition
      trajet -> ligne, la ligne étant résolue dans le processor (et non au moment de la validation).
*/
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Voyage', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Voyage']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Voyage') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'La liste des voyages',
                description: 'Permet de voir la liste des voyages',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            provider: VoyageProvider::class, // + frise 'horaires' (dérivée), pipeline natif conservé
            normalizationContext: ['groups' => ['read:Voyage', 'read:Base', 'read:Voyage:item']], /*
                - La FICHE seule embarque les collections complètes (billets, courriers, bagages,
                  personnel) : elle les affiche. La LISTE ne reçoit que leurs compteurs — sinon on
                  hydrate et sérialise des collections entières juste pour afficher un nombre.
            */
            openapi: new Operation(
                summary: 'Le voyage',
                description: 'Permet de voir un voyage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Voyage')",
            processor: VoyageProcessor::class,
            openapi: new Operation(
                summary: 'Permet de créer un voyage',
                description: 'Création du voyage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: VoyageProcessor::class,
            denormalizationContext: ['groups' => ['write:Voyage:update']],
            openapi: new Operation(
                summary: 'Modification du voyage',
                description: 'Permet de modifier un voyage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/voyages/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du voyage',
                description: 'Permet de mettre un voyage en corbeille',
                security: [['bearerAuth' => []]]
            )
        ),
        new GetCollection(
            security: "is_granted('VOIR', 'Voyage') or is_granted('ROLE_USER')",
            uriTemplate: '/voyages/reservables',
            provider: VoyagesReservablesProvider::class,
            paginationEnabled: false,
            output: VoyageReservableDto::class,
            // Un champ nul (heurepassage pour un profil sans gare) reste dans le JSON à null au lieu
            // de DISPARAÎTRE — forme stable pour le client. Assuré globalement par
            // 'skip_null_values: false' dans 'api_platform.yaml'.
            normalizationContext: ['groups' => ['read:VoyageReservable']],
            openapi: new Operation(
                summary: 'Voyages sur lesquels une réservation peut encore être créée',
                description: 'Décision prise côté serveur : elle dépend de la gare de l\'agent, de l\'avancement réel du car et des durées de trajet par arrêt. Le guichet ne doit pas la reconstituer par des filtres. `?usage=vente` applique les règles de la VENTE au guichet : pas de délai de présentation (le passager est là) et embarquement à la position du car pour le commercial du voyage.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/voyages/{id}/receptionner',
            requirements: ['id' => '\d+'],
            input: false,
            processor: ReceptionnerVoyageProcessor::class,
            openapi: new Operation(
                summary: 'Réceptionner un voyage à sa gare',
                description: 'L\'agent confirme le passage du véhicule à sa gare : les courriers et bagages qui y descendent sont réceptionnés/livrés automatiquement',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            /*
                ROLE_ADMIN, et rien de moins : c'est une écriture RÉTROACTIVE sur la chronologie d'un
                voyage. Elle débloque les gares en aval, avance la position du car et ferme des
                réservations. Un agent de gare réceptionne sa propre gare — il ne certifie pas le
                passage du car chez le voisin.
            */
            security: "is_granted('ROLE_ADMIN')",
            uriTemplate: '/voyages/{id}/rattraper-passage',
            requirements: ['id' => '\d+'],
            input: RattrapagePassageInput::class,
            processor: RattraperPassageProcessor::class,
            denormalizationContext: ['groups' => ['write:RattrapagePassageInput']],
            openapi: new Operation(
                summary: 'Rattraper le passage d\'une gare jamais pointée',
                description: 'Consigne, à son heure RÉELLE, l\'arrivée du car à une gare que personne n\'a réceptionnée — ce qui débloque la réception des gares suivantes. Ne réceptionne PAS les colis : la gare le fera elle-même. L\'heure doit s\'insérer entre les passages déjà connus.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/voyages/{id}/repartir',
            requirements: ['id' => '\d+'],
            input: false,
            processor: RepartirVoyageProcessor::class,
            openapi: new Operation(
                summary: 'Enregistrer le départ du car d\'une gare intermédiaire',
                description: 'Horodate le départ réel du car de sa position courante (pendant de la réception). Réservé au commercial, à l\'agent de la gare où se trouve le car, ou à un admin.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/voyages/{id}/demarrer',
            requirements: ['id' => '\d+'],
            input: false,
            processor: DemarrerVoyageProcessor::class,
            openapi: new Operation(
                summary: 'Démarrer le voyage (départ réel)',
                description: 'Enregistre le départ réel du car : les colis passent EN_TRANSIT et les bagages EMBARQUE.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/voyages/{id}/cloturer',
            requirements: ['id' => '\d+'],
            input: CloturerInput::class,
            denormalizationContext: ['groups' => ['write:Cloturer']],
            processor: CloturerVoyageProcessor::class,
            openapi: new Operation(
                summary: 'Clôturer le voyage (arrivée au terminus)',
                description: 'Pose l\'arrivée réelle : réservé à la gare de destination. Le car redevient disponible.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            uriTemplate: '/voyages/{id}/car',
            input: AffectcarInput::class,
            processor: AffectcarProcessor::class,
            denormalizationContext: ['groups' => ['write:AffectcarInput']],
            openapi: new Operation(
                summary: 'Affectation d\'un car à un voyage',
                description: 'Permet d\'affecter un car à un voyage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            uriTemplate: '/voyages/{id}/personnel',
            input: AffectpersonnelInput::class,
            processor: AffectpersonnelProcessor::class,
            name: 'Affect-voyage',
            denormalizationContext: ['groups' => ['write:AffectpersonnelInput']],
            openapi: new Operation(
                summary: 'Affectation d\'un personnel à un voyage',
                description: 'Permet d\'affecter un personnel à un voyage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            uriTemplate: '/voyages/{id}/commercial',
            input: AffectCommercialInput::class,
            processor: AffectCommercialProcessor::class,
            denormalizationContext: ['groups' => ['write:AffectCommercialInput']],
            openapi: new Operation(
                summary: 'Affecter / désaffecter le commercial (vendeur à bord)',
                description: 'Affecte un User comme commercial du voyage (vend en route depuis la position courante du car). commercial=null pour le retirer.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('IS_AUTHENTICATED_FULLY')", /*
                - Réservé au commercial du voyage (ou admin) : vérifié dans le processor.
            */
            requirements: ['id' => '\d+'],
            uriTemplate: '/voyages/{id}/avancer',
            input: AvancerCommercialInput::class,
            processor: AvancerCommercialProcessor::class,
            denormalizationContext: ['groups' => ['write:AvancerCommercialInput']],
            openapi: new Operation(
                summary: 'Le commercial fait avancer la position du car',
                description: 'Le commercial à bord déclare que le car est arrivé à une gare en aval : sa position de vente avance.',
                security: [['bearerAuth' => []]]
            )
        ),
        /* Bordereaux
         */
        new Get(
            uriTemplate: '/voyages/{id}/bordereau',
            uriVariables: [
                'id' => new Link(fromClass: Voyage::class)
            ],
            security: "is_granted('VOIR', 'Voyage')",
            provider: BordereauProvider::class,
            output: BordereauOutput::class,
            // Les horaires réels NON encore connus (arrivée à l'origine, tout au terminus avant le
            // passage) restent dans le JSON à null au lieu de DISPARAÎTRE — sinon le template lit une
            // clé absente et plante. Assuré globalement par 'skip_null_values: false'
            // dans 'api_platform.yaml'.
            normalizationContext: ['groups' => []],
            openapi: new Operation(
                summary: 'Bordereau d\'un voyage par gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/voyages/{id}/bordereau/chauffeur',
            requirements: ['id' => '\d+'],
            security: "is_granted('VOIR', 'Voyage')",
            provider: BordereauChauffeurProvider::class,
            output: BordereauChauffeurOutput::class,
            normalizationContext: ['groups' => []],
            openapi: new Operation(
                summary: 'Bordereau chauffeur',
                description: 'Document de transfert chauffeur → gare d\'arrivée',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact', /*
        - Permet '?id[]=..&id[]=..' : le FT décide des voyages vendables via '/voyages/reservables'
          puis vient chercher CES voyages-là en entier. Sans ce filtre il chargeait '/api/voyages'
          page par page (25, triés 'createdAt DESC') et croisait les deux listes en mémoire — les
          voyages les plus anciennement CRÉÉS, donc les plus proches ou déjà en retard, tombaient
          hors de la première page et disparaissaient du sélecteur de vente.
    */
    'codevoyage' => 'partial',
    'ligne.id' => 'exact',
    'car.id' => 'exact',
    'datearriveereelle' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'datedepartprevue',
    'provenance',
    'destination',
    'placestotal',
    'createdAt'
])]
#[ApiFilter(DateFilter::class, properties: ['datedepartprevue'])]
#[ApiFilter(ExistsFilter::class, properties: ['datearriveereelle', 'datedepartreelle'])] /* Pour récupérer que les voyages en cours
    - Vu que 'mysql' ne comprend pas 'null' comme une valeur 'DATETIME' valide et va l'interprèté 'WHERE datearriveereelle = 'null'' on a le 'ExistsFilter' qui lui 'datearriveereelle IS NULL' mais attend '?exists[datearriveereelle]=false'
    - 'datedepartreelle' : idem pour ne proposer que les voyages PAS ENCORE PARTIS (sélecteur de réservation)
*/
#[ApiFilter(PersonnelFilter::class)] /*
    - On.. filtre qui fais un join sur 'Detailpersonnel' via 'personnel.id' pour récupérer les voyages du personnel
*/
class Voyage extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard, LigneGareScopedInterface
{
    public static function ligneScopePath(): array
    {
        return ['ligne'];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Voyage', 'read:Personnel', 'read:Ticket', 'read:Courrier', 'read:Bagage', 'read:Reservation'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Voyage', 'read:Personnel', 'read:Ticket', 'read:Courrier', 'read:Bagage', 'read:Reservation'])]
    private ?string $codevoyage = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Voyage', 'write:Voyage', 'write:Voyage:update', 'read:Personnel', 'read:Ticket', 'read:Bagage', 'read:Reservation'])]
    private ?string $provenance = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Voyage', 'write:Voyage', 'read:Personnel', 'read:Ticket', 'write:Voyage:update', 'read:Bagage', 'read:Reservation'])]
    private ?string $destination = null;

    // -- Exploitation : départ/arrivée PRÉVUS (saisis à la création) et RÉELS (posés à l'exécution) -- //

    #[ORM\Column]
    #[Groups(['read:Voyage', 'write:Voyage', 'read:Personnel', 'read:Ticket', 'write:Voyage:update', 'read:Reservation'])]
    private ?\DateTimeImmutable $datedepartprevue = null; // départ prévu (saisi à la création)

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Voyage', 'write:Voyage', 'write:Voyage:update', 'read:Personnel', 'read:Ticket', 'read:Reservation'])]
    private ?\DateTimeImmutable $datearriveeprevue = null; // arrivée prévue (saisie à la création)

    /** Départ RÉEL : posé quand le car part vraiment (action « démarrer » ou 1re réception). Lecture seule. */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Voyage', 'read:Personnel', 'read:Ticket', 'read:Reservation'])]
    private ?\DateTimeImmutable $datedepartreelle = null;

    /** Arrivée RÉELLE = clôture du voyage (terminus atteint). Lecture seule (posée à la clôture). */
    #[ORM\Column(nullable: true)]
    // Lecture seule : posée UNIQUEMENT par la clôture (route dédiée /voyages/{id}/cloturer), plus par le
    // Patch général — d'où l'absence de groupe d'écriture.
    #[Groups(['read:Voyage', 'read:Personnel', 'read:Ticket', 'read:Reservation'])]
    private ?\DateTimeImmutable $datearriveereelle = null;

    /**
     * Capacité PRÉVISIONNELLE pour les réservations à l'avance (avant l'affectation d'un car) :
     * borne le nombre de places réservables tant qu'aucun car n'est affecté. Une fois le car affecté,
     * la capacité réelle (nombre de sièges) prend le relais (cf. CapaciteService).
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Voyage', 'write:Voyage', 'write:Voyage:update'])]
    private ?int $placesprevues = null;

    #[ORM\ManyToOne(inversedBy: 'voyages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Voyage', 'write:Voyage', 'read:Ticket'])]
    private ?Ligne $ligne = null;

    #[ORM\ManyToOne(inversedBy: 'voyages')]
    // #[ORM\JoinColumn(nullable: false)]  -- onDelete: 'RESTRICT'
    #[Groups(['read:Voyage', 'write:Voyage', 'write:Voyage:update', 'read:Personnel'])] // 'optionel' ou l'ajouter à partir d'un 'input' et '..update' car au cours d'un voyage on peut changer un car
    private ?Car $car = null;

    /**
     * Commercial (vendeur à bord) affecté au voyage : un User qui accompagne le car de la provenance
     * au terminus et vend des tickets EN ROUTE depuis la position courante du car ('garecourante').
     * Affecté/désaffecté après création via /voyages/{id}/commercial.
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Voyage'])]
    private ?User $commercial = null;

    /**
     * Position COURANTE du car sur la ligne (gare où il se trouve) : le commercial vend DEPUIS cette gare.
     * Démarre à la gare d'origine ; avance (monotone, jamais en arrière) à chaque réception d'une gare
     * intermédiaire OU quand le commercial déclare sa progression. Null = non initialisée (= origine).
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Voyage'])]
    private ?Gare $garecourante = null;

    /**
     * Gare de PROVENANCE RÉELLE du voyage = son origine effective. Pour un voyage normal, c'est
     * l'origine de la ligne. Pour un « DÉPART PARTIEL » (une gare intermédiaire qui lance son propre
     * car parce qu'elle a assez de demande), c'est CETTE gare intermédiaire : le voyage ne dessert
     * alors que [gareprovenance → terminus]. C'est le pivot de la doctrine « l'origine planifie » :
     * la planification (modif/commercial/suppression) est réservée à cette gare, pas à celle de la ligne.
     * Null = legacy → on retombe sur l'origine de la ligne.
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Voyage'])] // uniquement read:Voyage (évite d'alourdir les jointures des billets/réservations)
    private ?Gare $gareprovenance = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailpersonnel>
     */
    #[ORM\OneToMany(targetEntity: Detailpersonnel::class, mappedBy: 'voyage')]
    #[Groups(['read:Voyage:item'])] // fiche uniquement — la liste utilise getDetailpersonnelsCount()
    private Collection $detailpersonnels;

    /**
     * @var Collection<int, Ticket>
     */
    #[ORM\OneToMany(targetEntity: Ticket::class, mappedBy: 'voyage')]
    #[Groups(['read:Voyage:item'])] // fiche uniquement — la liste utilise getTicketsCount()
    private Collection $tickets;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Voyage'])]
    // #[Assert\PositiveOrZero]
    private ?int $placestotal = null;

    /**
     * @var Collection<int, Courrier>
     */
    #[ORM\OneToMany(targetEntity: Courrier::class, mappedBy: 'voyage')]
    #[Groups(['read:Voyage:item'])] // fiche uniquement — la liste utilise getCourriersCount()
    private Collection $courriers;

    /**
     * @var Collection<int, Bagage>
     */
    #[ORM\OneToMany(targetEntity: Bagage::class, mappedBy: 'voyage')]
    #[Groups(['read:Voyage:item'])] // fiche uniquement — la liste utilise getBagagesCount()
    private Collection $bagages;

    /**
     * Passages réels (arrivée/départ par gare). Léger (≤ nb d'arrêts) → exposé aussi en LISTE, pour que
     * le front sache si le car est arrivé/reparti d'une gare (boutons réception / départ, disparition).
     * @var Collection<int, Passage>
     */
    #[ORM\OneToMany(targetEntity: Passage::class, mappedBy: 'voyage')]
    #[Groups(['read:Voyage'])]
    private Collection $passages;

    /**
     * FRISE des horaires par gare : pour chaque arrêt de la ligne, l'heure PRÉVUE de passage (somme
     * des tronçons), les heures RÉELLES (cf. Passage), le retard et le temps d'arrêt.
     *
     * DÉRIVÉE à la lecture (jamais stockée) et posée par VoyageProvider, comme Ticket::$evince : le
     * calcul a besoin de ReservationEcheanceService, qu'une entité ne peut pas porter. Exposée sur le
     * SEUL groupe 'read:Voyage:item' — donc sur la FICHE, pas en liste, où elle serait du poids mort.
     *
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['read:Voyage:item'])]
    private array $horaires = [];

    public function __construct()
    {
        $this->detailpersonnels = new ArrayCollection();
        $this->tickets = new ArrayCollection();
        $this->courriers = new ArrayCollection();
        $this->bagages = new ArrayCollection();
        $this->passages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodevoyage(): ?string
    {
        return $this->codevoyage;
    }

    public function setCodevoyage(string $codevoyage): static
    {
        $this->codevoyage = $codevoyage;

        return $this;
    }

    public function getProvenance(): ?string
    {
        return $this->provenance;
    }

    public function setProvenance(string $provenance): static
    {
        $this->provenance = $provenance;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getDatedepartprevue(): ?\DateTimeImmutable
    {
        return $this->datedepartprevue;
    }

    public function setDatedepartprevue(\DateTimeImmutable $datedepartprevue): static
    {
        $this->datedepartprevue = $datedepartprevue;

        return $this;
    }

    public function getDatearriveeprevue(): ?\DateTimeImmutable
    {
        return $this->datearriveeprevue;
    }

    public function setDatearriveeprevue(?\DateTimeImmutable $datearriveeprevue): static
    {
        $this->datearriveeprevue = $datearriveeprevue;

        return $this;
    }

    public function getDatedepartreelle(): ?\DateTimeImmutable
    {
        return $this->datedepartreelle;
    }

    public function setDatedepartreelle(?\DateTimeImmutable $datedepartreelle): static
    {
        $this->datedepartreelle = $datedepartreelle;

        return $this;
    }

    public function getDatearriveereelle(): ?\DateTimeImmutable
    {
        return $this->datearriveereelle;
    }

    public function setDatearriveereelle(?\DateTimeImmutable $datearriveereelle): static
    {
        $this->datearriveereelle = $datearriveereelle;

        return $this;
    }

    public function getPlacesprevues(): ?int
    {
        return $this->placesprevues;
    }

    public function setPlacesprevues(?int $placesprevues): static
    {
        $this->placesprevues = $placesprevues;

        return $this;
    }

    public function getLigne(): ?Ligne
    {
        return $this->ligne;
    }

    public function setLigne(?Ligne $ligne): static
    {
        $this->ligne = $ligne;

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

    public function getCommercial(): ?User
    {
        return $this->commercial;
    }

    public function setCommercial(?User $commercial): static
    {
        $this->commercial = $commercial;

        return $this;
    }

    public function getGarecourante(): ?Gare
    {
        return $this->garecourante;
    }

    public function setGarecourante(?Gare $garecourante): static
    {
        $this->garecourante = $garecourante;

        return $this;
    }

    public function getGareprovenance(): ?Gare
    {
        return $this->gareprovenance;
    }

    public function setGareprovenance(?Gare $gareprovenance): static
    {
        $this->gareprovenance = $gareprovenance;

        return $this;
    }

    /**
     * Origine EFFECTIVE du voyage : la gare de provenance réelle si renseignée (départ partiel),
     * sinon l'origine de la ligne (voyage normal / legacy). Pivot de la doctrine « l'origine planifie ».
     */
    public function getOrigineEffective(): ?Gare
    {
        return $this->gareprovenance ?? $this->ligne?->getGareorigine();
    }

    /**
     * Ids des gares de la ROUTE EFFECTIVE (provenance réelle → terminus, dans l'ordre). Permet au
     * frontend de situer une gare (amont / sur la route / terminus) sans exposer toute la structure
     * des arrêts. Une gare EN AMONT de la provenance (départ partiel) n'y figure pas → aucune action
     * d'exploitation/réception (miroir de VoyageGuard::assertPeutGerer / assertPeutReceptionner).
     * @return int[]
     */
    #[Groups(['read:Voyage'])]
    #[SerializedName('routeeffectiveids')]
    public function getRouteEffectiveIds(): array
    {
        $ligne = $this->ligne;
        if ($ligne === null) {
            return [];
        }
        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $gare = $arret->getGare();
            if ($gare !== null) {
                $ordreParGare[$gare->getId()] = $arret->getOrdre();
            }
        }
        $origine = $this->getOrigineEffective();
        $ordreProvenance = $origine !== null ? ($ordreParGare[$origine->getId()] ?? 0) : 0;
        $surRoute = array_filter($ordreParGare, static fn($ordre) => $ordre >= $ordreProvenance);
        asort($surRoute);
        return array_map('intval', array_keys($surRoute));
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
            $detailpersonnel->setVoyage($this);
        }

        return $this;
    }

    public function removeDetailpersonnel(Detailpersonnel $detailpersonnel): static
    {
        if ($this->detailpersonnels->removeElement($detailpersonnel)) {
            // set the owning side to null (unless already changed)
            if ($detailpersonnel->getVoyage() === $this) {
                $detailpersonnel->setVoyage(null);
            }
        }

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
            $ticket->setVoyage($this);
        }

        return $this;
    }

    public function removeTicket(Ticket $ticket): static
    {
        if ($this->tickets->removeElement($ticket)) {
            // set the owning side to null (unless already changed)
            if ($ticket->getVoyage() === $this) {
                $ticket->setVoyage(null);
            }
        }

        return $this;
    }

    public function getPlacesTotal(): ?int
    {
        return $this->placestotal;
    }

    public function setPlacesTotal(?int $places_total): static
    {
        $this->placestotal = $places_total;

        return $this;
    }

    /* Nombre de billets ACTIFS (VALIDE, deletedAt IS NULL) vendus sur le voyage — remplace l'ancien compteur
       stocké 'placesoccupees'. Les billets reportés/annulés (désistements) ne comptent pas. Informatif : avec la
       vente PAR TRONÇON ce total peut dépasser 'placestotal' (un même siège est revendable sur des tronçons
       disjoints). La dispo réelle par tronçon = 'SiegeStateProvider'.
     */
    #[Groups(['read:Voyage'])]
    public function getTicketsCount(): int
    {
        /*
            'matching(Criteria)' et NON 'filter()' : sur une collection non chargée, Doctrine traduit le
            critère en SQL et 'count()' devient un COUNT — la collection n'est jamais hydratée. Avec
            'filter()', on chargeait TOUS les billets du voyage juste pour afficher un nombre, et ce sur
            CHAQUE ligne de la liste des voyages.
        */
        return $this->tickets->matching(
            Criteria::create()->where(
                Criteria::expr()->andX(
                    Criteria::expr()->isNull('deletedAt'),
                    Criteria::expr()->eq('statut', TicketStatus::STATUT_VALIDE->value)
                )
            )
        )->count();
    }

    /*
        Compteurs destinés à la LISTE des voyages (COUNT SQL, sans hydratation). Les collections
        correspondantes ne sont sérialisées que sur la FICHE ('read:Voyage:item'), qui les affiche
        réellement. On exclut les éléments supprimés (deletedAt), comme les listes affichées.
    */
    #[Groups(['read:Voyage'])]
    public function getCourriersCount(): int
    {
        return $this->courriers->matching(self::critereActif())->count();
    }

    #[Groups(['read:Voyage'])]
    public function getBagagesCount(): int
    {
        return $this->bagages->matching(self::critereActif())->count();
    }

    #[Groups(['read:Voyage'])]
    public function getDetailpersonnelsCount(): int
    {
        return $this->detailpersonnels->matching(self::critereActif())->count();
    }

    private static function critereActif(): Criteria
    {
        return Criteria::create()->where(Criteria::expr()->isNull('deletedAt'));
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        // Seuls les billets VALIDE bloquent la suppression ; les billets désistés sont de l'historique
        $ticketsNotDeleted = $this->tickets->filter(
            fn(Ticket $v) => $v->getDeletedAt() === null && $v->getStatut() === TicketStatus::STATUT_VALIDE->value
        );

        if(!$ticketsNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le voyage est liée à %d tickets(s) actif(s).',
                $ticketsNotDeleted->count()
            );
        }

        return $errors;
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
            $courrier->setVoyage($this);
        }

        return $this;
    }

    public function removeCourrier(Courrier $courrier): static
    {
        if ($this->courriers->removeElement($courrier)) {
            // set the owning side to null (unless already changed)
            if ($courrier->getVoyage() === $this) {
                $courrier->setVoyage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Bagage>
     */
    public function getBagages(): Collection
    {
        return $this->bagages;
    }

    /**
     * @return Collection<int, Passage>
     */
    public function getPassages(): Collection
    {
        return $this->passages;
    }

    /** @return array<int, array<string, mixed>> */
    public function getHoraires(): array
    {
        return $this->horaires;
    }

    /** @param array<int, array<string, mixed>> $horaires */
    public function setHoraires(array $horaires): static
    {
        $this->horaires = $horaires;

        return $this;
    }

    public function addBagage(Bagage $bagage): static
    {
        if (!$this->bagages->contains($bagage)) {
            $this->bagages->add($bagage);
            $bagage->setVoyage($this);
        }

        return $this;
    }

    public function removeBagage(Bagage $bagage): static
    {
        if ($this->bagages->removeElement($bagage)) {
            // set the owning side to null (unless already changed)
            if ($bagage->getVoyage() === $this) {
                $bagage->setVoyage(null);
            }
        }

        return $this;
    }

}
