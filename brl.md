### Brl

- **Command**
    > php -S localhost:8000 -t public | symfony serve
    > php bin/console cache:clear
    > php bin/console debug:router
    > php bin/console make:controller
    > php bin/console make:entity
    > php bin/console make:voter
    > php bin/console make:listener
    > php bin/console make:subscriber
    > php bin/console make:fixtures
    > php bin/console doctrine:fixtures:load
    > php bin/console make:migration
    > php bin/console doctrine:migrations:migrate
    > php bin/console doctrine:schema:update --force : `--env=test` pour les tests
        > php bin/console doctrine:schema:update --dump-sql
    > php bin/console doctrine:fixtures:load : `--env=test` pour les tests
    > php bin/console translation:extract --dump-messages fr
    > php bin/console translation:extract --force fr --format=yaml
    > php bin/console make:test
    > php bin/console make:state-processor
    > php bin/console make:state-provider

- 

- 
J'ai une réflexion concernant le panneau d'affichage des sièges dans le `TicketForm` (ou tout autre écran de sélection des sièges).

Une des entreprises qui souhaite utiliser l'application m'a indiqué que la disposition de ses sièges est différente de celle que nous affichons actuellement. De mon côté, je souhaiterais conserver la disposition actuelle comme disposition par défaut.

Je me demande donc s'il ne serait pas pertinent de rendre la disposition des sièges configurable côté frontend, afin que chaque entreprise puisse choisir le modèle qui correspond à ses véhicules.

Par exemple, cette entreprise utilise la disposition suivante :

```text
3  4  5   |   2  1
8  9 10   |   7  6
13 14 15  |  12 11
...
```

Les six dernières places sont ensuite positionnées à l'arrière du véhicule.

L'objectif serait d'avoir un système suffisamment flexible pour afficher différentes dispositions de sièges sans modifier la logique métier. Qu'en penses-tu ? Est-ce que cette approche te semble pertinente ou vois-tu une meilleure solution ?









Les cas de survente..

quest ce quon a omis

