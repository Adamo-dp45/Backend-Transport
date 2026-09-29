<?php

namespace App\Entity;

use App\Repository\DetailmaindoeuvreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une prestation de MAIN D'ŒUVRE EXTERNE sur un dépannage : le soudeur du village, le garage qu'on
 * appelle au bord de la route, le grutier qui remorque.
 *
 * POURQUOI SUR LE DÉPANNAGE ET NON DANS `Depense`. C'est bien une charge — de l'argent qui sort —,
 * mais c'est le COÛT DE L'INTERVENTION. La verser dans `Depense` scinderait le coût d'une même panne
 * en deux postes : les pièces sous « dépannages », la main d'œuvre sous « dépenses ». Le poste
 * « dépannages » du bénéfice sous-estimerait alors ce qu'une panne coûte, et la fiche d'un car réparé
 * par des mains externes le ferait passer pour bon marché — les deux chiffres restant INDIVIDUELLEMENT
 * corrects, donc personne ne s'en apercevrait. Les trois postes du bénéfice restent DISJOINTS
 * (cf. le README, module Dépense) : rien ici n'est jamais compté deux fois.
 *
 * L'asymétrie avec le personnel INTERNE (`Detailpersonnel`, qui ne porte aucun coût) est voulue : un
 * mécanicien de la maison est un SALAIRE, déjà compté en `Depense` au siège. L'externe est une facture
 * ponctuelle attachée à l'intervention. Charge fixe contre sous-traitance.
 *
 * UNE LIGNE PAR INTERVENANT plutôt qu'un montant global : un gros dépannage en mobilise deux ou
 * trois, payés séparément, et « 45 000 de main d'œuvre » sans dire à qui ne se relit pas dans six
 * mois. Patron de {@see Detaildepannage} — pas d'`EntityBase` : un détail vit et meurt avec son
 * dépannage, il n'a pas de corbeille propre.
 */
#[ORM\Entity(repositoryClass: DetailmaindoeuvreRepository::class)]
#[ORM\Index(name: 'idx_maindoeuvre_depannage', columns: ['depannage_id'])] /*
    - Déclaré ICI et pas seulement dans la migration : la base de TEST est bâtie par
      'doctrine:schema:update', qui ne connaît que les mappings
*/
class Detailmaindoeuvre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Depannage'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'detailmaindoeuvres', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Depannage $depannage = null;

    /**
     * QUI a travaillé, en clair : « Garage Kouassi », « soudeur du village », « remorquage Traoré ».
     *
     * Texte libre et non un `Fournisseur` : cette entité exige contact, adresse et pays, et imposer
     * une fiche pour payer un soudeur au bord de la route produirait des fiches bidon — même raison
     * qui a fait garder `beneficiaire` en texte libre sur `Depense`.
     */
    #[ORM\Column(length: 255)]
    #[Groups(['read:Depannage'])]
    #[Assert\NotBlank(message: 'L\'intervenant est obligatoire')]
    #[Assert\Length(max: 255)]
    private ?string $intervenant = null;

    /** Ce qui a été fait, si le nom de l'intervenant ne suffit pas à le dire. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Depannage'])]
    private ?string $prestation = null;

    /**
     * BIGINT comme `Depannage::$couttotal` et `Depense::$montant` : un INT plafonne à ~2,1 milliards
     * de FCFA, hors d'atteinte pour un soudeur mais pas pour une remise en état complète.
     */
    #[ORM\Column(type: 'bigint')]
    #[Groups(['read:Depannage'])]
    #[Assert\NotNull(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro')] /*
        - Un montant négatif BAISSERAIT le coût du dépannage : c'est une porte d'abus, pas une
          commodité de saisie. Une correction se fait en modifiant la ligne
    */
    private ?int $montant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepannage(): ?Depannage
    {
        return $this->depannage;
    }

    public function setDepannage(?Depannage $depannage): static
    {
        $this->depannage = $depannage;

        return $this;
    }

    public function getIntervenant(): ?string
    {
        return $this->intervenant;
    }

    public function setIntervenant(string $intervenant): static
    {
        $this->intervenant = $intervenant;

        return $this;
    }

    public function getPrestation(): ?string
    {
        return $this->prestation;
    }

    public function setPrestation(?string $prestation): static
    {
        $this->prestation = $prestation;

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
}
