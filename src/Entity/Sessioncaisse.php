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
use App\Entity\Dto\ClotureCaisseInput;
use App\Entity\Dto\OuvertureCaisseInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\GareOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\SessioncaisseRepository;
use App\State\CloturerCaisseProcessor;
use App\State\OuvrirCaisseProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * La CAISSE d'un agent sur une période de travail : ce qu'il a encaissé, ce qu'il a compté, l'écart.
 *
 * L'application sait au franc près ce qu'un guichet AURAIT DÛ encaisser ('RecetteGareService', trois
 * canaux) et ne rapproche ce chiffre de l'argent RÉELLEMENT DANS LE TIROIR nulle part. Pour une
 * compagnie où presque tout se paie en espèces, un écart de caisse ne se détecte aujourd'hui que par
 * recoupement manuel, a posteriori, et sans rien d'opposable à l'agent.
 *
 * !! CE N'EST PAS UN POT D'ARGENT DE PLUS, c'est une PÉRIODE DE RESPONSABILITÉ. La session ne crée
 * ni recette ni charge : elle RAPPROCHE. Ne jamais l'ajouter à un total existant, et ne JAMAIS
 * soustraire un écart du bénéfice — ce serait le double comptage technique que le README interdit.
 *
 * !! À NE PAS CONFONDRE AVEC LE SOLDE d'une gare (chantier suivant, conditionnel) : la caisse compte
 * ce qu'UN guichetier a physiquement encaissé sur SA journée, le solde compte ce que la GARE détient
 * en tout. C'est pour cela qu'aucune DÉPENSE ne touche à une session : une dépense sort du coffre du
 * chef de gare, jamais du tiroir d'un guichet.
 *
 * L'AGENT EST LA SEULE IDENTITÉ d'une caisse — pas le poste, pas le guichet physique. Un écart
 * appartient donc toujours à une personne et à une journée, ce qui est la condition pour qu'il soit
 * opposable.
 */
#[ORM\Entity(repositoryClass: SessioncaisseRepository::class)]
/*
    - Index déclarés ICI et pas seulement dans la migration : la base de TEST est construite par
      'doctrine:schema:update' (cf. 'make test-db'), qui ne connaît que les mappings — un index posé
      par la seule migration n'y existerait pas, et les tests mesureraient un autre schéma
*/
#[ORM\Index(name: 'idx_sessioncaisse_ent_gare_debut', columns: ['identreprise', 'gare_id', 'datedebut'])]
#[ORM\Index(name: 'idx_sessioncaisse_agent_statut', columns: ['agent_id', 'statut'])]
#[ORM\UniqueConstraint(name: 'uniq_sessioncaisse_agent_ouverte', columns: ['identreprise', 'agentsessionouverte'])] /*
    - UNE SEULE SESSION OUVERTE PAR AGENT, tenue par la BASE et non par une relecture applicative :
      deux ventes simultanées du même agent pourraient sinon lui ouvrir deux caisses, et ses
      encaissements se répartiraient entre elles au hasard
    - !! MySQL n'a pas d'index unique PARTIEL ('WHERE statut = OUVERTE'), d'où cette colonne qui
      porte l'identifiant de l'agent TANT QUE la session est ouverte et retombe à NULL à la clôture :
      les NULL échappent à l'unicité, un agent peut donc avoir autant de sessions CLOTUREE qu'il a
      travaillé de journées. Une colonne dérivée, mais qui existe pour une CONTRAINTE et non pour
      être lue — même motif que 'Voyage::$jourdepart'
