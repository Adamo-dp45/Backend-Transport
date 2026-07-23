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
use App\Entity\Dto\DescendreInput;
use App\Entity\Dto\DesistementInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\LigneGareScopedInterface;
use App\Repository\TicketRepository;
use App\State\DescendreTicketProcessor;
use App\State\DesistementProcessor;
use App\State\SoftDeleteProcessor;
use App\State\TicketProcessor;
use App\State\TicketProvider;
use App\State\TicketUpdateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TicketRepository::class)]
/*
    - Plus d'unicité (voyage, siege) : un siège peut porter plusieurs tickets sur le même voyage
      tant que leurs tronçons [montée, descente) ne se chevauchent pas (cf. TicketProcessor).
*/
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Ticket', 'read:Base'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:Ticket']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Ticket') or is_granted('ROLE_USER')",
            provider: TicketProvider::class, // + repère 'evince' (dérivé), pipeline natif conservé
            openapi: new Operation(
                summary: 'Liste des tickets',
                description: 'Permet de voir la liste des tickets',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_USER')",
            requirements: ['id' => '\d+'],
            provider: TicketProvider::class,
            openapi: new Operation(
                summary: 'Le ticket',
                description: 'Permet de voir un ticket',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Ticket')",
            processor: TicketProcessor::class,
            openapi: new Operation(
                summary: 'Création du ticket',
                description: 'Permet de créer un ticket',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: TicketUpdateProcessor::class, /*
                - Gardes métier du billet (clôture, gare émettrice, car déjà passé à la montée) +
                  re-résolution du Client quand le téléphone change. Cf. TicketUpdateProcessor.
            */
            denormalizationContext: ['groups' => ['write:Ticket:update']],
            openapi: new Operation(
                summary: 'Modification du ticket',
                description: 'Permet de modifier un ticket',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/tickets/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du ticket',
                description: 'Permet de mettre un ticket en corbeille',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/tickets/{id}/desister',
            requirements: ['id' => '\d+'],
            input: DesistementInput::class,
            processor: DesistementProcessor::class,
            denormalizationContext: ['groups' => ['write:DesistementInput']],
            openapi: new Operation(
                summary: 'Désistement d\'un billet (report ou annulation)',
                description: 'Libère le siège du billet d\'origine. Mode REPORT : crée un nouveau billet sur un voyage de la même ligne (tronçon et prix conservés). Mode ANNULATION : annule et rembourse le billet.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('CREER', 'Ticket')", /*
                - L'agent qui vend des billets peut libérer un siège : il enregistre que le passager
                  descend en route (avant sa descente vendue) pour pouvoir revendre le siège en aval.
            */
            uriTemplate: '/tickets/{id}/descendre',
            requirements: ['id' => '\d+'],
            input: DescendreInput::class,
            processor: DescendreTicketProcessor::class,
            denormalizationContext: ['groups' => ['write:DescendreInput']],
            openapi: new Operation(
                summary: 'Le passager descend en cours de route (libère le siège pour revente)',
                description: 'Enregistre la gare de descente RÉELLE (en amont de la descente vendue) sur le billet : libère le siège en aval sans toucher au prix ni à la recette, afin de le revendre.',
                security: [['bearerAuth' => []]]
            )
        ),
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'codeticket' => 'partial',
    'nomclient' => 'partial', // recherche d'un billet par nom du client (autocomplete rattachement bagage)
    'voyage.id' => 'exact', /*
        - Le filtre exact sur la relation '?voyage=/api/voyages/5' mais on peut s'en passer vu qu'on a le 'read:Ticket'
    */
    'client.id' => 'exact', // billets d'un client donné (fiche client)
    'gare.id' => 'exact', // filtre par gare émettrice (listing, réservé admin/central)
    'commercial.id' => 'exact', // billets vendus à bord par un commercial donné (rattachement bagage côté commercial)
    'statut' => 'exact', // VALIDE | REPORTE | ANNULE — pour filtrer l'historique des désistements
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'codeticket',
    'prix',
    'createdAt'
])]
class Ticket extends EntityBase implements EntrepriseOwnedInterface, LigneGareScopedInterface
{
    public static function ligneScopePath(): array
    {
        return ['voyage', 'ligne']; // billetterie : tickets des voyages dont la ligne dessert sa gare
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Ticket', 'read:Voyage', 'read:Bagage', 'read:Reservation'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)] // Sans 'onDelete: 'CASCADE' pour l'historique
    #[Groups(['read:Ticket', 'write:Ticket'])]
    private ?Voyage $voyage = null;

    /**
     * Bagages rattachés à ce billet (le bagage SUIT le billet) — côté inverse. Sert notamment au
     * décompte des bagages d'un client (Client::getBagagesCount), le bagage n'ayant pas de lien direct
     * au client. Non sérialisé (pas de groupe) pour ne pas alourdir la charge du billet.
     * @var Collection<int, Bagage>
     */
    #[ORM\OneToMany(targetEntity: Bagage::class, mappedBy: 'ticket')]
    private Collection $bagages;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Voyage', 'read:Ticket', 'read:Bagage', 'write:Ticket', 'write:Ticket:update'])]
    private ?string $nomclient = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Voyage', 'read:Ticket', 'read:Bagage', 'write:Ticket', 'write:Ticket:update'])]
    private ?string $contactclient = null;

    /**
     * Identité durable du passager (dédupliquée par téléphone). Rattachée automatiquement au point
     * de vente via ClientResolver à partir de nomclient/contactclient ; nullable (snapshot conservé,
     * pas de client si aucun téléphone). Prépare fidélité & réservation.
     */
    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Ticket', 'read:Voyage'])]
    private ?Client $client = null;

    #[ORM\Column]
    #[Groups(['read:Voyage', 'read:Ticket'])]
    private ?int $prix = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Voyage', 'read:Ticket', 'read:Bagage', 'read:Reservation'])]
    private ?string $codeticket = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ticket', 'read:Voyage', 'write:Ticket'])]
    private ?Siege $siege = null;

    #[ORM\ManyToOne(inversedBy: 'tickets')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ticket', 'read:Voyage', 'read:Bagage', 'write:Ticket'])]
    private ?Gare $gare = null; // Gare de MONTÉE / émission, on peut le déduire de la gare de l'utilisateur

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ticket', 'read:Voyage', 'read:Bagage', 'write:Ticket'])]
    private ?Gare $garedescente = null; // Gare de DESCENTE VENDUE — fixe le prix via la grille globale Tarif(gare, garedescente)

    /**
     * Gare de descente RÉELLE (passager descendu en cours de route, avant sa descente vendue).
     * Ne touche NI au prix NI à la recette (le client a payé jusqu'à 'garedescente') : sert UNIQUEMENT
     * à libérer le siège en aval pour permettre sa revente. Renseignée via /tickets/{id}/descendre.
     * L'occupation d'un siège ('td') vaut donc 'garedescentereelle ?? garedescente'.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Ticket', 'read:Voyage'])]
    private ?Gare $garedescentereelle = null;

    /**
     * Commercial (vendeur À BORD) ayant vendu ce billet, FIGÉ au moment de la vente (snapshot — donc
     * insensible à un changement ultérieur de 'voyage.commercial'). Non nul UNIQUEMENT pour une vente
     * en route : la recette est alors attribuée AU COMMERCIAL et non à la gare de montée (= position
     * courante du car). Nul = vente au guichet → recette rattachée à la gare de montée.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Ticket', 'read:Voyage'])]
    private ?User $commercial = null;

    /**
     * Réservation d'ORIGINE, si ce billet a été émis depuis un bon de réservation (nul sinon). La
     * réservation a été payée sur le compte (mobile/bancaire) de l'administrateur, PAS dans le tiroir
     * de la gare → ce billet est exclu de la recette gare (3e canal, cf. $commercial pour le à-bord).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['read:Ticket'])] // exposé (IRI) pour distinguer un billet de réservation d'une vente directe
    private ?Reservation $reservation = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['read:Ticket', 'read:Voyage'])]
    private int $remise = 0; // Montant déduit (FCFA), calculé par le processor ; prix = tarif - remise

    #[ORM\ManyToOne]
    #[Groups(['read:Ticket', 'read:Voyage', 'write:Ticket'])]
    private ?Beneficiaire $beneficiaire = null; // Bénéficiaire de la remise (obligatoire si remise > 0)

    /**
     * Billet émis comme RÉCOMPENSE de fidélité (carte à tampons) : ne compte pas comme un tampon et
     * consomme 'seuil' tampons. La remise correspondante est appliquée par le TicketProcessor.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['read:Ticket', 'read:Voyage', 'write:Ticket'])]
    private bool $fideliteRecompense = false;

    // -- Désistement (report / annulation) -- //

    #[ORM\Column(length: 20, options: ['default' => 'VALIDE'])]
    #[Groups(['read:Ticket', 'read:Voyage'])]
    private string $statut = 'VALIDE'; // VALIDE | REPORTE | ANNULE (cf. App\Domain\Enum\TicketStatus)

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)] // Renseigné sur le billet issu d'un REPORT : pointe vers le billet désisté
    #[Groups(['read:Ticket'])]
    private ?Ticket $ticketOrigine = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['read:Ticket'])]
    private ?\DateTimeImmutable $datedesistement = null; // Horodatage du report/annulation

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Ticket'])]
    private ?string $motifdesistement = null;

    /**
     * Désistement IMPUTABLE À LA COMPAGNIE : ce billet a été REPORTE parce qu'il était ÉVINCÉ (siège
     * repris par la priorité amont), pas parce que le client a renoncé. PERSISTÉ au moment du report —
     * contrairement à {@see $evince} (dérivé), car une fois le billet passé REPORTE il sort de
     * billetsEvinces() et l'information ne pourrait plus être recalculée pour les statistiques
     * historiques. Posé automatiquement par DesistementProcessor (détection au report), jamais saisi.
     *
     * Sert à ne pas gonfler le TAUX de désistement avec des relogements que la compagnie a provoqués :
     * un report d'éviction n'est pas un désistement volontaire.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['read:Ticket'])]
    private bool $desistementImputableCompagnie = false;

    /**
     * ÉVINCÉ par la priorité amont : à SON propre point de montée, le siège de ce billet est déjà
     * occupé par un passager monté plus tôt. Le surbooking amont étant assumé, ce passager ne montera
     * pas — sa gare (= la gare émettrice) doit le reloger sur un autre départ.
     *
     * NON PERSISTÉ, entièrement DÉRIVÉ de l'état courant du voyage (cf. CapaciteService::billetsEvinces)
     * et posé par {@see App\State\TicketProvider}. Le stocker le ferait dériver : si l'occupant amont
     * se désiste ou descend en route, le siège se libère et ce billet redevient légitime — un statut
     * figé continuerait, lui, d'annoncer une éviction résolue.
     *
     * Exposé sur le SEUL groupe 'read:Ticket', celui que sert TicketProvider : les sérialisations
     * imbriquées ('read:Voyage', 'read:Bagage'…) ne passent pas par lui et afficheraient un 'false'
     * mensonger.
     */
    #[Groups(['read:Ticket'])]
    private bool $evince = false;

    /*
        - Entrées TRANSITOIRES (non persistées) : l'agent saisit un type + une valeur,
          le TicketProcessor calcule le montant de la remise et le prix net.
    */
    #[Groups(['write:Ticket'])]
    private ?string $remisetype = null; // 'MONTANT' | 'POURCENTAGE'

    #[Groups(['write:Ticket'])]
    private ?int $remisevaleur = null; // montant en FCFA, ou pourcentage selon remisetype

    public function getId(): ?int
    {
        return $this->id;
    }

    public function __construct()
    {
        $this->bagages = new ArrayCollection();
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

    /** @return Collection<int, Bagage> */
    public function getBagages(): Collection
    {
        return $this->bagages;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

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

    public function getCodeticket(): ?string
    {
        return $this->codeticket;
    }

    public function setCodeticket(string $codeticket): static
    {
        $this->codeticket = $codeticket;

        return $this;
    }

    public function getSiege(): ?Siege
    {
        return $this->siege;
    }

    public function setSiege(?Siege $siege): static
    {
        $this->siege = $siege;

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

    public function getGaredescentereelle(): ?Gare
    {
        return $this->garedescentereelle;
    }

    public function setGaredescentereelle(?Gare $garedescentereelle): static
    {
        $this->garedescentereelle = $garedescentereelle;

        return $this;
    }

    /**
     * Gare effective de descente pour le calcul d'OCCUPATION du siège :
     * la descente réelle si le passager est descendu en route, sinon la descente vendue.
     */
    public function getGaredescenteEffective(): ?Gare
    {
        return $this->garedescentereelle ?? $this->garedescente;
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

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): static
    {
        $this->reservation = $reservation;

        return $this;
    }

    public function getRemise(): int
    {
        return $this->remise;
    }

    public function setRemise(int $remise): static
    {
        $this->remise = $remise;

        return $this;
    }

    public function getBeneficiaire(): ?Beneficiaire
    {
        return $this->beneficiaire;
    }

    public function setBeneficiaire(?Beneficiaire $beneficiaire): static
    {
        $this->beneficiaire = $beneficiaire;

        return $this;
    }

    public function isFideliteRecompense(): bool
    {
        return $this->fideliteRecompense;
    }

    public function setFideliteRecompense(bool $fideliteRecompense): static
    {
        $this->fideliteRecompense = $fideliteRecompense;

        return $this;
    }

    public function getRemisetype(): ?string
    {
        return $this->remisetype;
    }

    public function setRemisetype(?string $remisetype): static
    {
        $this->remisetype = $remisetype;

        return $this;
    }

    public function getRemisevaleur(): ?int
    {
        return $this->remisevaleur;
    }

    public function setRemisevaleur(?int $remisevaleur): static
    {
        $this->remisevaleur = $remisevaleur;

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

    public function getTicketOrigine(): ?Ticket
    {
        return $this->ticketOrigine;
    }

    public function setTicketOrigine(?Ticket $ticketOrigine): static
    {
        $this->ticketOrigine = $ticketOrigine;

        return $this;
    }

    public function getDatedesistement(): ?\DateTimeImmutable
    {
        return $this->datedesistement;
    }

    public function setDatedesistement(?\DateTimeImmutable $datedesistement): static
    {
        $this->datedesistement = $datedesistement;

        return $this;
    }

    public function getMotifdesistement(): ?string
    {
        return $this->motifdesistement;
    }

    public function setMotifdesistement(?string $motifdesistement): static
    {
        $this->motifdesistement = $motifdesistement;

        return $this;
    }

    public function isEvince(): bool
    {
        return $this->evince;
    }

    public function setEvince(bool $evince): static
    {
        $this->evince = $evince;

        return $this;
    }

    public function isDesistementImputableCompagnie(): bool
    {
        return $this->desistementImputableCompagnie;
    }

    public function setDesistementImputableCompagnie(bool $desistementImputableCompagnie): static
    {
        $this->desistementImputableCompagnie = $desistementImputableCompagnie;

        return $this;
    }
}
