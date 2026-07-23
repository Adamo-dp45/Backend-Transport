<?php

namespace App\Entity;

use App\Repository\ArretRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ArretRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_arret_ligne_gare', columns: ['ligne_id', 'gare_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_arret_ligne_ordre', columns: ['ligne_id', 'ordre'])]
/*
    - Entité « config » enfant de 'Ligne' : pas de 'EntityBase' ni de 'EntrepriseOwnedInterface'
      (sinon 'EntrepriseScopeExtension' exigerait une colonne 'deleted_at'). Gérée via 'Ligne'
      (cascade persist, orphanRemoval, hard delete à la reconstruction) donc pas de soft-delete
      et les 'UNIQUE' SQL sont sûrs.
*/
class Arret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'arrets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?Gare $gare = null;

    #[ORM\Column]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?int $ordre = null; // 0 = origine, N = terminus : Abidjan=0, Yamoussoukro=1, Bouaké=2, Korhogo=3

    /*
        Minutes de trajet du TRONÇON qui MÈNE à cet arrêt (depuis l'arrêt précédent). NULL à l'origine
        (aucun tronçon avant elle). L'heure de passage prévue du car à un arrêt est la SOMME des tronçons
        de l'origine effective jusqu'à lui (cf. ReservationEcheanceService::heurePassage), ajoutée à
        'voyage.datedepartprevue'.

        Saisir par tronçon (« Abidjan→Bouaké = 240, Bouaké→Korhogo = 180 ») est plus naturel que saisir
        un cumul, et donne aussi le temps de trajet théorique de chaque segment. Nullable = ligne pas
        encore renseignée (tout-ou-rien) : on retombe alors sur le départ du voyage (ancien comportement).
    */
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Ligne', 'read:Voyage'])]
    private ?int $dureeTronconMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getDureeTronconMinutes(): ?int
    {
        return $this->dureeTronconMinutes;
    }

    public function setDureeTronconMinutes(?int $dureeTronconMinutes): static
    {
        $this->dureeTronconMinutes = $dureeTronconMinutes;

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