*/
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Sessioncaisse', 'read:Base']], /*
        - AUCUN 'denormalizationContext' global, et aucune propriété n'est écrivable directement :
          les deux seuls gestes d'écriture (ouvrir, clôturer) passent par un DTO, chacun déclarant
          le sien sur son opération. Une caisse ne se modifie pas champ par champ — laisser un
          groupe d'écriture ici rendrait un jour 'montanttheorique' ou 'ecart' saisissables
    */
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['datedebut' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Sessioncaisse')", /*
                - Le périmètre de GARE est porté par 'GareScopeExtension' (cf. gareScopeField)
                - !! IL MANQUE ENCORE le bornage « un agent ne voit que SES caisses » : il vient avec
                  'CaisseScopeExtension' au palier suivant. En l'état, un agent habilité à VOIR
                  lirait les caisses de ses collègues de la même gare
            */
            openapi: new Operation(
                summary: 'Liste des caisses',
                description: 'Permet de voir la liste des sessions de caisse',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'La caisse',
                description: 'Permet de voir une session de caisse',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Sessioncaisse')",
            input: OuvertureCaisseInput::class,
            processor: OuvrirCaisseProcessor::class,
            denormalizationContext: ['groups' => ['write:OuvertureCaisseInput']],
            openapi: new Operation(
                summary: 'Ouverture de sa caisse',
                description: 'Ouvre la caisse de l’utilisateur courant avec le fonds avancé par le chef de gare',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('CLOTURER', object)", /*
                - Permission DÉDIÉE et non 'MODIFIER' : arrêter une caisse et signer son écart n'est
                  pas rectifier une saisie. Sous 'MODIFIER', tout profil autorisé à corriger une
                  ligne pourrait clôturer la caisse d'un collègue — même raison que 'DESISTER' sur
                  'Ticket'
            */
            uriTemplate: '/sessioncaisses/{id}/cloturer',
            requirements: ['id' => '\d+'],
            input: ClotureCaisseInput::class,
            processor: CloturerCaisseProcessor::class,
            denormalizationContext: ['groups' => ['write:ClotureCaisseInput']],
            openapi: new Operation(
                summary: 'Clôture de la caisse',
                description: 'Fige les totaux, constate l’écart (motif obligatoire s’il n’est pas nul) et ferme la caisse',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'statut' => 'exact',
    'agent.id' => 'exact',
    'gare.id' => 'exact',
])] /*
    - !! UN FILTRE NON DÉCLARÉ EST IGNORÉ EN SILENCE par ApiPlatform, qui rend alors la collection
      ENTIÈRE avec un 200 parfaitement crédible. L'écran des caisses se filtre sur le statut et sur
      l'agent : les deux sont ici, et un test le garde
*/
#[ApiFilter(OrderFilter::class, properties: ['id', 'datedebut', 'datefin', 'ecart'])]
#[ApiFilter(DateFilter::class, properties: ['datedebut'])]
class Sessioncaisse extends EntityBase implements EntrepriseOwnedInterface, GareOwnedInterface, HasSoftDeleteGuard
{
    use IdEntrepriseTrait;

    /** Périmètre C : une caisse appartient à LA gare où l'agent tient son guichet. */
    public static function gareScopeField(): string
    {
        return 'gare';
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $id = null;

    /**
     * Le titulaire. NON NUL et sans valeur de repli : une caisse sans agent ne serait opposable à
     * personne, et c'est tout ce qu'on lui demande.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT' — on ne supprime pas la preuve avec le compte
    #[Groups(['read:Sessioncaisse'])]
    private ?User $agent = null;

    /**
     * La gare où l'agent encaisse, FIGÉE à l'ouverture. Recopiée depuis 'User::$gare' plutôt que lue
     * à travers l'agent : muter un agent d'une gare à l'autre déplacerait sinon ses caisses passées.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Sessioncaisse'])]
    private ?Gare $gare = null;

    #[ORM\Column]
    #[Groups(['read:Sessioncaisse'])]
    private ?\DateTimeImmutable $datedebut = null;

    /** NULL tant que la caisse est ouverte. */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?\DateTimeImmutable $datefin = null;

    /**
     * Le fonds de caisse avancé par le chef de gare à la prise de poste — la monnaie pour rendre.
     * Il fait partie du théorique parce qu'il est PHYSIQUEMENT dans le tiroir au moment du comptage.
     */
    #[ORM\Column(type: 'bigint', options: ['default' => 0])]
    #[Groups(['read:Sessioncaisse'])]
    private int $fondsouverture = 0;

    /**
     * L'agent n'a pas ouvert sa caisse : sa première vente l'a fait pour lui, fonds à zéro.
     *
     * AUCUNE GARDE BLOQUANTE nulle part, et c'est délibéré : le guichet ne s'arrête jamais sur une
     * procédure oubliée, et aucune vente ne reste orpheline. Le drapeau sert à le SIGNALER (alerte
     * de gare, palier 5), jamais à l'empêcher.
     */
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['read:Sessioncaisse'])]
    private bool $ouvertureautomatique = false;

    /**
     * LES HUIT POSTES DU THÉORIQUE, gelés à la clôture — un par ligne de la formule, pour que le
     * ticket imprimé se relise poste par poste et qu'un écart se cherche là où il est.
     *
     * !! PERSISTÉS, contre la doctrine maison du « rien de dérivable stocké ». La clôture est une
     * PIÈCE OPPOSABLE : un tarif corrigé ou une annulation tardive déplaceraient un chiffre
     * recalculé, et l'écart signé par l'agent ne voudrait plus rien dire. Même exception assumée que
     * 'Ticket::$desistementImputableCompagnie'. Ils valent NULL tant que la caisse est OUVERTE —
     * null et non zéro : « pas encore compté » ne s'écrit pas comme « rien encaissé ».
     *
     * Les frais de suivi ont LEUR PROPRE LIGNE parce qu'ils sont un encaissement DISTINCT du montant
     * du courrier (deux lignes sur le reçu du client, cf. README module Courrier) ; pénalité et
     * complément de régularisation aussi, pour la même raison.
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalbillets = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalbagages = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalcourriers = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalfraissuivi = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalreservations = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalpenalites = null;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalcomplements = null;

    /**
     * LE SEUL POSTE EN SORTIE : ce que l'agent a rendu de son tiroir à un client qui se désiste.
     * Dépenses et versements n'y sont pas — ils sortent du coffre du chef de gare (cf. l'en-tête).
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $totalremboursements = null;

    /** La somme des huit postes, plus le fonds d'ouverture. Ce que le tiroir DEVRAIT contenir. */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $montanttheorique = null;

    /** Ce que l'agent a réellement compté. */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $montantcompte = null;

    /**
     * 'montantcompte − montanttheorique'. SIGNÉ, et surtout AUCUN 'Assert\Positive' : un manquant
     * est négatif, c'est le cas qu'on cherche. Un excédent l'est tout autant — il signale une erreur
     * de rendu de monnaie ou une vente non saisie.
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?int $ecart = null;

    /** OBLIGATOIRE dès que l'écart n'est pas nul (garde posée à la clôture, palier 2). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Sessioncaisse'])]
    private ?string $motifecart = null;

    #[ORM\Column(length: 20, options: ['default' => 'OUVERTE'])]
    #[Groups(['read:Sessioncaisse'])]
    private string $statut = 'OUVERTE'; // cf. App\Domain\Enum\SessioncaisseStatut

    /**
     * Porte l'identifiant de l'agent TANT QUE la session est ouverte, NULL ensuite. Sert
     * exclusivement l'index unique (cf. l'en-tête de la classe) : ne jamais l'exposer, ni s'en
     * servir pour retrouver l'agent — 'agent' est là pour ça.
     */
    #[ORM\Column(nullable: true)]
    private ?int $agentsessionouverte = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?User
    {
        return $this->agent;
    }

    public function setAgent(?User $agent): static
    {
        $this->agent = $agent;

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

    public function getDatedebut(): ?\DateTimeImmutable
    {
        return $this->datedebut;
    }

    public function setDatedebut(\DateTimeImmutable $datedebut): static
    {
        $this->datedebut = $datedebut;

        return $this;
    }

    public function getDatefin(): ?\DateTimeImmutable
    {
        return $this->datefin;
    }

    public function setDatefin(?\DateTimeImmutable $datefin): static
    {
        $this->datefin = $datefin;

        return $this;
    }

    public function getFondsouverture(): int
    {
        return $this->fondsouverture;
    }

    public function setFondsouverture(int $fondsouverture): static
    {
        $this->fondsouverture = $fondsouverture;

        return $this;
    }

    public function isOuvertureautomatique(): bool
    {
        return $this->ouvertureautomatique;
    }

    public function setOuvertureautomatique(bool $ouvertureautomatique): static
    {
        $this->ouvertureautomatique = $ouvertureautomatique;

        return $this;
    }

    public function getTotalbillets(): ?int
    {
        return $this->totalbillets;
    }

    public function setTotalbillets(?int $totalbillets): static
    {
        $this->totalbillets = $totalbillets;

        return $this;
    }

    public function getTotalbagages(): ?int
    {
        return $this->totalbagages;
    }

    public function setTotalbagages(?int $totalbagages): static
    {
        $this->totalbagages = $totalbagages;

        return $this;
    }

    public function getTotalcourriers(): ?int
    {
        return $this->totalcourriers;
    }

    public function setTotalcourriers(?int $totalcourriers): static
    {
        $this->totalcourriers = $totalcourriers;

        return $this;
    }

    public function getTotalfraissuivi(): ?int
    {
        return $this->totalfraissuivi;
    }

    public function setTotalfraissuivi(?int $totalfraissuivi): static
    {
        $this->totalfraissuivi = $totalfraissuivi;

        return $this;
    }

    public function getTotalreservations(): ?int
    {
        return $this->totalreservations;
    }

    public function setTotalreservations(?int $totalreservations): static
    {
        $this->totalreservations = $totalreservations;

        return $this;
    }

    public function getTotalpenalites(): ?int
    {
        return $this->totalpenalites;
    }

    public function setTotalpenalites(?int $totalpenalites): static
    {
        $this->totalpenalites = $totalpenalites;

        return $this;
    }

    public function getTotalcomplements(): ?int
    {
        return $this->totalcomplements;
    }

    public function setTotalcomplements(?int $totalcomplements): static
    {
        $this->totalcomplements = $totalcomplements;

        return $this;
    }

    public function getTotalremboursements(): ?int
    {
        return $this->totalremboursements;
    }

    public function setTotalremboursements(?int $totalremboursements): static
    {
        $this->totalremboursements = $totalremboursements;

        return $this;
    }

    public function getMontanttheorique(): ?int
    {
        return $this->montanttheorique;
    }

    public function setMontanttheorique(?int $montanttheorique): static
    {
        $this->montanttheorique = $montanttheorique;

        return $this;
    }

    public function getMontantcompte(): ?int
    {
        return $this->montantcompte;
    }

    public function setMontantcompte(?int $montantcompte): static
    {
        $this->montantcompte = $montantcompte;

        return $this;
    }

    public function getEcart(): ?int
    {
        return $this->ecart;
    }

    public function setEcart(?int $ecart): static
    {
        $this->ecart = $ecart;

        return $this;
    }

    public function getMotifecart(): ?string
    {
        return $this->motifecart;
    }

    public function setMotifecart(?string $motifecart): static
    {
        $this->motifecart = $motifecart;

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

    public function getAgentsessionouverte(): ?int
    {
        return $this->agentsessionouverte;
    }

    public function setAgentsessionouverte(?int $agentsessionouverte): static
    {
        $this->agentsessionouverte = $agentsessionouverte;

        return $this;
    }

    /**
     * UNE CAISSE NE VA JAMAIS À LA CORBEILLE — refus inconditionnel, et non une liste de conditions.
     *
     * Ouverte, elle porte des encaissements qui deviendraient introuvables ; clôturée, elle est la
     * PIÈCE que l'agent a signée. Dans les deux cas, l'effacer reviendrait à retirer d'un contrôle
     * la trace même qu'il contrôle — c'est le geste que ce module existe pour rendre impossible.
     *
     * Ceinture ET bretelles : 'Sessioncaisse' est aussi exclue de 'CorbeilleRegistry', donc aucune
     * opération de mise en corbeille ne lui est exposée. Ce garde attrape ce qui viendrait par un
     * autre chemin — un 'SoftDeleteProcessor' branché un jour par distraction sur une nouvelle
     * opération, par exemple.
     */
    public function getSoftDeleteBlockers(): array
    {
        return ['Une session de caisse ne se supprime pas : c\'est la pièce qui justifie un écart.'];
    }
}
