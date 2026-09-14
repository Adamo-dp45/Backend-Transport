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
use App\Domain\Enum\BagageStatus;
use App\Entity\Dto\BagageInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\MultiGareScopedInterface;
use App\Repository\BagageRepository;
use App\State\AnnulerBagageProcessor;
use App\State\BagageProcessor;
use App\State\EmbarquerBagageProcessor;
use App\State\PerduBagageProcessor;
use App\State\SoftDeleteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: BagageRepository::class)]
/*
    - Le CODE d'un bagage est imprimé sur l'étiquette remise au client : il doit être unique. Il ne
      l'était pas, et le générateur ('COUNT(*) + 1' sur les bagages non supprimés) réutilisait déjà un
      code après une mise en corbeille — en silence. L'unicité est portée PAR ENTREPRISE, parce que le
      code l'est aussi : 'BAG-2026-7' existe chez chaque compagnie.
    - La RÉFÉRENCE d'un enregistrement hors ligne est unique : clé d'idempotence qui permet de rejouer
      un lot de synchronisation sans dupliquer. Nulle au guichet.
*/
#[ORM\UniqueConstraint(name: 'uniq_bagage_codebagage', columns: ['identreprise', 'codebagage'])]
#[ORM\UniqueConstraint(name: 'uniq_bagage_reference_offline', columns: ['reference_offline'])]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Bagage', 'read:Base']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Bagage')",
            openapi: new Operation(
                summary: 'Liste des bagages',
                description: 'Permet de voir la liste des bagages',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'Un bagage',
                description: 'Permet de voir un bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Bagage')",
            input: BagageInput::class,
            processor: BagageProcessor::class,
            denormalizationContext: ['groups' => ['write:BagageInput']],
            openapi: new Operation(
                summary: 'Enregistrement d\'un bagage',
                description: 'Permet de créer un bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            input: BagageInput::class,
            processor: BagageProcessor::class,
            denormalizationContext: ['groups' => ['write:BagageInput']],
            openapi: new Operation(
                summary: 'Modification d\'un bagage',
                security: [['bearerAuth' => []]]
            )
        ),
        /*
            new Patch(
                security: "is_granted('MODIFIER', object)",
                uriTemplate: '/bagages/{id}/embarquer',
                requirements: ['id' => '\d+'],
                input: false,
                processor: EmbarquerBagageProcessor::class,
                openapi: new Operation(
                    summary: 'Embarquer un bagage',
                    description: 'Marque le bagage comme embarqué sur le voyage',
                    security: [['bearerAuth' => []]]
                )
            ),
        */
        new Patch(
            // Réservé à l'ADMIN d'entreprise : déclarer une perte engage la responsabilité de la compagnie
            // vis-à-vis du client. Ce n'est pas une correction de saisie.
            /*
                Action DÉDIÉE, pas 'MODIFIER' : déclarer une perte engage la responsabilité de la
                compagnie vis-à-vis du client. Ce n'est pas une correction de saisie, et cela ne doit
                pas venir avec elle.
            */
            security: "is_granted('DECLARER_PERDU', object)",
            uriTemplate: '/bagages/{id}/perdu',
            requirements: ['id' => '\d+'],
            input: false,
            processor: PerduBagageProcessor::class,
            openapi: new Operation(
                summary: 'Déclarer un bagage perdu',
                description: 'Déclare le bagage comme perdu',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/bagages/{id}/annuler',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AnnulerBagageProcessor::class,
            openapi: new Operation(
                summary: 'Annuler un bagage',
                description: 'Annule un bagage encore ENREGISTRE (non embarqué), par sa gare de dépôt',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')", // suppression d'un document comptable : admin d'entreprise UNIQUEMENT (la sortie normale est l'annulation, tracée)
            uriTemplate: '/bagages/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Suppression d\'un bagage',
                description: 'Permet de supprimer un bagage',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'codebagage' => 'partial',
    'voyage.id' => 'exact',
    'statut' => 'exact',
    'ticket.client.id' => 'exact' // bagages d'un client (fiche client), via le billet rattaché
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'codebagage',
    'poids',
    'montant',
    'statut',
    'createdAt'
])]
class Bagage extends EntityBase implements EntrepriseOwnedInterface, MultiGareScopedInterface
{
    public static function gareScopeFields(): array
    {
        return ['garedepart', 'garedescente'];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?string $codebagage = null;

    /*
        - nomclient / contactclient retirés de l'entité : l'identité du client provient désormais du billet
          rattaché (bagage.ticket.nomclient / bagage.ticket.contactclient). Colonnes supprimées par migration.
        #[ORM\Column(length: 255)]
        #[Groups(['read:Bagage', 'read:Voyage'])]
        private ?string $nomclient = null;

        #[ORM\Column(length: 255)]
        #[Groups(['read:Bagage', 'read:Voyage'])]
        private ?string $contactclient = null;
    */

    #[ORM\Column(length: 255)]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?string $nature = null; // valise, sac, carton, vélo..

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?string $type = null; // LEGER, LOURD, VOLUMINEUX, FRAGILE

    #[ORM\Column]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?int $poids = null; // La base du calcul

    #[ORM\Column]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?int $montant = null; // On le calcule via 'Tarifbagage' ou forcé manuellement

    #[ORM\Column]
    #[Groups(['read:Bagage'])]
    private ?bool $montantforce = false; // 'true' si l'agent a modifié le montant calculé

    #[ORM\ManyToOne(inversedBy: 'bagages')]
    #[Groups(['read:Bagage'])]
    private ?Voyage $voyage = null;

    /**
     * Billet du client auquel le bagage est rattaché — OBLIGATOIRE. Le bagage SUIT le billet :
     * provenance (garedepart) = gare de montée du billet, destination (garedescente) = sa descente,
     * voyage = celui du billet, identité = celle du billet, et le CANAL de vente (guichet/commercial)
     * en dérive (bagage.ticket.commercial). L'annulation du billet annule le bagage.
     */
    #[ORM\ManyToOne(inversedBy: 'bagages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?Ticket $ticket = null;

    /**
     * Commercial (vendeur À BORD) ayant enregistré ce bagage, FIGÉ à l'enregistrement (snapshot).
     * Non nul UNIQUEMENT si l'agent qui enregistre est le commercial du voyage : la recette du bagage
     * va alors AU COMMERCIAL et non à la gare de dépôt. Nul = enregistrement au guichet → recette gare.
     * (On ne dérive PAS du billet : le commercial pourrait rattacher un billet qui n'est pas le sien.)
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Bagage'])]
    private ?User $commercial = null;

    #[ORM\ManyToOne]
    #[Groups(['read:Bagage', 'read:Voyage', 'write:Bagage'])]
    private ?Gare $garedepart = null; // Gare d'origine du bagage (= gare de l'agent s'il y est rattaché)

    #[ORM\ManyToOne]
    #[Groups(['read:Bagage', 'read:Voyage', 'write:Bagage'])]
    private ?Gare $garedescente = null; // Gare de descente du bagage (où le client récupère)

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Bagage', 'read:Voyage'])]
    private ?string $statut = BagageStatus::STATUT_ENREGISTRE->value; // ENREGISTRE, EMBARQUE, LIVRE, PERDU

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * Référence de l'ENREGISTREMENT HORS LIGNE qui a produit ce bagage — clé d'IDEMPOTENCE.
     *
     * Même rôle que {@see Ticket::$referenceOffline} : générée par le téléphone du vendeur à bord,
     * elle rend le rejeu d'un lot de synchronisation sans effet la seconde fois. Nulle au guichet.
     */
    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['read:Bagage'])]
    private ?string $referenceOffline = null;

    /**
     * Montant réellement ENCAISSÉ à bord, quand l'enregistrement s'est fait hors ligne.
     *
     * Le téléphone facture depuis la grille de poids téléchargée ; si un administrateur la modifie
     * pendant le trajet, le montant perçu en espèces peut différer de celle qui fait foi à la
     * synchronisation. Le serveur reste seul juge de {@see $montant} — l'écart est consigné pour que
     * la gare régularise. À ne pas confondre avec {@see $montantforce}, qui dit que l'AGENT a
     * volontairement facturé hors grille.
     */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Bagage'])]
    private ?int $montantEncaisse = null;

    #[ORM\ManyToOne(inversedBy: 'bagages')]
    #[Groups(['read:Bagage'])]
    private ?Tarifbagage $tarifbagage = null; // NULL si montant forcé sans tarif correspondant et on le conserve pour l'historique même si la grille change

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodebagage(): ?string
    {
        return $this->codebagage;
    }

    public function setCodebagage(string $codebagage): static
    {
        $this->codebagage = $codebagage;

        return $this;
    }

    /*
        - Getters/setters nomclient / contactclient retirés (colonnes supprimées). Utiliser le billet :
          $bagage->getTicket()?->getNomclient() / ->getContactclient().
        public function getNomclient(): ?string { return $this->nomclient; }
        public function setNomclient(string $nomclient): static { $this->nomclient = $nomclient; return $this; }
        public function getContactclient(): ?string { return $this->contactclient; }
        public function setContactclient(string $contactclient): static { $this->contactclient = $contactclient; return $this; }
    */

    public function getNature(): ?string
    {
        return $this->nature;
    }

    public function setNature(string $nature): static
    {
        $this->nature = $nature;

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

    public function getPoids(): ?int
    {
        return $this->poids;
    }

    public function setPoids(int $poids): static
    {
        $this->poids = $poids;

        return $this;
    }

    public function getMontant(): ?int
    {
        return $this->montant;
    }

    public function setMontant(int $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function isMontantforce(): ?bool
    {
        return $this->montantforce;
    }

    public function setMontantforce(bool $montantforce): static
    {
        $this->montantforce = $montantforce;

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

    public function getTicket(): ?Ticket
    {
        return $this->ticket;
    }

    public function setTicket(?Ticket $ticket): static
    {
        $this->ticket = $ticket;

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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

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

    public function getTarifbagage(): ?Tarifbagage
    {
        return $this->tarifbagage;
    }

    public function setTarifbagage(?Tarifbagage $tarifbagage): static
    {
        $this->tarifbagage = $tarifbagage;

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

    public function getGaredepart(): ?Gare
    {
        return $this->garedepart;
    }

    public function setGaredepart(?Gare $garedepart): static
    {
        $this->garedepart = $garedepart;

        return $this;
    }

    public function getReferenceOffline(): ?string
    {
        return $this->referenceOffline;
    }

    public function setReferenceOffline(?string $referenceOffline): static
    {
        $this->referenceOffline = $referenceOffline;

        return $this;
    }

    public function getMontantEncaisse(): ?int
    {
        return $this->montantEncaisse;
    }

    public function setMontantEncaisse(?int $montantEncaisse): static
    {
        $this->montantEncaisse = $montantEncaisse;

        return $this;
    }
}
