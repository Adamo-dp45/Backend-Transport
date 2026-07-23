<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Domain\Enum\CourrierStatus;
use App\Entity\Dto\CourrierInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\MultiGareScopedInterface;
use App\Repository\CourrierRepository;
use App\State\AnnulerCourrierProcessor;
use App\State\CourrierProcessor;
use App\State\CourrierStatutProcessor;
use App\State\LivrerCourrierProcessor;
use App\State\PerduCourrierProcessor;
use App\State\ReceptionnerCourrierProcessor;
use App\State\SoftDeleteProcessor;
use ArrayObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: CourrierRepository::class)]
// La contrainte..
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Courrier', 'read:Base'], 'skip_null_values' => false],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Courrier')",
            openapi: new Operation(
                summary: 'Liste des courriers',
                description: 'Permet de voir la liste des courriers',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            normalizationContext: ['groups' => ['read:Courrier', 'read:Base', 'read:Courrier:item'], 'skip_null_values' => false], /*
                - Seule la FICHE (et par elle le reçu PDF / le formulaire d'édition) embarque la liste
                  des colis ; la LISTE des courriers n'en reçoit que le nombre.
            */
            openapi: new Operation(
                summary: 'Un courrier',
                description: 'Permet de voir un courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Courrier')",
            input: CourrierInput::class,
            processor: CourrierProcessor::class,
            denormalizationContext: ['groups' => ['write:CourrierInput']],
            openapi: new Operation(
                summary: 'Création d\'un courrier',
                description: 'Permet de créer un courrier avec ses colis',
                security: [['bearerAuth' => []]],
                requestBody: new RequestBody(
                    required: true,
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'nomexpediteur' => ['type' => 'string', 'example' => 'Bakayoko'],
                                    'contactexpediteur' => ['type' => 'string', 'example' => '054478845'],
                                    'nomdestinataire' => ['type' => 'string', 'example' => 'Sita'],
                                    'contactdestinataire' => ['type' => 'string', 'example' => '547788995'],
                                    'gareDepart' => ['type' => 'int', 'example' => 1],
                                    'gareArrivee' => ['type' => 'int', 'example' => 2],
                                    'voyage' => ['type' => 'int', 'example' => null],
                                    'fraissuivi' => ['type' => 'number', 'example' => 100],
                                    // 'modepaiement' => ['type' => 'string', 'example' => 'ENVOI'], // paiement courrier désactivé
                                    'details' => [ // -- Mieux vaut utiliser un dto pour éviter cette doc
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'nature' => ['type' => 'string', 'example' => 'Matériel électronique'],
                                                'designation' => ['type' => 'string', 'example' => 'CAPTEUR'],
                                                'emballage' => ['type' => 'string', 'example' => 'Sachet Blanc'],
                                                'type' => ['type' => 'string', 'example' => 'FRAGILE'],
                                                'poids' => ['type' => 'number', 'example' => 0.5],
                                                'valeur' => ['type' => 'number', 'example' => 13000]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            )
        ),
        new Patch( /*
            - Lorsqu'on modifie un courrier on supprime les anciens détails et on recrée
        */
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            input: CourrierInput::class,
            processor: CourrierProcessor::class,
            denormalizationContext: ['groups' => ['write:CourrierInput']],
            openapi: new Operation(
                summary: 'Modification d\'un courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/courriers/{id}/receptionner',
            requirements: ['id' => '\d+'],
            input: false,
            processor: ReceptionnerCourrierProcessor::class,
            openapi: new Operation(
                summary: 'Réceptionner un courrier à sa gare d\'arrivée',
                description: 'Accusé de réception « à l\'arrêt » : EN_TRANSIT -> RECEPTIONNE, par l\'agent de la gare d\'arrivée du colis',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/courriers/{id}/livrer',
            requirements: ['id' => '\d+'],
            input: false,
            processor: LivrerCourrierProcessor::class,
            openapi: new Operation(
                summary: 'Confirmer la livraison d\'un courrier',
                description: 'Marque le courrier comme livré au destinataire et gère le paiement à la réception',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/courriers/{id}/annuler',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AnnulerCourrierProcessor::class,
            openapi: new Operation(
                summary: 'Annuler un courrier',
                description: 'Annule un courrier en attente',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            uriTemplate: '/courriers/{id}/perdu',
            requirements: ['id' => '\d+'],
            input: false,
            processor: PerduCourrierProcessor::class,
            openapi: new Operation(
                summary: 'Déclarer un courrier perdu',
                description: 'Déclare un courrier en transit comme perdu',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('SUPPRIMER', object)",
            uriTemplate: '/courriers/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Suppression d\'un courrier',
                description: 'Permet de supprimer un courrier',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'codecourrier' => 'partial',
    'nomexpediteur' => 'partial',
    'nomdestinataire' => 'partial',
    'voyage.id' => 'exact',
    'garedepart.id' => 'exact',
    'garearrivee.id' => 'exact',
    'statut' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'codecourrier',
    'montant',
    'statut',
    'createdAt'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Courrier extends EntityBase implements EntrepriseOwnedInterface, MultiGareScopedInterface
{
    public static function gareScopeFields(): array
    {
        return ['garedepart', 'garearrivee'];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Courrier', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Courrier', 'read:Voyage'])]
    private ?string $codecourrier = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Courrier', 'write:Courrier', 'read:Voyage'])]
    private ?string $nomexpediteur = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Courrier', 'write:Courrier', 'read:Voyage'])]
    private ?string $contactexpediteur = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Courrier', 'write:Courrier', 'read:Voyage'])]
    private ?string $nomdestinataire = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Courrier', 'write:Courrier', 'read:Voyage'])]
    private ?string $contactdestinataire = null;

    // Obligatoire dès la création : un colis est défini par son origine et sa destination.
    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Courrier'])]
    private ?Gare $garedepart = null;

    // Unidirectionnel : Gare::$courriers ne suit que les départs (mappedBy garedepart)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Courrier'])]
    private ?Gare $garearrivee = null;

    // Optionnel : le voyage (quel car transporte le colis) est affecté à la création OU plus tard.
    #[ORM\ManyToOne(inversedBy: 'courriers')]
    #[Groups(['read:Courrier', 'write:Courrier'])]
    private ?Voyage $voyage = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Courrier', 'write:Courrier'])]
    private ?int $fraissuivi = null;

    #[ORM\Column(type: 'bigint')] // BIGINT : somme des taxes des colis
    #[Groups(['read:Courrier', 'read:Voyage'])]
    private ?int $montant = null;

    #[ORM\Column(length: 50)]
    #[Groups(['read:Courrier', 'read:Voyage'])]
    private ?string $statut = CourrierStatus::STATUT_EN_ATTENTE->value;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    /**
     * @var Collection<int, Detailcourrier>
     */
    #[ORM\OneToMany(targetEntity: Detailcourrier::class, mappedBy: 'courrier')]
    #[Groups(['read:Courrier:item'])] // fiche/reçu/édition — la liste utilise getDetailcourriersCount()
    private Collection $detailcourriers;
    /*
        #[ORM\Column(length: 50)]
        #[Groups(['read:Courrier'])]
        private ?string $modepaiement = 'ENVOI'; -- 'ENVOI', 'RECEPTION'

        #[ORM\Column(length: 50)]
        #[Groups(['read:Courrier'])]
        private ?string $etatpaiement = 'PAYE'; -- 'PAYE', 'EN_ATTENTE_PAIEMENT'

        #[ORM\Column(nullable: true)]
        private ?\DateTimeImmutable $datepaiement = null;
    */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Courrier'])]
    private ?\DateTimeImmutable $datelivraison = null; // Renseignée à la livraison (LivrerCourrierProcessor) → délai exact

    public function __construct()
    {
        $this->detailcourriers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodecourrier(): ?string
    {
        return $this->codecourrier;
    }

    public function setCodecourrier(string $codecourrier): static
    {
        $this->codecourrier = $codecourrier;

        return $this;
    }

    public function getNomexpediteur(): ?string
    {
        return $this->nomexpediteur;
    }

    public function setNomexpediteur(string $nomexpediteur): static
    {
        $this->nomexpediteur = $nomexpediteur;

        return $this;
    }

    public function getContactexpediteur(): ?string
    {
        return $this->contactexpediteur;
    }

    public function setContactexpediteur(string $contactexpediteur): static
    {
        $this->contactexpediteur = $contactexpediteur;

        return $this;
    }

    public function getNomdestinataire(): ?string
    {
        return $this->nomdestinataire;
    }

    public function setNomdestinataire(string $nomdestinataire): static
    {
        $this->nomdestinataire = $nomdestinataire;

        return $this;
    }

    public function getContactdestinataire(): ?string
    {
        return $this->contactdestinataire;
    }

    public function setContactdestinataire(string $contactdestinataire): static
    {
        $this->contactdestinataire = $contactdestinataire;

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

    public function getGarearrivee(): ?Gare
    {
        return $this->garearrivee;
    }

    public function setGarearrivee(?Gare $garearrivee): static
    {
        $this->garearrivee = $garearrivee;

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

    public function getFraissuivi(): ?int
    {
        return $this->fraissuivi;
    }

    public function setFraissuivi(?int $fraissuivi): static
    {
        $this->fraissuivi = $fraissuivi;

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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
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

    /**
     * @return Collection<int, Detailcourrier>
     */
    public function getDetailcourriers(): Collection
    {
        return $this->detailcourriers;
    }

    /*
        Nombre de colis, pour la LISTE des courriers. 'matching(Criteria)' → COUNT SQL sans hydrater :
        la liste n'affichait qu'un nombre mais recevait la collection entière (groupe 'read:Courrier:item'
        désormais, donc réservée à la fiche, au reçu et au formulaire d'édition qui l'exploitent vraiment).
    */
    #[Groups(['read:Courrier'])]
    public function getDetailcourriersCount(): int
    {
        return $this->detailcourriers->matching(
            Criteria::create()->where(Criteria::expr()->isNull('deletedAt'))
        )->count();
    }

    public function addDetailcourrier(Detailcourrier $detailcourrier): static
    {
        if (!$this->detailcourriers->contains($detailcourrier)) {
            $this->detailcourriers->add($detailcourrier);
            $detailcourrier->setCourrier($this);
        }

        return $this;
    }

    public function removeDetailcourrier(Detailcourrier $detailcourrier): static
    {
        if ($this->detailcourriers->removeElement($detailcourrier)) {
            // set the owning side to null (unless already changed)
            if ($detailcourrier->getCourrier() === $this) {
                $detailcourrier->setCourrier(null);
            }
        }

        return $this;
    }

    /*
        public function getModepaiement(): ?string
        {
            return $this->modepaiement;
        }

        public function setModepaiement(string $modepaiement): static
        {
            $this->modepaiement = $modepaiement;

            return $this;
        }

        public function getEtatpaiement(): ?string
        {
            return $this->etatpaiement;
        }

        public function setEtatpaiement(string $etatpaiement): static
        {
            $this->etatpaiement = $etatpaiement;

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
    */

    public function getDatelivraison(): ?\DateTimeImmutable
    {
        return $this->datelivraison;
    }

    public function setDatelivraison(?\DateTimeImmutable $datelivraison): static
    {
        $this->datelivraison = $datelivraison;

        return $this;
    }
}
