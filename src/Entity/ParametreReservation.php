<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Dto\ParametreReservationInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\ParametreReservationRepository;
use App\State\MeParametreReservationProcessor;
use App\State\MeParametreReservationProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paramètres de RÉSERVATION, UN par entreprise.
 *
 * Pour l'instant un seul réglage : le délai (en minutes) AVANT le départ au-delà duquel une place
 * n'est plus tenue / réservable (0 = jusqu'au départ). Singleton par entreprise, auto-créé avec les
 * valeurs par défaut (cf. ReservationConfigService). Accès via /me/parametre-reservation
 * (GET ouvert aux agents pour l'affichage, PATCH réservé admin).
 */
#[ORM\Entity(repositoryClass: ParametreReservationRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:ParametreReservation', 'read:Base']],
    operations: [
        new Get(
            uriTemplate: '/me/parametre-reservation',
            security: "is_granted('ROLE_USER')",
            provider: MeParametreReservationProvider::class,
            openapi: new Operation(
                summary: 'Voir les paramètres de réservation de mon entreprise',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            uriTemplate: '/me/parametre-reservation',
            security: "is_granted('ROLE_ADMIN')",
            input: ParametreReservationInput::class,
            processor: MeParametreReservationProcessor::class,
            denormalizationContext: ['groups' => ['write:ParametreReservationInput']],
            openapi: new Operation(
                summary: 'Configurer les paramètres de réservation',
                security: [['bearerAuth' => []]]
            )
        )
    ]
)]
class ParametreReservation extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:ParametreReservation'])]
    private ?int $id = null;

    /*
        DEUX délais DISTINCTS — ils répondent à deux questions différentes, les confondre revenait à
        traiter en no-show un client qui avait payé et se présentait 1h30 avant le départ.
    */

    /**
     * Délai (minutes) AVANT LE DÉPART au-delà duquel une réservation payée n'est plus honorée : le
     * client doit s'être présenté au guichet avant. C'est aussi la limite de réservation d'un départ.
     * 0 = jusqu'au départ.
     */
    #[ORM\Column(options: ['default' => 15])]
    #[Groups(['read:ParametreReservation'])]
    private int $delaiPresentationMinutes = 15;

    /**
     * Délai (minutes) À PARTIR DE LA CRÉATION laissé pour payer une réservation en attente. Ancré sur
     * la création (et non sur le départ) : réserver un départ dans trois semaines ne doit pas donner
     * trois semaines pour payer. Toujours borné par le délai de présentation ci-dessus.
     */
    #[ORM\Column(options: ['default' => 30])]
    #[Groups(['read:ParametreReservation'])]
    private int $delaiPaiementMinutes = 30;

    /**
     * Politique de pénalité appliquée à la RÉGULARISATION d'une réservation payée en no-show :
     * AUCUNE (0), FIXE (montant en FCFA) ou POURCENTAGE (% du prix du billet).
     */
    #[ORM\Column(length: 20, options: ['default' => 'AUCUNE'])]
    #[Groups(['read:ParametreReservation'])]
    private string $penaliteType = 'AUCUNE';

    /** Valeur de la pénalité : montant FCFA si FIXE, pourcentage si POURCENTAGE (ignoré si AUCUNE). */
    #[ORM\Column(options: ['default' => 0])]
    #[Groups(['read:ParametreReservation'])]
    private int $penaliteValeur = 0;

    /**
     * Fenêtre (en jours) après la date de départ prévue pendant laquelle une réservation payée en
     * no-show reste régularisable ; au-delà, elle est définitivement perdue (EXPIREE).
     */
    #[ORM\Column(options: ['default' => 7])]
    #[Groups(['read:ParametreReservation'])]
    private int $fenetreRegularisationJours = 7;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDelaiPresentationMinutes(): int
    {
        return $this->delaiPresentationMinutes;
    }

    public function setDelaiPresentationMinutes(int $delaiPresentationMinutes): static
    {
        $this->delaiPresentationMinutes = $delaiPresentationMinutes;

        return $this;
    }

    public function getDelaiPaiementMinutes(): int
    {
        return $this->delaiPaiementMinutes;
    }

    public function setDelaiPaiementMinutes(int $delaiPaiementMinutes): static
    {
        $this->delaiPaiementMinutes = $delaiPaiementMinutes;

        return $this;
    }

    public function getPenaliteType(): string
    {
        return $this->penaliteType;
    }

    public function setPenaliteType(string $penaliteType): static
    {
        $this->penaliteType = $penaliteType;

        return $this;
    }

    public function getPenaliteValeur(): int
    {
        return $this->penaliteValeur;
    }

    public function setPenaliteValeur(int $penaliteValeur): static
    {
        $this->penaliteValeur = $penaliteValeur;

        return $this;
    }

    public function getFenetreRegularisationJours(): int
    {
        return $this->fenetreRegularisationJours;
    }

    public function setFenetreRegularisationJours(int $fenetreRegularisationJours): static
    {
        $this->fenetreRegularisationJours = $fenetreRegularisationJours;

        return $this;
    }

    /** Pénalité (FCFA) pour un prix de billet donné, selon la politique de l'entreprise. */
    public function calculerPenalite(int $prix): int
    {
        return match ($this->penaliteType) {
            'FIXE' => max(0, $this->penaliteValeur),
            'POURCENTAGE' => (int) round($prix * max(0, $this->penaliteValeur) / 100),
            default => 0,
        };
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