pour la partie suppression : 
    Je me qu'on devrait enlever l'action supprimer sur certaines resources(du genre tout ce qui conçerne l'argent) pour éviter de créer une confusion !! vu qu'un agent pourrait émettre un ticket ou enregistrer un courrier et la supprimer avec une intention de vole :: bagage courrier

D. Renforcements recommandés (par ordre d'impact) :
    Remise : motif/bénéficiaire obligatoire au-delà d'un seuil + plafond de remise par agent.
    Rapport « recette annulée/supprimée par agent » (réconcilier annulations vs encaissements — repère les agents à fort taux d'annulation).
    montantforce bagage : rapport des montants forcés / écarts par agent.









On vas revenir sur la réservation, explique moi le principe ou fonctionnement ou cycle de vie du système de réservation.

Aussi à quelle moment une réservation en attente et une réservation payé expire ?

Places ocuupés = réservations en attente non expiré + réservation payé + tickets .. 

Le cron sur `app:reservations:expirer` pour la réservation vu que c'est lui qui matérialise le passage en à régulariser, sans planification, il faut le lancer manuellement.

Le cron d'expiration ne touche que les réservations EN_ATTENTE (non payées) — il libère la place si le client ne paie pas à temps

Je voulais dire qu'une réservation payée ne se fait pas « annuler pour non-paiement » (puisqu'elle est payée). La correction = découpler le paiement de l'attribution du siège. Payer devient possible sans car → la réservation passe PAYÉE et génère un bon de réservation (sans siège). Le billet avec siège est émis ensuite (à la gare, ou dès qu'un car est affecté). C'est exactement ton intuition « le ticket de réservation ≠ le ticket de la gare ». C'est un prérequis au mobile, donc à traiter avant.

Aussi :
- Vu qu'on peut vendre une place réservé, comment on fais dans le cas ou on vend toutes les places et que le client qui a réservé arrive ? est ce qu'on peut emettre le bon sur un autre même voyage





En utilisant les bonnes pratiques



Voici comment installer Flutter sur Windows, étape par étape :

## 1. Prérequis
- Windows 10 ou plus récent (64 bits), au moins ~2 Go d'espace disque libre
- **Git for Windows** installé (nécessaire pour les mises à jour de Flutter) — [git-scm.com](https://git-scm.com/download/win)
- Windows PowerShell 5.0+ (normalement déjà présent)

## 2. Télécharger le SDK Flutter
- Va sur [docs.flutter.dev/install](https://docs.flutter.dev/install)
- Télécharge l'archive `flutter_windows_<version>-stable.zip`

## 3. Extraire le SDK
- Extrais le zip dans un dossier **sans espaces ni caractères spéciaux**, et **sans privilèges élevés requis** (donc pas dans `C:\Program Files`)
- Exemple recommandé : `C:\src\flutter` ou `C:\Users\<toi>\develop\flutter`

## 4. Ajouter Flutter au PATH
- Tape "env" dans la barre de recherche Windows → "Modifier les variables d'environnement système"
- Sous "Variables utilisateur", sélectionne `Path` → "Modifier" → "Nouveau"
- Ajoute le chemin vers le dossier `bin` de Flutter, par exemple : `C:\src\flutter\bin`
- Valide avec OK partout

## 5. Vérifier l'installation
Ouvre un **nouveau** terminal (PowerShell ou CMD) et lance :
```
flutter doctor
```
Cette commande vérifie tout ton environnement et te dit précisément ce qu'il manque (Android SDK, licences, etc.).

## 6. Compléter selon tes besoins
- **Pour Android** : installe Android Studio, puis le SDK Android via le SDK Manager. Si `flutter doctor` signale un souci de licence, lance `flutter doctor --android-licenses`.
- **Pour l'éditeur** : installe VS Code, puis ajoute les extensions **Flutter** et **Dart** (recherche-les dans l'onglet Extensions).

## 7. Créer ton premier projet
```
flutter create mon_projet
cd mon_projet
flutter run
```

Vu que tu bosses déjà sur ton projet Expo/React Native, tu connais probablement déjà les réflexes CLI — `flutter doctor` est un peu l'équivalent de `expo doctor`. Dis-moi si tu bloques sur une étape en particulier (souvent le PATH ou les licences Android sont les points chauds).








Rien d'incorrect à corriger. Une amélioration possible (cosmétique) : la courbe « recette par jour » de Recettes pourrait être empilée par type — dis-moi si ça t'intéresse.

#4 — Détection du conflit de siège (billet aval « déclassé »)
Faisable et bien cadré. Plan : champ Ticket.enConflit (+ migration) ; à la vente/priorité-amont, SiegeStateProvider/processor détecte qu'un billet aval partage un tronçon avec le nouveau billet amont sur le même siège → on le marque (comme le marqueur « revendu ») ; affichage d'un badge « siège à réattribuer » sur le billet (table + fiche + grille sièges) et, en option, une alerte à la gare aval. Je dois d'abord relire CapaciteService + la logique « revendu » pour être exact. => Priorité amont : marquer le billet aval évincé (déjà flaggé).

Conflit de siège (#4)	non géré	🟠 Gap opérationnel réel
Perf des stats	N+1 évités, mais endpoints lourds	🟡 Prévoir index/cache à volume élevé
Infra (backups, monito, déploiement)	non abordé ici	🟡 À cadrer côté ops




Pour l'affichage des recettes, je propose de bien distinguer les différentes sources de revenus.

Nous pourrions afficher :

* les recettes des gares
* les recettes des commerciaux
* la recette totale
* la recette totale hors courriers

Cette dernière est importante, car une des entreprises qui souhaite utiliser l'application m'a indiqué qu'elle ne souhaite pas intégrer les revenus issus des courriers dans son chiffre d'affaires, alors que, dans notre logique actuelle, ils sont inclus. Il serait donc pertinent de rendre ce comportement configurable afin de s'adapter aux besoins de chaque entreprise




Base temporelle de la ligne : le cargo est filtré sur sa date de saisie (createdAt), les billets sur la date de départ du voyage. Écart mineur sur des périodes courtes, sans impact sur des périodes normales.

Bref : je garde la feature et j'ajoute le fetch-join. (À noter : la vraie lourdeur de cette liste, c'est plutôt courriers/bagages/detailpersonnels hydratés en entier juste pour un .length — sujet à part si tu veux l'optimiser un jour.)

Voyage « réservation » : il n'y a aucun type distinct — un voyage est réservable implicitement (futur + capacité). Je n'ai rien ajouté ; dis-moi si tu veux un flag explicite un jour.


aussi sache que je privilégie les provider aux controllers comme tu l'a fais avec (BilletterieStatsController, CommercialStatsController, CourrierStatsDetailsController, DepartStatsController, FlotteStatsDetailsController),  (BilleterieStatsProvider, CourrierStatsProvider, FlotteActiviteStatsProvider) ce qui provoqué 2 appel dans le controller du frontend :: lequel est le mieux les controllers ou provider vu que j'utilise API Platform
- Réecris les modules met à jour la page d'aide
- Fais moi un système d'autocomplétion dans un formulaire de recherche sur plusieurs ressources dans une application Symfony
- - 
- Dépenses : 2 types (Dépense générale et gare)
    Objetdepense -> libelle          Objetdepensegare..
    Depense                          Depensegare..
        objetdepense -> vers Objetdepense
        date
        montant
        detail
- On vas ajouter un système de mode maintenance configurable par le super admin
- Géolocalisation pour le suivi des cars en temps réel
- Guide utilisateur via Driver.js ou Intro.js avec persisantce de l'état sur lequel l'utilisateur est et peut recommencer

## Comparaison Driver.js vs Intro.js

**Driver.js**
- Plus moderne, léger (~5kb gzip)
- Highlight visuel élégant — met en surbrillance l'élément ciblé avec un overlay
- API simple et flexible
- Pas de dépendances
- Meilleur pour des guides contextuels par page/module
- Gratuit et open source

**Intro.js**
- Plus ancien, plus lourd
- Style "tooltip numéroté" classique
- Nécessite une licence pour usage commercial
- Plus de configuration nécessaire

**Recommandation : Driver.js** — plus adapté à ton cas car tu as des guides par module (contextuels par page), il est plus léger, gratuit et son rendu est plus moderne.




Parfait 🔥 on va construire un **système d’alertes intelligent** pour ton app — et là on passe en mode **produit pro**.

---

# 🚨 🎯 OBJECTIF DU MODULE ALERTES

Notifier automatiquement :

* 📦 stock faible
* 🚍 voyage complet / presque complet
* 🛠️ anomalies (optionnel plus tard)

---

# 🧠 🧩 1. CONCEPTION GLOBALE

On crée une entité centrale :

## 👉 `Alerte`

Elle servira pour **TOUT le système**

---

# 🧱 2. ENTITY `Alerte`

```php
#[ORM\Entity]
class Alerte extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Column(length: 50)]
    private string $type; // STOCK_FAIBLE, VOYAGE_COMPLET...

    #[ORM\Column(length: 255)]
    private string $message;

    #[ORM\Column(nullable: true)]
    private ?int $referenceId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private int $identreprise;
}
```

---

# 🧠 TYPES D’ALERTES

```php
class AlerteType
{
    public const STOCK_FAIBLE = 'STOCK_FAIBLE';
    public const VOYAGE_COMPLET = 'VOYAGE_COMPLET';
    public const VOYAGE_BIENTOT_COMPLET = 'VOYAGE_BIENTOT_COMPLET';
}
```

---

# 🧠 🧩 3. SERVICE CENTRAL (TRÈS IMPORTANT)

👉 pour éviter du code partout

```php
class AlerteService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function create(
        string $type,
        string $message,
        int $entrepriseId,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): void {
        $alerte = new Alerte();
        $alerte
            ->setType($type)
            ->setMessage($message)
            ->setIdentreprise($entrepriseId)
            ->setReferenceId($referenceId)
            ->setReferenceType($referenceType);

        $this->em->persist($alerte);
    }
}
```

---

# 📦 4. ALERTES STOCK FAIBLE

---

## 🎯 RÈGLE

```txt
stock <= seuil
```

👉 ajoute dans `Piece` :

```php
private int $seuilAlerte = 5;
```

---

## 🧠 DANS TON `StockmouvementService`

👉 après chaque mouvement :

```php
if ($piece->getStock() <= $piece->getSeuilAlerte()) {

    $this->alerteService->create(
        AlerteType::STOCK_FAIBLE,
        "Stock faible pour {$piece->getLibelle()}",
        $entrepriseId,
        $piece->getId(),
        'PIECE'
    );
}
```

---

# 🚍 5. ALERTES VOYAGE

## 🎯 CAS 1 : VOYAGE COMPLET
## 🎯 CAS 2 : PRESQUE COMPLET



---

# 🧱 6. API PLATFORM (ENDPOINTS)

```php
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    operations: [

        new GetCollection(
            security: "is_granted('VOIR', 'Alerte')"
        ),

        new Get(),

        new Patch(
            security: "is_granted('MODIFIER', object)"
        )
    ]
)]
```

---

# 📊 7. DASHBOARD "ALERTES NON LUES"

👉 endpoint :

```http
GET /alertes?isRead=false
```

---

## 🔥 BONUS FILTRE

```php
#[ApiFilter(SearchFilter::class, properties: [
    'isRead' => 'exact',
    'type' => 'exact'
])]
```

---

# 🧱 8. MARQUER COMME LU

👉 PATCH :

```json
{
  "isRead": true
}
```

# AMÉLIORATIONS PRO

## 🔥 Notification temps réel

* WebSocket
* ou polling `/alertes`

## 🔥 Notification WhatsApp / Email










### Opus

- Pour la tarification on.. matrice complète O-D, tout couple (montée, descente) le long de la ligne a son tarif
- **Places = capacité par segment** : un siège se libère à la descente et est revendable pour les tronçons suivants.

### 9.4 Cœur du système : disponibilité des sièges par tronçon

On modélise chaque ticket comme un **intervalle semi-ouvert** `[ordre(montée), ordre(descente))` sur le siège.

```
Arrêts :   Abidjan(0)   Yamoussoukro(1)   Bouaké(2)   Korhogo(3)
Siège 12 :  ●───────────────────────────● B vendu              [0,2)  Abidjan→Bouaké
                                          ●───────────────────● même siège revendable [2,3) Bouaké→Korhogo
```

Avec des **sièges nommés**, la capacité par tronçon est garantie automatiquement : on ne peut pas affecter un siège dont l'intervalle chevauche un ticket existant ⇒ pas besoin d'un compteur global.


- - 
## 2. `Tarifbagage` — renommer les champs poids → valeur

```php
// poidsmin → valeurmin
#[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
#[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage', 'write:Tarifbagage:update'])]
private ?string $valeurmin = null;

// poidsmax → valeurmax
#[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
#[Groups(['read:Tarifbagage', 'read:Bagage', 'write:Tarifbagage', 'write:Tarifbagage:update'])]
private ?string $valeurmax = null;
```

---

## 3. `TarifbagageRepository` — méthode basée sur la valeur

```php
public function findTarifForValeur(int $valeur, int $identreprise): ?Tarifbagage
{
    return $this->createQueryBuilder('t')
        ->where('t.identreprise = :identreprise')
        ->andWhere('t.deletedAt IS NULL')
        ->andWhere('t.valeurmin <= :valeur')
        ->andWhere('t.valeurmax IS NULL OR t.valeurmax >= :valeur')
        ->setParameter('identreprise', $identreprise)
        ->setParameter('valeur', $valeur)
        ->orderBy('t.valeurmin', 'ASC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
```

Les méthodes `findTrancheIllimitee` et `findChevauchement` utilisent maintenant `valeurmin/valeurmax` — renommer les paramètres en conséquence.

---

## 4. `BagageInput` — modifications

```php
#[Assert\NotNull]
#[Groups(['write:BagageInput'])]
public int $voyage;

#[Assert\NotBlank]
#[Groups(['write:BagageInput'])]
public string $nomclient;

#[Assert\NotBlank]
#[Groups(['write:BagageInput'])]
public string $contactclient;

#[Assert\NotBlank]
#[Groups(['write:BagageInput'])]
public string $nature;

#[Assert\NotBlank]
#[Assert\Choice(choices: ['LEGER', 'LOURD', 'VOLUMINEUX', 'FRAGILE'])]
#[Groups(['write:BagageInput'])]
public string $type;

// poids nullable
#[Groups(['write:BagageInput'])]
public ?float $poids = null;

// valeur déclarée — obligatoire pour le calcul tarifaire
#[Assert\NotNull]
#[Assert\Positive]
#[Groups(['write:BagageInput'])]
public int $valeur;

// montant forcé optionnel
#[Assert\PositiveOrZero]
#[Groups(['write:BagageInput'])]
public ?int $montant = null;

// codeticket lié optionnel
#[Groups(['write:BagageInput'])]
public ?string $codeticket = null;
```

---

## 5. `BagageProcessor` — calcul basé sur la valeur

```php
private function resoudreMontant(int $valeur, ?int $montantFourni, int $identreprise): array
{
    $tarifbagage = $this->tarifbagageRepository->findTarifForValeur($valeur, $identreprise);

    if ($tarifbagage !== null) {
        $montantCalcule = $tarifbagage->getMontant();
        if ($montantFourni !== null && $montantFourni !== $montantCalcule) {
            return [$montantFourni, $tarifbagage, true];
        }
        return [$montantCalcule, $tarifbagage, false];
    }

    if ($montantFourni === null) {
        throw new BadRequestHttpException(
            'Aucun tarif trouvé pour une valeur de ' . $valeur . ' FCFA. Veuillez saisir le montant manuellement.'
        );
    }

    return [$montantFourni, null, true];
}
```

Dans `handlePost` et `handlePatch`, remplacer `$data->poids` par `$data->valeur` :

```php
[$montant, $tarifbagage, $montantforce] = $this->resoudreMontant(
    $data->valeur,      // ← valeur au lieu de poids
    $data->montant,
    $identreprise
);

$bagage
    ->setPoids($data->poids !== null ? (string) $data->poids : null)  // nullable
    ->setValeur($data->valeur)
    // ...
```





## Tarifligne

# Revenir au modèle `TarifLigne` — code complet

Guide **prêt à coller** pour repasser de la grille tarifaire **globale** (`Tarif` gare→gare) au modèle
**`TarifLigne`** (un tarif par couple de gares **et par ligne**).

> Rappel du compromis : `TarifLigne` réintroduit la duplication du prix d'un segment partagé par plusieurs
> lignes. À ne faire que si tu veux réellement des prix **différents selon la ligne**.

---

# BACKEND (`BK-Transport`)

## 1. `src/Entity/TarifLigne.php` (NOUVEAU fichier)

```php
<?php

namespace App\Entity;

use App\Repository\TarifLigneRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TarifLigneRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_tarifligne', columns: ['ligne_id', 'garedepart_id', 'garearrivee_id'])]
class TarifLigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Ligne', 'read:Ligne:item'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tariflignes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Ligne $ligne = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ligne', 'read:Ligne:item'])]
    private ?Gare $garedepart = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['read:Ligne', 'read:Ligne:item'])]
    private ?Gare $garearrivee = null;

    #[ORM\Column]
    #[Groups(['read:Ligne', 'read:Ligne:item'])]
    #[Assert\Positive(message: 'Le montant doit être strictement positif')]
    private ?int $montant = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

    public function getId(): ?int { return $this->id; }

    public function getLigne(): ?Ligne { return $this->ligne; }
    public function setLigne(?Ligne $ligne): static { $this->ligne = $ligne; return $this; }

    public function getGaredepart(): ?Gare { return $this->garedepart; }
    public function setGaredepart(?Gare $garedepart): static { $this->garedepart = $garedepart; return $this; }

    public function getGarearrivee(): ?Gare { return $this->garearrivee; }
    public function setGarearrivee(?Gare $garearrivee): static { $this->garearrivee = $garearrivee; return $this; }

    public function getMontant(): ?int { return $this->montant; }
    public function setMontant(int $montant): static { $this->montant = $montant; return $this; }

    public function getIdentreprise(): ?int { return $this->identreprise; }
    public function setIdentreprise(?int $identreprise): static { $this->identreprise = $identreprise; return $this; }
}
```

> `TarifLigne` est volontairement une entité **simple** (pas de `EntityBase`/soft-delete) : c'est de la
> config, recréée à chaque édition de ligne (orphanRemoval). Elle n'a **pas** d'`ApiResource` : elle est
> gérée via `LigneInput`/`LigneProcessor` et lue via `read:Ligne`.

## 2. `src/Repository/TarifLigneRepository.php` (NOUVEAU fichier)

```php
<?php

namespace App\Repository;

use App\Entity\TarifLigne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TarifLigne>
 */
class TarifLigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TarifLigne::class);
    }

    /** Prix d'un segment (garedepart → garearrivee) POUR une ligne donnée. */
    public function findMontant(int $ligneId, int $gareDepartId, int $gareArriveeId, int $entrepriseId): ?TarifLigne
    {
        return $this->findOneBy([
            'ligne' => $ligneId,
            'garedepart' => $gareDepartId,
            'garearrivee' => $gareArriveeId,
            'identreprise' => $entrepriseId,
        ]);
    }
}
```

## 3. `src/Entity/Ligne.php` (MODIF : ajouter la collection `tariflignes`)

Ajouter la propriété (après la collection `$arrets`, vers la ligne 134) :

```php
    /**
     * @var Collection<int, TarifLigne>
     */
    #[ORM\OneToMany(targetEntity: TarifLigne::class, mappedBy: 'ligne', cascade: ['persist'], orphanRemoval: true)]
    #[Groups(['read:Ligne', 'read:Ligne:item'])]
    private Collection $tariflignes;
```

Dans le **constructeur** :

```php
    public function __construct()
    {
        $this->arrets = new ArrayCollection();
        $this->voyages = new ArrayCollection();
        $this->tariflignes = new ArrayCollection(); // <-- ajouter
    }
```

Ajouter les méthodes (par ex. après `removeArret`) :

```php
    /**
     * @return Collection<int, TarifLigne>
     */
    public function getTariflignes(): Collection
    {
        return $this->tariflignes;
    }

    public function addTarifligne(TarifLigne $tarifligne): static
    {
        if (!$this->tariflignes->contains($tarifligne)) {
            $this->tariflignes->add($tarifligne);
            $tarifligne->setLigne($this);
        }
        return $this;
    }

    public function removeTarifligne(TarifLigne $tarifligne): static
    {
        if ($this->tariflignes->removeElement($tarifligne)) {
            if ($tarifligne->getLigne() === $this) {
                $tarifligne->setLigne(null);
            }
        }
        return $this;
    }
```

> `TarifLigne` est dans le même namespace `App\Entity` → pas d'import à ajouter.

## 4. `src/Entity/Dto/LigneInput.php` (MODIF : ajouter `tarifs`)

```php
    /**
     * Grille tarifaire : [['garedepart' => 12, 'garearrivee' => 7, 'montant' => 8000], ...]
     * @var array<int, array{garedepart: int, garearrivee: int, montant: int}>
     */
    #[Groups(['write:LigneInput'])]
    public array $tarifs = [];
```

## 5. `src/State/LigneProcessor.php` (MODIF)

**a.** Dans le bloc `Patch`, supprimer les anciens `tariflignes` AVANT recréation (juste après la
suppression des arrêts, avant le `$this->em->flush();`) :

```php
            // Hard delete des anciens tarifs de ligne (config, recréée)
            foreach ($ligne->getTariflignes()->toArray() as $tl) {
                $this->em->remove($tl);
            }
            $ligne->getTariflignes()->clear();
```

**b.** Après l'appel `$this->handleArrets(...)`, récupérer la map des ordres et appeler `handleTarifs` :

```php
        $ordreParGare = $this->handleArrets($ligne, $data->arrets, $entrepriseId);
        $this->handleTarifs($ligne, $data->tarifs, $ordreParGare, $entrepriseId);
```

> `handleArrets` retourne déjà `array<int,int> gareId => ordre` — on s'en sert pour valider que chaque
> tarif relie deux arrêts existants dans le bon sens.

**c.** Ajouter la méthode `handleTarifs` :

```php
    /**
     * Crée les TarifLigne à partir de l'input, en validant que chaque couple est constitué
     * d'arrêts de la ligne et orienté dans le sens du trajet (départ avant arrivée).
     *
     * @param array<int, array{garedepart:int, garearrivee:int, montant:int}> $tarifs
     * @param array<int,int> $ordreParGare  map gareId => ordre
     */
    private function handleTarifs(Ligne $ligne, array $tarifs, array $ordreParGare, int $entrepriseId): void
    {
        $vus = [];
        foreach ($tarifs as $t) {
            $departId = (int) ($t['garedepart'] ?? 0);
            $arriveeId = (int) ($t['garearrivee'] ?? 0);
            $montant = (int) ($t['montant'] ?? 0);

            if ($montant <= 0) {
                throw new BadRequestHttpException('Le montant d\'un tarif doit être strictement positif');
            }
            if (!isset($ordreParGare[$departId], $ordreParGare[$arriveeId])) {
                throw new BadRequestHttpException('Un tarif référence une gare qui n\'est pas un arrêt de la ligne');
            }
            if ($ordreParGare[$departId] >= $ordreParGare[$arriveeId]) {
                throw new BadRequestHttpException('Un tarif doit aller d\'un arrêt vers un arrêt situé après lui');
            }
            $key = $departId . '-' . $arriveeId;
            if (isset($vus[$key])) {
                throw new BadRequestHttpException('Un couple de gares est en doublon dans la grille tarifaire');
            }
            $vus[$key] = true;

            $tarifLigne = new TarifLigne();
            $tarifLigne
                ->setGaredepart($this->gareRepository->find($departId))
                ->setGarearrivee($this->gareRepository->find($arriveeId))
                ->setMontant($montant)
                ->setIdentreprise($entrepriseId);
            $ligne->addTarifligne($tarifLigne);
        }
    }
```

**d.** Ajouter l'import en tête de fichier :

```php
use App\Entity\TarifLigne;
```

## 6. `src/State/TicketProcessor.php` (MODIF : prix par ligne)

**a.** Imports : remplacer

```php
use App\Repository\TarifRepository;
```

par

```php
use App\Repository\TarifLigneRepository;
```

**b.** Constructeur : remplacer le paramètre

```php
        private TarifRepository $tarifRepository
```

par

```php
        private TarifLigneRepository $tarifLigneRepository
```

**c.** Résolution du prix (étape 4) : remplacer

```php
        $tarif = $this->tarifRepository->findMontant($monteeId, $descenteId, $entrepriseId);
```

par

```php
        $tarif = $this->tarifLigneRepository->findMontant($ligne->getId(), $monteeId, $descenteId, $entrepriseId);
```

> `$ligne` est déjà disponible dans le processor. `$tarif->getMontant()` reste inchangé.

## 7. `src/Entity/Data/CorbeilleRegistry.php` (MODIF, optionnel)

Si tu **gardes** `TarifLigne` dans la corbeille — mais comme `TarifLigne` n'a **pas** de soft-delete, tu
peux simplement **retirer** la ligne `'tarif' => Tarif::class` (si tu supprimes l'entité globale) et ne
rien ajouter. Si tu veux gérer la corbeille de `TarifLigne`, il faut d'abord lui donner `EntityBase`.

```php
// Retirer si on supprime l'entité globale :
//   use App\Entity\Tarif;   ← supprimer l'import
//   'tarif' => Tarif::class, ← supprimer l'entrée
```

## 8. Suppression de l'entité globale `Tarif` (si tu l'abandonnes)

Supprimer : `src/Entity/Tarif.php`, `src/Repository/TarifRepository.php`, `src/State/TarifProcessor.php`,
et retirer `Tarif` de `CorbeilleRegistry`. (Sinon, la garder en parallèle ne gêne pas.)

## 9. Migration (NOUVELLE) — `src/migrations/VersionXXXXXXXXXXXXXX.php`

`php bin/console make:migration` puis remplacer le contenu par :

```php
public function up(Schema $schema): void
{
    // 1. Recréer la table tarif_ligne (schéma d'origine, cf. Version20260611213840)
    $this->addSql('CREATE TABLE tarif_ligne (id INT AUTO_INCREMENT NOT NULL, montant INT NOT NULL, identreprise INT DEFAULT NULL, ligne_id INT NOT NULL, garedepart_id INT NOT NULL, garearrivee_id INT NOT NULL, INDEX IDX_8EC440735A438E76 (ligne_id), INDEX IDX_8EC4407316887400 (garedepart_id), INDEX IDX_8EC44073B466CD0 (garearrivee_id), UNIQUE INDEX UNIQ_tarifligne (ligne_id, garedepart_id, garearrivee_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC440735A438E76 FOREIGN KEY (ligne_id) REFERENCES ligne (id)');
    $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC4407316887400 FOREIGN KEY (garedepart_id) REFERENCES gare (id)');
    $this->addSql('ALTER TABLE tarif_ligne ADD CONSTRAINT FK_8EC44073B466CD0 FOREIGN KEY (garearrivee_id) REFERENCES gare (id)');

    // 2. Fan-out : pour chaque ligne, chaque couple (arrêt amont, arrêt aval), reprendre le prix global
    $this->addSql('
        INSERT INTO tarif_ligne (ligne_id, garedepart_id, garearrivee_id, montant, identreprise)
        SELECT a1.ligne_id, a1.gare_id, a2.gare_id, t.montant, l.identreprise
        FROM arret a1
        JOIN arret a2 ON a2.ligne_id = a1.ligne_id AND a2.ordre > a1.ordre
        JOIN ligne l ON l.id = a1.ligne_id
        JOIN tarif t ON t.garedepart_id = a1.gare_id
                    AND t.garearrivee_id = a2.gare_id
                    AND t.identreprise = l.identreprise
                    AND t.deleted_at IS NULL
    ');

    // 3. (optionnel) supprimer la grille globale
    $this->addSql('DROP TABLE tarif');
}

public function down(Schema $schema): void
{
    // Best-effort inverse : recréer tarif depuis tarif_ligne (MAX par couple), puis drop tarif_ligne.
    $this->addSql('DROP TABLE tarif_ligne');
}
```

> ⚠️ Le fan-out ne crée des tarifs que pour les couples couverts par un `tarif` global. Vérifie ensuite
> qu'aucun segment vendable ne reste sans `TarifLigne`.

---

# FRONTEND (`FT-Transport`)

## 10. `assets/react/models/ligne.model.ts` (MODIF)

```ts
export interface TarifLigne {
    garedepart: GareRef
    garearrivee: GareRef
    montant: number
}

export interface Ligne {
    id: number
    codeligne: string
    libelle: string | null
    gareorigine: GareRef
    gareterminus: GareRef
    arrets: Arret[]
    tariflignes: TarifLigne[]   // <-- ajouter
    voyagesCount: number
}
```

## 11. `assets/react/controllers/Exploitation/LigneForm.tsx` (MODIF : remettre la grille)

**a.** Étendre l'interface initiale :

```ts
interface LigneInitial {
    id: number
    libelle: string | null
    heuredepart?: string | null
    arrets: { gare: GareRef; ordre: number }[]
    tariflignes?: { garedepart: { id: number }; garearrivee: { id: number }; montant: number }[]
}
```

**b.** State + helpers (sous les autres `useState`) :

```ts
    const pairKey = (a: number, b: number) => `${a}-${b}`

    const [fares, setFares] = useState<Record<string, string>>(() => {
        const init: Record<string, string> = {}
        ligne?.tariflignes?.forEach((t) => {
            init[pairKey(t.garedepart.id, t.garearrivee.id)] = String(t.montant)
        })
        return init
    })

    // Toutes les paires (amont < aval) des arrêts ordonnés
    const pairs = useMemo(
        () =>
            stops.flatMap((dep, i) =>
                stops.slice(i + 1).map((arr) => ({ depart: dep, arrivee: arr }))
            ),
        [stops]
    )

    const setFare = (key: string, value: string) =>
        setFares((prev) => ({ ...prev, [key]: value }))
```

**c.** Dans `handleSubmit`, construire `tarifs` et l'ajouter au payload (avant le `fetch`) :

```ts
        // Tarifs renseignés uniquement (montant > 0)
        const tarifs = pairs
            .map((p) => ({
                garedepart: p.depart.id,
                garearrivee: p.arrivee.id,
                montant: Number(fares[pairKey(p.depart.id, p.arrivee.id)] || 0),
            }))
            .filter((t) => t.montant > 0)

        const payload = {
            libelle: libelle.trim(),
            heuredepart: heuredepart || null,
            arrets: stops.map((s, idx) => ({ gare: s.id, ordre: idx })),
            tarifs, // <-- ajouter
        }
```

**d.** Carte « Grille tarifaire » (JSX, avant le bloc des boutons Annuler/Créer) :

```tsx
            {/* Grille tarifaire (matrice O-D) */}
            {stops.length >= 2 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Grille tarifaire</CardTitle>
                        <CardDescription>Prix par tronçon (origine → arrêt suivant…).</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {pairs.map((p) => {
                            const key = pairKey(p.depart.id, p.arrivee.id)
                            return (
                                <div key={key} className="flex items-center gap-3">
                                    <span className="flex-1 text-sm">
                                        {p.depart.libelle} <span className="text-muted-foreground mx-1">→</span> {p.arrivee.libelle}
                                    </span>
                                    <Input
                                        type="number"
                                        min={0}
                                        placeholder="FCFA"
                                        className="w-32"
                                        value={fares[key] ?? ""}
                                        onChange={(e) => setFare(key, e.target.value)}
                                    />
                                </div>
                            )
                        })}
                    </CardContent>
                </Card>
            )}
```

> `Card, CardHeader, CardTitle, CardDescription, CardContent, Input, useMemo` sont déjà importés dans
> `LigneForm.tsx`.

## 12. `src/Controller/LigneController.php` (MODIF : renvoyer `tarifs`)

Dans `new` **et** `edit`, ajouter `tarifs` au payload envoyé à l'API :

```php
            $ligne = $this->api->post('/api/lignes', [
                'libelle' => $payload['libelle'] ?? null,
                'heuredepart' => $payload['heuredepart'] ?? null,
                'arrets' => $payload['arrets'] ?? [],
                'tarifs' => $payload['tarifs'] ?? [],   // <-- ajouter
            ]);
```

(idem pour le `$this->api->patch('/api/lignes/' . $id, [...])` dans `edit`).

## 13. `templates/ligne/show.html.twig` (MODIF : remettre la carte)

Remplacer la note « grille tarifaire globale » par la carte qui itère `ligne.tariflignes` :

```twig
    <div class="card p-6 mb-3">
        <h3 class="text-lg font-bold mb-3">Grille tarifaire</h3>
        <ul class="space-y-2">
            {% for tarif in ligne.tariflignes %}
            <li class="flex items-center justify-between gap-3 text-sm">
                <span>
                    <span class="font-medium">{{ tarif.garedepart.libelle }}</span>
                    <span class="mx-1.5 text-muted-foreground">→</span>
                    <span class="font-medium">{{ tarif.garearrivee.libelle }}</span>
                </span>
                <span class="tabular-nums font-semibold">{{ tarif.montant|number_format(0, ',', ' ') }} FCFA</span>
            </li>
            {% else %}
            <li class="text-sm text-muted-foreground">Aucun tarif défini.</li>
            {% endfor %}
        </ul>
    </div>
```

## 14. Supprimer la gestion de la grille GLOBALE (si abandon de `Tarif`)

- Fichiers à supprimer : `src/Controller/TarifController.php`, `src/Form/TarifFormType.php`,
  `assets/react/controllers/Exploitation/TarifTable.tsx`, `assets/react/models/tarif.model.ts`,
  `templates/tarif/{index,new,edit,_form}.html.twig`.
- `templates/base.html.twig` : retirer le lien sidebar « Grille tarifaire » (`{% if is_granted('TARIF_VOIR') %}…`).

---

# VÉRIFICATIONS

```bash
# BK
php -l src/Entity/TarifLigne.php
php -l src/Repository/TarifLigneRepository.php
php -l src/State/LigneProcessor.php
php -l src/State/TicketProcessor.php
php bin/console doctrine:schema:validate --skip-sync     # mapping OK
php bin/console doctrine:mapping:info | grep TarifLigne  # [OK]

# FT
php bin/console lint:twig templates/ligne
npm run dev    # 0 erreur (8 warnings CSS pré-existants)
```

Test fonctionnel : créer une ligne + sa grille → vendre un ticket sur un tronçon → le prix vient bien du
`TarifLigne` de **cette** ligne.

---

# RÉFÉRENCES
- Migration `tarif_ligne → tarif` (à inverser) : `BK-Transport/migrations/Version20260612120000.php`
- Baseline contenant le schéma `tarif_ligne` d'origine : `BK-Transport/migrations/Version20260611213840.php`








# opus.md — Rendre `nomclient` et `contactclient` obligatoires sur le billet

> ⚠️ Document d'instructions **uniquement** — rien n'a été modifié dans le code des tickets.
> Suis les étapes ci-dessous quand tu voudras appliquer le changement.

Les deux champs sont aujourd'hui **optionnels** :
- BK-Transport : `src/Entity/Ticket.php` → `nomclient` / `contactclient` en `nullable: true`, sans contrainte.
- FT-Transport : `assets/react/controllers/Billetterie/TicketForm.tsx` → labels « (optionnel) », aucune validation (commentaire explicite ligne ~640 : « nomclient et contactclient sont optionnels »).

Le backend est la **source de vérité** : c'est lui qui doit refuser un billet sans ces champs. Le frontend ne fait qu'améliorer l'UX (message immédiat). Fais **les deux**.

---

## 1) Backend — validation (obligatoire, le vrai garde-fou)

Dans `BK-Transport/src/Entity/Ticket.php`, ajoute `#[Assert\NotBlank]` sur les deux propriétés. (L'import `use Symfony\Component\Validator\Constraints as Assert;` est déjà présent dans l'entité.)

```php
#[ORM\Column(length: 255, nullable: true)]
#[Assert\NotBlank(message: 'Le nom du client est obligatoire')]
#[Groups(['read:Voyage', 'read:Ticket', 'write:Ticket', 'write:Ticket:update'])]
private ?string $nomclient = null;

#[ORM\Column(length: 255, nullable: true)]
#[Assert\NotBlank(message: 'Le contact du client est obligatoire')]
// #[Assert\Length(min: 6, minMessage: 'Contact trop court')] // optionnel
#[Groups(['read:Voyage', 'read:Ticket', 'write:Ticket', 'write:Ticket:update'])]
private ?string $contactclient = null;
```

Remarques importantes :
- **Garde `nullable: true` en base.** On ne change PAS la colonne SQL → **pas de migration**, et les anciens billets éventuellement à `null` ne cassent pas la base. C'est la *validation* (couche applicative) qui impose la présence, pas la contrainte SQL.
- `#[Assert\NotBlank]` sans `groups` s'applique au **groupe de validation `Default`**, donc sur la **création (POST)** ET la **modification (PATCH)** — exactement ce qu'on veut.
- ⚠️ **Effet de bord à vérifier** : toute opération qui *re-valide* un billet (ex. le désistement `DesistementProcessor`, ou un PATCH partiel) validera désormais aussi `nomclient`/`contactclient`. Pour un billet récent c'est sans effet (les valeurs sont déjà là). Pour un **ancien** billet à `null`, un PATCH échouerait. Deux options :
  1. Laisser tel quel (les nouveaux billets sont conformes ; les anciens sont rares/inexistants).
  2. Restreindre la contrainte aux écritures « billet » via des **groupes de validation** dédiés et les déclarer sur les opérations `Post`/`Patch` concernées (`validationContext: ['groups' => ['Default', 'ticket:write']]`) en mettant `groups: ['ticket:write']` sur le `NotBlank`. Plus chirurgical mais plus verbeux.
- Le message d'erreur remonte déjà proprement au front : `ApiHelper::throwFromResponse()` mappe les `violations` par champ dans `ApiException`.

---

## 2) Frontend — formulaire de **vente** (`TicketForm.tsx`)

Fichier : `FT-Transport/assets/react/controllers/Billetterie/TicketForm.tsx`

### a) Validation à la soumission
Dans `handleSubmit`, remplace le commentaire « nomclient et contactclient sont optionnels » (~ligne 640, juste avant le `const tickets = selectedSieges.map(...)`) par une vérification par siège :

```ts
// Chaque billet doit avoir un nom et un contact client
for (const s of selectedSieges) {
    const infos = clientInfos[s.id];
    if (!infos?.nomclient?.trim()) {
        setFlashError(`Renseignez le nom du client pour le siège ${s.numero}.`);
        return;
    }
    if (!infos?.contactclient?.trim()) {
        setFlashError(`Renseignez le contact du client pour le siège ${s.numero}.`);
        return;
    }
}
```
(Adapte `s.numero` au champ réel du siège si besoin.)

Et dans le `map` qui construit `tickets`, retire les `|| null` pour envoyer la valeur saisie (trim) :
```ts
nomclient: clientInfos[s.id].nomclient.trim(),
contactclient: clientInfos[s.id].contactclient.trim(),
```

### b) Champs marqués requis (UX)
Pour chaque siège (~lignes 994–1030), enlève le « (optionnel) » et marque les inputs `required` :
```tsx
<Label htmlFor={`nom-${siege.id}`} className="text-gray-600">
    Nom du client <span className="text-red-500">*</span>
</Label>
<Input id={`nom-${siege.id}`} required placeholder="Nom complet"
       value={clientInfos[siege.id]?.nomclient ?? ""}
       onChange={(e) => updateClientInfo(siege.id, "nomclient", e.target.value)} />
```
Idem pour `Contact` (`contact-${siege.id}`, champ `contactclient`).

> Le `required` HTML seul ne suffit pas (la soumission passe par `fetch`, pas un `<form>` natif) → la vraie barrière côté front reste la boucle du a).

---

## 3) Frontend — formulaire d'**édition** du billet

`FT-Transport/templates/ticket/edit.html.twig` (groupe `write:Ticket:update`). Si les champs `nomclient`/`contactclient` y sont éditables, rends-les obligatoires aussi :
- formulaire Symfony : ajoute `'required' => true` (et éventuellement une contrainte `NotBlank`) sur les champs dans le `FormType` correspondant ;
- ou template manuel : ajoute l'attribut `required` sur les `<input>`.
Le backend (étape 1) couvre déjà ce cas, l'édition ne fait qu'aligner l'UX.

---

## 4) Build & test
```bash
# FT-Transport
npm run build
```
Puis tester :
- vente d'un billet **sans** nom/contact → message d'erreur, pas d'appel API qui passe ;
- vérifier qu'un POST direct sur l'API **sans** ces champs renvoie bien `422` avec les violations (preuve que le garde-fou backend marche).