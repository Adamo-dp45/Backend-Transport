<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Dto\ProgrammeFideliteInput;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\ProgrammeFideliteRepository;
use App\State\MeProgrammeFideliteProcessor;
use App\State\MeProgrammeFideliteProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Paramètres du programme de fidélité « carte à tampons », UN par entreprise.
 *
 * Modèle : tous les 'seuil' voyages payés (billets VALIDE émis depuis l'adhésion, hors récompenses),
 * le client gagne une récompense = un billet à 'recompensePourcentage' % de remise (100 = offert).
 *
 * Singleton par entreprise : résolu/auto-créé avec des valeurs par défaut via FideliteService.
 * Accès via /me/programme-fidelite (GET ouvert aux agents pour l'affichage, PATCH réservé admin).
 */
#[ORM\Entity(repositoryClass: ProgrammeFideliteRepository::class)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:ProgrammeFidelite', 'read:Base'], 'skip_null_values' => false],
    operations: [
        new Get(
            uriTemplate: '/me/programme-fidelite',
            security: "is_granted('ROLE_USER')",
            provider: MeProgrammeFideliteProvider::class,
            openapi: new Operation(
                summary: 'Voir le programme de fidélité de mon entreprise',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            uriTemplate: '/me/programme-fidelite',
            security: "is_granted('ROLE_ADMIN')",
            input: ProgrammeFideliteInput::class,
            processor: MeProgrammeFideliteProcessor::class,
            denormalizationContext: ['groups' => ['write:ProgrammeFideliteInput']],
            openapi: new Operation(
                summary: 'Configurer le programme de fidélité',
                security: [['bearerAuth' => []]]
            )
        )
    ]
)]
class ProgrammeFidelite extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:ProgrammeFidelite'])]
    private ?int $id = null;

    /** Nombre de voyages payés à atteindre pour gagner une récompense. */
    #[ORM\Column(options: ['default' => 10])]
    #[Groups(['read:ProgrammeFidelite'])]
    private int $seuil = 10;

    /** Remise (%) appliquée au billet-récompense (100 = voyage offert). */
    #[ORM\Column(options: ['default' => 100])]
    #[Groups(['read:ProgrammeFidelite'])]
    private int $recompensePourcentage = 100;

    /** Programme actif ou suspendu. */
    #[ORM\Column(options: ['default' => true])]
    #[Groups(['read:ProgrammeFidelite'])]
    private bool $actif = true;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeuil(): int
    {
        return $this->seuil;
    }

    public function setSeuil(int $seuil): static
    {
        $this->seuil = $seuil;

        return $this;
    }

    public function getRecompensePourcentage(): int
    {
        return $this->recompensePourcentage;
    }

    public function setRecompensePourcentage(int $recompensePourcentage): static
    {
        $this->recompensePourcentage = $recompensePourcentage;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

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
