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
    > php bin/console doctrine:database:create
    > php bin/console make:migration
    > php bin/console doctrine:schema:update --force : `--env=test` pour les tests
        > php bin/console doctrine:schema:update --dump-sql
    > php bin/console doctrine:fixtures:load : `--env=test` pour les tests
    > php bin/console translation:extract --dump-messages fr
    > php bin/console translation:extract --force fr --format=yaml
    > php bin/console make:test
    > php bin/console make:state-processor
    > php bin/console make:state-provider

- **L..**
    >

- 

- 
Docker, Server, Sql Postgre(claude), Git(claude), ia, UI, Symfony, backups et backfill

php -S 10.0.2.2:8000 -t public
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
flutter build apk --release --dart-define=API_BASE_URL=https://proud-gauntlet-elongated.ngrok-free.dev
ngrok http 80 --url https://proud-gauntlet-elongated.ngrok-free.dev

SycaPay, Jèko, GeniusPay, AdjeminPay

- Faire une mise à jour du Framework vers .. 8. et montre moi comment faire
- Ne pas oublié de mettre à jour les `README.md`, la prise en main, la page d'aide et on vas continuer dans session suivante
- Wiki du projet



## Points ouverts que j'ai relevés

- Mobile Money réel : toujours simulé, en attente d'un agrégateur souscrit.
- commercialflutter « à venir » : bilan de recette par période (nécessite un endpoint dédié, `/api/stats/commercial` étant admin) et fidélité à la vente.
- Alertes : seuils encore en constantes de service, externalisation par entreprise non faite. Pas de notifications côté mobile.
- Piste notée mais non entamée : visite interactive à la première connexion (Driver.js / Shepherd.js).







Vu qu'on a la notion de caisse, comment on calcule la recette

Palier 3 — les sorties et le visa. Depense, remboursement du désistement, réservations, /viser, CaisseScopeExtension, CaisseGuard, permissions, audit. Vérifié : vente + annulation le même jour laissent le théorique inchangé ; un agent ne vise pas sa caisse. !!

la vente du commercial — en ligne et via /sync — ne crée aucune session ; !! Mais est ce que ça entre dans la caisse de sa gare !!


Et pourquoi il n'y a pas d'entité Caisse pour la gare ? chatgpt
Ignore le dossier `brl/` dans `Backend-Transport/tools/`, c'est un brouillon aussi



Montre moi l'état de la recette actuelle 








Je vois que fournisseur est une liste vide pour le chef de gare ! est ce que c'est voulue ?
-- La modification de dépense..

Chaque gare a un solde , à la création du'une gare on crée sold initial, on pourra savoir combien la gare à vendu aujourdhui
Aussi, on m'a parler d'un truc du genre : Chaque doit avoir un sold initial qu'on décrémente et incrémente, aussi combien la gare a vendu aujourd'hui, hier, etc.., la dépense que la gare à faite est sortir de quel recette.. etc... !



# Mettre les statistiques de recettes au claire en connessant l'état actuelle et les allignés

recettes − dépannages − approvisionnements − dépenses ici vu la notion de caisse je me dis que c'est caisse - 

Depense n'est pas ajoutée à GareScopedEntities::ENTITIES : y figurer donnerait à tout ROLE_ADMIN_GARE un bypass d'écriture sans permission, et le droit de déléguer ces permissions. Sur une donnée qui fait sortir de l'argent, la permission reste explicite, accordée par l'admin d'entreprise — même régime que le stock.

la limite du résultat par gare : les dépannages et approvisionnements n'étant rattachés à aucune gare, je ne peux pas les y déduire. Je préfère l'afficher honnêtement (« résultat d'exploitation », pas « bénéfice », plus deux totaux qui se recoupent côté entreprise) plutôt que de sortir un chiffre qui ment ;


Deux constats de l'exploration, avant de te poser les bonnes questions :

Rien n'existe pour l'argent qui sort : aucune entité, migration, route ni écran. Les seuls « coûts » modélisés sont Depannage::$couttotal et Detailapprovisionnement::$couttotal.

Le bénéfice net se calcule à un seul endroit, en dur : FinancierStatsProvider.php:59 — recettes − coutDepannages − coutApprovisionnements. C'est le point d'entrée naturel pour un troisième poste.


- Aussi, peut-on connaître les dépenses éffectué lors d'un voyage
- A la fin du voyage les péages nous ont pris combien
- La somme net reçu sur un car dans les statistiques
- Plus de détails sur la recette et centralisé la recette pour que l'admin ne se perde pas
- Vérifié si hors courriers est bien pris en compte dans toutes les stats


On m'a parler aussi d'une notion qui est : Les charges qui contient aussi la main d'oeuvre externe de dépannage :: Dans la création de dépannage avoir la possibilité de choisir les mains d'oeuvre :: Frais externe de main d'oeuvre == 3 chantier après frais de route

- RH (Charges, paie)

# 8. Pourquoi lier les dépenses au voyage

Très important.

Sinon :

* impossible de calculer la rentabilité réelle.

---

# 9. Exemple de calcul réel

## Voyage

```text id="a0n12u"
Recettes tickets = 300000
```

---

## Dépenses

| Type      | Montant |
| --------- | ------- |
| Carburant | 70000   |
| Péage     | 10000   |
| Chauffeur | 30000   |
| Dépannage | 15000   |

--- Frais de route


# 17. Ce que je te conseille maintenant

Vu ton système :

> la prochaine grosse brique logique est clairement :

## Gestion des dépenses + caisse

Parce que tu as déjà :

* recettes,
* stock,
* maintenance.

Mais il manque :

* la sortie d’argent globale.

---

Caisse journalière

# A. Dépenses d’exploitation voyage

Liées directement à un voyage.

Exemples :

* carburant,
* péage,
* frais chauffeur,
* frais de route,
* repas équipage.

---

# B. Dépenses de maintenance

Exemples :

* pièces détachées,
* réparations,
* main d’œuvre mécanique.

Tu en gères déjà une partie avec :

```text id="c5wx15"
Depannage
```

---

# C. Dépenses administratives

Exemples :

* électricité,
* internet,
* loyers,
* fournitures.

---

# D. Dépenses RH

Exemples :

* salaires,
* primes,
* avances.

---

# 3. Le vrai principe métier

Chaque dépense doit répondre à :

| Question         | Exemple           |
| ---------------- | ----------------- |
| Qui a dépensé ?  | chauffeur         |
| Pourquoi ?       | carburant         |
| Pour quoi ?      | voyage V001       |
| Combien ?        | 25 000            |
| Quand ?          | 12 juin           |
| Validé par qui ? | chef exploitation |

---

# 4. Ce qu’il te manque actuellement

Tu as :

* approvisionnements,
* dépannage.

Mais tu n’as pas encore :

> une vraie gestion générale des dépenses.

Et elle devient indispensable.

---

# 5. Ce qu’il faut créer

Je te conseille fortement une entité :

```text id="wnif5y"
Depense
```

---

# 6. Structure recommandée

```text id="bf91d6"
Depense
- libelle
- montant
- date_depense
- categorie
- description
- mode_paiement
- justificatif
- voyage NULL
- car NULL
- personnel NULL
- entreprise
```

---

# 7. Exemple concret

---

## Dépense carburant

```text id="kvyrk4"
Libellé : Carburant voyage Korhogo
Montant : 45000
Voyage : V001
Car : 2548AB01
```

---

## Dépense péage

```text id="bx1mfr"
Libellé : Péage Yamoussoukro
Montant : 5000
Voyage : V001
```

---

## Dépense administrative

```text id="11p0k5"
Libellé : Internet gare
Montant : 30000
```

---

# 8. Pourquoi lier les dépenses au voyage

Très important.

Sinon :

* impossible de calculer la rentabilité réelle.

---

# 9. Exemple de calcul réel

## Voyage

```text id="a0n12u"
Recettes tickets = 300000
```

---

## Dépenses

| Type      | Montant |
| --------- | ------- |
| Carburant | 70000   |
| Péage     | 10000   |
| Chauffeur | 30000   |
| Dépannage | 15000   |

---

## Résultat

```text id="e1wy04"
Bénéfice = 175000
```

---

# 10. Très important : catégories de dépenses

Ajoute une entité ou enum.

---

## Exemple

```php id="0d6mji"
enum CategorieDepense
{
    CARBURANT,
    PEAGE,
    MAINTENANCE,
    SALAIRE,
    ADMINISTRATIF,
    AUTRE
}
```

---

# 11. Gestion des justificatifs --- Ici on a pris forfait final

Très important dans les entreprises.

Tu peux stocker :

* facture,
* reçu,
* photo.

Exemple :

```text id="v8w72e"
Depense
- justificatif
```

---

# 12. Workflow réel souvent utilisé

---

## Étape 1

Le chauffeur reçoit une avance.

---

## Étape 2

Il dépense pendant le voyage.

---

## Étape 3

Il revient avec les justificatifs.

---

## Étape 4

Le gestionnaire valide les dépenses.

---

# 13. Tu peux gérer les avances aussi

Très utile plus tard.

---

## Exemple

```text id="g52g4m"
AvanceVoyage
- voyage
- personnel
- montant
```

Puis :

* dépenses déduites de l’avance.

---

# 14. Très important : validation

Dans les vraies compagnies :

> une dépense doit souvent être validée.

Tu peux ajouter :

```text id="1n94x0"
Depense
- statut
- valide_par
- date_validation
```

---

# 15. Différence avec ton module Approvisionnement

---

## Approvisionnement

Concerne :

* entrée de stock.

Exemple :

* achat de pneus.

---

## Dépense

Concerne :

* sortie d’argent.

Exemple :

* paiement carburant.

---

# 16. Relation entre les deux

Un approvisionnement peut :

* générer automatiquement une dépense.

Exemple :

```text id="xop1dn"
Approvisionnement :
Achat pneus = 200000
```

↓

```text id="n2njd5"
Depense :
Paiement fournisseur = 200000
```

Très professionnel.

# 19. Niveau avancé futur

Plus tard tu peux ajouter :

* caisse journalière,
* comptabilité,
* budgets,
* fournisseurs à crédit,
* échéances,
* dette fournisseurs,
* rapprochement bancaire.

Et là ton système deviendra un vrai ERP transport complet.




# 2. La recette d'une gare

Pour moi, la recette d'une gare est **tout ce qui a été vendu au nom de cette gare**, indépendamment du canal de paiement.

Elle comprend :

* ✅ Tickets vendus au guichet de la gare
* ✅ Tickets vendus par les commerciaux rattachés à cette gare
* ✅ Réservations initiées par cette gare (si elles sont payées)
* ✅ Bagages enregistrés par cette gare
* ✅ Courriers déposés dans cette gare

Elle **ne dépend pas** de savoir si le paiement a été effectué :

* au guichet ;
* par un commercial ;
* en ligne.





























======

- Dans le darkMode on vas rajouter le choix du `système` aussi en plus du mode `dark` et `light`.
- QRCode des tickets à redirigé vers une page qui montre que le ticket est toujours valide en tenant compte du périmètre entreprise (conersation claude)
    B2 Contrôle d'embarquement par QR => Aussi peut on doit on le faire côté serveur
- Générer l'impression côté Backend et donner la possibilités au Frontend de choisir le modèle qu'il souhaite, donc on aura plusieurs formats de tickets(aussi, courriers, bagage, bordereau..) :: Regarde d'autres endroit de l'application dans lequel il est logique de dédié toutes les tâches lourdes au backend, c'est qui est logique dans une architecture séparé
- Revoire le cas de désistement qui se fait que sur un voyage de la même ligne !
- Plus d'infos sur le tableau de bord de gare concernant les filtres


Si un client désiste pour un voyage est ce que le nouveau voyage doit être forcément sur la même ligne ou sur n'importe quel voyage ouvert

Cela dépend de ce que tu appelles "désister", mais dans les compagnies de transport on distingue généralement :

1. **Annulation simple** → remboursement ou avoir.
2. **Report du billet** → transfert vers un autre voyage.

Dans le cas du report, le nouveau voyage n'est **pas forcément sur le même voyage**, mais il doit normalement rester **compatible avec le trajet acheté**.

---

## Cas 1 : Même ligne (le plus courant)

Le client avait :

```text
Abidjan → Korhogo
Voyage V001
08h
```

Il ne peut plus voyager.

On le reporte sur :

```text
Abidjan → Korhogo
Voyage V002
14h
```

C'est le cas le plus fréquent.

---

## Cas 2 : Autre voyage de la même ligne

Par exemple :

```text
Ligne : Abidjan → Korhogo
```

Voyages :

```text
V001 - 08h
V002 - 14h
V003 - 18h
```

Le billet est déplacé vers un autre départ.

Très courant.

---

## Cas 3 : Voyage d'une autre ligne compatible

Supposons :

```text
L1 : Abidjan → Korhogo
L2 : Abidjan → Bouaké → Korhogo
```

Le client avait acheté :

```text
Abidjan → Korhogo
```

S'il reste des places sur L2, certaines compagnies acceptent le transfert.

Métierement, ce qui compte n'est pas la ligne mais :

```text
Départ
Arrivée
```

---

## Cas 4 : Voyage totalement différent

Exemple :

```text
Billet :
Abidjan → Korhogo
```

et on veut le transférer vers :

```text
Abidjan → Man
```

Là, ce n'est plus un report.

C'est pratiquement :

* une annulation,
* suivie d'un nouvel achat.

---

## Dans ton système

Comme tu tends vers un modèle segmenté :

```text
Ticket
- gare_depart
- gare_arrivee
- voyage
```

Je te conseillerais la règle suivante :

### Un report est autorisé si :

```text
nouveau voyage passe par
gare_depart
et
gare_arrivee
```

et qu'il reste des places sur le segment.

Ainsi :

| Ancien billet     | Nouveau voyage                    | Autorisé |
| ----------------- | --------------------------------- | -------- |
| Abidjan → Korhogo | Autre voyage Abidjan → Korhogo    | ✅        |
| Abidjan → Korhogo | Voyage Abidjan → Bouaké → Korhogo | ✅        |
| Abidjan → Korhogo | Voyage Abidjan → Man              | ❌        |

---

## Comment font les compagnies ?

La plupart ont une logique proche de :

```text
Annulation
ou
Report vers le prochain départ disponible
```

Le plus souvent :

1. Le siège est libéré sur l'ancien voyage.
2. Un nouveau ticket est créé sur le nouveau voyage.
3. Une éventuelle différence tarifaire est calculée.

---

## Modélisation

Je te déconseille de modifier directement le ticket existant.

Il est préférable de conserver l'historique :

```text
Ticket T001
    STATUT = REPORTE
```

et créer :

```text
Ticket T045
    STATUT = ACTIF
    ticket_origine = T001
```

Ainsi tu gardes une trace complète des reports.

Pour ton application, la règle métier la plus réaliste est :

> Un client peut être reporté sur n'importe quel voyage ouvert qui dessert sa gare de départ et sa gare d'arrivée, même si ce voyage appartient à une autre ligne. Ce qui compte est la possibilité réelle d'effectuer le trajet acheté.
















Moyen de paiement, on vas mettre plusieurs, mais on vas commencer par Stripe
Le Mobile Money reste hors périmètre en attendant l'agrégateur
- Mobile money -> Frais payé par le client + un plus pour les timbres du genre au lieu de 100F on ajoute 200F

- La modification peut géner plusieurs choses comme les réservations...
- Prévenir les alertes via une baffe ou aussi ce qui se fait dans les aéroports qui averti le départ..
- Auth client réservation via numéro de téléphone
- Retour client et agent vi codeqr et peut envoyer des images, vidéos etc...

- Hors-ligne pour le guichet au cas ou il n'y a coupure de courant (Ici vu qu'il a TPE je me dis que ce n'est pas nécéssaire)
- Dépannage auto création typepanne

- 
Le voir de ceux qui en dépendent au lieu de tout comme je l'ai fais pour...
    new GetCollection(
        security: "is_granted('VOIR', 'Car') or is_granted('VOIR', 'Voyage') or is_granted('VOIR', 'Depannage')",
        openapi: new Operation(
            summary: 'La liste des cars',
            description: 'Permet de voir la liste des cars',
            security: [['bearerAuth' => []]]
        )
    ), CAR
Ticket, Ville, Voyage, Ligne, Gare, Personnel, Tarif

Annuler voyage qui annule tous les tickets, corriers etc.. au cas ou le car ne part pas

- Quand tu regarde l'appli comment je pourrais écrire une explication ou guide pour les endroits éssenciel de l'application
- La possibilités d'activer ou désactiver des modules dans l'application















Vu tout ce que nous avons construit ensemble, si ton objectif est d'avoir un **logiciel professionnel de gestion de compagnies de transport** (multi-entreprises, multi-gares, réservations, exploitation, temps réel, application mobile, etc.), je partirais sur une architecture moderne plutôt que de simplement changer de framework.

## Backend

Je garderais **Symfony**.

Pourquoi ?

* Tu maîtrises déjà Symfony.
* API Platform est excellent pour construire des API REST.
* L'écosystème est mature.
* Facile à faire évoluer vers des microservices plus tard si nécessaire.

J'ajouterais :

* **API Platform**
* **PostgreSQL** (je le préfère à MySQL pour ce type d'application)
* **Redis** (cache, files d'attente, sessions, verrous)
* **Messenger** (traitements asynchrones)
* **Mercure ou WebSocket** pour le temps réel

---

## Frontend Web

Je passerais sur **Next.js**.

Pourquoi ?

* Très performant.
* Excellent avec React.
* Facile pour construire un tableau de bord riche.
* Très bon pour les SaaS.

Tu avais d'ailleurs demandé si React ou Next.js était préférable pour consommer ton backend Symfony : je resterais sur **Next.js**.

---

## Mobile

Je garderais **Flutter**.

Tu as déjà créé le projet.

Un seul code pour :

* Android
* iOS

---

## Temps réel

Pour le suivi des voyages, je ne me limiterais pas à Mercure.

Je mettrais :

* WebSocket
* ou Mercure si tu restes sur Symfony

Pour afficher :

* position du car
* retards
* ventes
* nouveaux tickets
* nouvelles réservations

sans recharger la page.

---

## Cartographie

Pour le suivi GPS :

* Google Maps
* ou OpenStreetMap + Leaflet (moins coûteux)

Tu pourras afficher :

* le trajet ;
* la position du car ;
* les gares ;
* les retards.

---

## Notifications

* Firebase Cloud Messaging (Flutter)
* Emails
* SMS (si besoin)

---

## Authentification

* JWT
* Refresh Token
* Gestion fine des rôles (tu as déjà un bon modèle RBAC)

---

## Déploiement

* Docker
* Nginx
* GitHub Actions
* VPS ou Cloud

---

## Base de données

Je passerais sur **PostgreSQL**.

Avec notamment :

* index performants ;
* transactions solides ;
* types avancés ;
* possibilité d'utiliser des extensions géographiques si tu ajoutes beaucoup de fonctionnalités GPS.

---

# Si tu veux vraiment du temps réel

Je pense que ton application aura besoin de deux types de temps réel.

### 1. Temps réel métier

Exemple :

* un ticket est vendu ;
* une réservation est créée ;
* un voyage est annulé.

Tous les écrans concernés se mettent à jour automatiquement.

---

### 2. Temps réel GPS

Le chauffeur possède l'application Flutter.

Toutes les 10 à 30 secondes :

```text
Latitude

Longitude

Vitesse

Heure
```

sont envoyées au serveur.

L'application web affiche alors :

* le car sur la carte ;
* le retard ;
* le prochain arrêt.

---

# Si je devais repartir de zéro aujourd'hui

Je choisirais :

| Couche          | Technologie                             |
| --------------- | --------------------------------------- |
| Backend         | Symfony + API Platform                  |
| Base de données | PostgreSQL                              |
| Cache           | Redis                                   |
| Temps réel      | Mercure + WebSocket (selon les besoins) |
| Web             | Next.js + React Query + TypeScript      |
| Mobile          | Flutter                                 |
| Cartographie    | Google Maps ou Leaflet                  |
| Auth            | JWT + Refresh Token                     |
| Déploiement     | Docker + Nginx + GitHub Actions         |

## Mon dernier conseil

Au vu de l'évolution de ton projet, je pense que le plus grand changement ne sera **pas la technologie**, mais **l'architecture métier**.

Tu commences à intégrer des notions d'exploitation en temps réel (passage dans les gares, retards, suivi des cars, bordereaux, statistiques, réservations, multi-gares). À ce stade, je structurerais le projet autour de grands domaines fonctionnels (Administration, Exploitation, Billetterie, Réservations, Flotte, Stock, Comptabilité, etc.) plutôt que d'une simple organisation par entités.

C'est cette organisation qui fera réellement passer ton application d'un CRUD évolué à un logiciel professionnel de gestion de compagnies de transport.









Explique moi le principe de clôture de caisse dans une tel application car je veux mettre en place la fonctionnalité de vente hors-ligne via une application mobile

Oui. Et avec une **vente hors-ligne sur mobile**, la clôture de caisse devient encore plus importante, parce qu'il faut distinguer **ce qui a été vendu sur le téléphone** de **ce qui a déjà été synchronisé avec le serveur**.

### 1. Une caisse représente quoi ?

Dans ton application, une caisse peut être rattachée à un :

* guichetier ;
* commercial ;
* point de vente ;
* éventuellement une gare.

Exemple :

```text
Caisse C001
Agent : Kouassi
Gare : Bouaké
Date : 11/09/2026
```

L'agent ouvre sa caisse avant de commencer ses ventes.

---

### 2. À l'ouverture

On peut avoir un **fonds de caisse initial**.

Exemple :

```text
Fonds initial : 50 000 FCFA
```

Puis l'agent commence à vendre.

```text
Ticket 1     10 000
Ticket 2      8 000
Bagage        2 000
Ticket 3     10 000
--------------------
Ventes       30 000
```

S'il n'y a aucune autre opération :

```text
Espèces théoriques = 50 000 + 30 000
                   = 80 000 FCFA
```

---

# 3. La clôture

À la fin de sa journée ou de son service, l'agent demande la clôture.

Le système calcule ce qu'il **devrait avoir**.

Par exemple :

```text
Fonds initial             50 000
Ventes espèces            30 000
Ventes Mobile Money       20 000
--------------------------------
Total encaissé            50 000
```

L'agent déclare ensuite ce qu'il possède réellement :

```text
Espèces physiques : 79 000 FCFA
Mobile Money      : 20 000 FCFA
```

Le système compare le théorique et le réel.

Il peut alors avoir :

```text
Écart : -1 000 FCFA
```

C'est ce qu'on appelle généralement un **écart de caisse**.

---

# 4. Maintenant, ton problème : le hors-ligne

Imaginons que ton commercial parte avec son téléphone.

Avant de partir, il synchronise :

```text
Voyages disponibles
Tickets disponibles
Tarifs
Sièges
etc.
```

Puis il perd Internet.

Il peut quand même vendre :

```text
Ticket T001
Ticket T002
Ticket T003
```

Ces ventes sont enregistrées **localement sur le téléphone**.

Par exemple :

```text
Mobile

Vente T001   SYNC = NON
Vente T002   SYNC = NON
Vente T003   SYNC = NON
```

---

# 5. La synchronisation

Lorsqu'Internet revient :

```text
Téléphone
    ↓
Synchronisation
    ↓
Backend
```

Le serveur reçoit les ventes.

Mais il faut surtout éviter de créer deux fois le même ticket.

C'est pourquoi chaque vente hors-ligne devrait avoir un **identifiant unique généré côté mobile**, par exemple :

```text
offline_transaction_id
```

Le serveur peut ainsi dire :

> Cette vente existe déjà, je ne la recrée pas.

---

# 6. Et la clôture ?

C'est ici que je te conseille de séparer **clôture commerciale** et **synchronisation technique**.

Une caisse ne devrait pas être considérée comme correctement clôturée simplement parce que l'application mobile a envoyé une requête.

Je verrais plutôt :

```text
OUVERTE
   ↓
EN_COURS
   ↓
CLOTURE_DEMANDEE
   ↓
SYNCHRONISATION
   ↓
CLOTUREE
```

Par exemple, le commercial demande la clôture à 18h.

L'application vérifie :

```text
Ventes locales non synchronisées : 0
```

Alors :

> ✅ Toutes les ventes ont été synchronisées.
> Vous pouvez clôturer votre caisse.

---

# 7. Mais que faire si Internet est absent au moment de clôturer ?

C'est un cas très important.

Supposons :

```text
18h00

Internet ❌

3 ventes non synchronisées
```

Je **n'autoriserais pas une clôture serveur définitive**.

Le téléphone peut toutefois enregistrer :

> **Clôture demandée hors-ligne**

avec :

```text
date/heure
identifiant de caisse
montant théorique
montant déclaré
nombre de ventes
```

Puis, lorsque la connexion revient :

```text
Synchronisation
       ↓
Serveur
       ↓
Contrôle
       ↓
Clôture définitive
```

Cela évite de considérer comme définitivement clôturée une caisse dont le serveur ne connaît pas encore toutes les ventes.

---

# 8. Très important : la caisse et la recette ne sont pas la même chose

C'est particulièrement important dans **ton modèle**.

Tu as :

```text
Recette gare
Recette commercial
Recette voyage
Recette entreprise
```

La caisse répond plutôt à :

> **Combien cette caisse devait-elle encaisser et combien a-t-elle réellement encaissé ?**

Alors que la recette répond à :

> **Combien l'activité a-t-elle généré ?**

Par exemple, un commercial rattaché à Bouaké vend :

```text
Ticket : 10 000 FCFA
```

La vente appartient à son activité commerciale et doit alimenter les statistiques de la gare selon la règle métier que tu as définie.

Mais la **caisse du commercial** sert à contrôler l'encaissement.

---

# 9. Je structurerais donc ton système ainsi

```text
                    ENTREPRISE
                         │
              ┌──────────┴──────────┐
              │                     │
            GARE                COMMERCIAL
              │                     │
            CAISSE                CAISSE
              │                     │
              └──────────┬──────────┘
                         │
                       VENTES
                         │
              ┌──────────┼──────────┐
              │          │          │
            Ticket     Bagage    Courrier
```

Et chaque vente possède notamment :

```text
Vente
-----
id
reference
caisse
agent
gare
commercial (nullable)
voyage
montant
modePaiement
dateVente
source
createdAt
```

Avec par exemple :

```text
source = ONLINE
source = OFFLINE
```

---

# 10. Pour le mobile hors-ligne, j'ajouterais une notion essentielle

Il faut distinguer :

**créé localement** :

```text
LOCAL
```

**envoyé au serveur** :

```text
SYNC_EN_COURS
```

**confirmé par le serveur** :

```text
SYNCHRONISE
```

**erreur de synchronisation** :

```text
ERREUR_SYNC
```

Ainsi le commercial peut voir clairement :

```text
Aujourd'hui

47 ventes

45 synchronisées
2 en attente

⚠️ 2 ventes seront synchronisées dès
que la connexion sera disponible.
```

---

## Le point le plus important pour ton projet

Je te recommande de ne **pas concevoir la vente hors-ligne comme "le même formulaire mais sans Internet"**.

Il faut la considérer comme un véritable **système de synchronisation** :

```text
              MOBILE
                 │
       ┌─────────┴─────────┐
       │                   │
    Base locale          Internet
       │                   │
       └───────→ Sync ←────┘
                    │
                 BACKEND
                    │
                 POSTGRES
```

Et la **clôture de caisse doit être la photographie comptable d'une session de caisse**, tandis que la synchronisation garantit que cette photographie est connue du serveur.

Pour ton application, je prévoirais donc dès le départ : **session de caisse → opérations de caisse → ventes → journal hors-ligne → synchronisation idempotente → contrôle des écarts → clôture définitive**. C'est beaucoup plus solide que d'ajouter le hors-ligne après coup.











Si je fais abstraction de tes préférences et que je me place dans le contexte d'un **éditeur de logiciel (SaaS) pour compagnies de transport** qui vise plusieurs centaines de compagnies et des milliers de voyageurs, voici la stack que je choisirais aujourd'hui.

## Backend

* **Java 21**
* **Spring Boot 3**
* Spring Security
* Spring Data JPA
* Spring WebFlux (si besoin de traitements réactifs)
* Hibernate

Pourquoi ?

* C'est le standard des grandes entreprises.
* Excellentes performances.
* Très robuste.
* Énorme écosystème.
* Très adapté aux applications critiques.

---

## Base de données

* **PostgreSQL**

Pourquoi ?

* Très fiable.
* Transactions ACID.
* Excellent pour les fortes charges.
* Support GIS avec PostGIS.
* Très utilisé dans le transport.

---

## Cache

* Redis

Pour :

* sessions
* cache
* disponibilité des sièges
* notifications

---

## Messaging

* Apache Kafka

Pour gérer :

* Ticket vendu
* Réservation créée
* Paiement reçu
* Position GPS
* Notification

Chaque événement est diffusé aux autres services.

---

## Temps réel

* WebSocket
* Kafka

Le GPS des cars est envoyé en temps réel.

Les tableaux de bord se mettent à jour automatiquement.

---

## GPS

* PostGIS
* OpenStreetMap
* Google Maps (si le budget le permet)

---

## Frontend Web

* React
* Next.js
* TypeScript

Aujourd'hui, c'est probablement la combinaison la plus utilisée.

---

## Mobile

* Flutter

Pourquoi ?

* Android
* iOS
* Web (si besoin)

Une seule base de code.

---

## Authentification

* OAuth2
* OpenID Connect
* JWT

---

## Recherche

* Elasticsearch

Pour rechercher rapidement :

* voyageurs
* tickets
* voyages
* bagages

---

## Stockage

* S3 (ou compatible S3 comme MinIO)

Pour :

* tickets PDF
* pièces jointes
* images

---

## Monitoring

* Prometheus
* Grafana

Pour surveiller :

* CPU
* mémoire
* temps de réponse
* nombre de voyages
* erreurs

---

## Logs

* Loki
* Grafana

ou

* ELK Stack

---

## Conteneurisation

* Docker

---

## Orchestration

* Kubernetes

---

## CI/CD

* GitHub Actions

ou

* GitLab CI

---

# Si l'application devient très grosse

Je passerais progressivement en microservices.

```text
API Gateway

├── Auth Service
├── Billetterie Service
├── Réservation Service
├── Exploitation Service
├── GPS Tracking Service
├── Paiement Service
├── Notification Service
├── Stock Service
├── Reporting Service
```

Chaque service possède sa propre base de données.

Ils communiquent via Kafka.

---

# Pourquoi Java plutôt que Node, Go ou .NET ?

Pour un logiciel de transport, les critères sont :

* disponibilité 24h/24 ;
* transactions fiables ;
* concurrence importante ;
* maintenance sur plusieurs années.

Dans ce contexte :

* **Java/Spring Boot** est un choix très répandu chez les grands éditeurs de logiciels métiers.
* **.NET** serait également un excellent choix, notamment dans les entreprises fortement orientées Microsoft.
* **Go** est remarquable pour les services très performants (par exemple un service de suivi GPS), mais son écosystème métier est moins riche.
* **Node.js** est très performant pour les applications temps réel, mais beaucoup d'éditeurs privilégient Java ou .NET pour les traitements métiers complexes.

---

# La stack que j'utiliserais pour un SaaS de niveau entreprise

| Couche           | Technologie                  |
| ---------------- | ---------------------------- |
| Backend          | Java 21 + Spring Boot        |
| Base de données  | PostgreSQL + PostGIS         |
| Cache            | Redis                        |
| Temps réel       | WebSocket                    |
| Bus d'événements | Apache Kafka                 |
| Frontend         | Next.js + React + TypeScript |
| Mobile           | Flutter                      |
| Cartographie     | OpenStreetMap ou Google Maps |
| Recherche        | Elasticsearch                |
| Stockage         | S3 / MinIO                   |
| Monitoring       | Prometheus + Grafana         |
| Logs             | Loki ou ELK                  |
| Conteneurisation | Docker                       |
| Orchestration    | Kubernetes                   |
| CI/CD            | GitHub Actions ou GitLab CI  |

C'est une architecture que l'on retrouve, avec des variantes, dans de nombreuses plateformes professionnelles de logistique, de transport et de mobilité. Elle est conçue pour être scalable, résiliente et maintenable sur le long terme.













Comment pouvons-nous concevoir le système afin que chaque entreprise puisse activer ou désactiver certaines fonctionnalités selon ses besoins ?

Par exemple, une entreprise peut souhaiter utiliser le module de **réservation**, tandis qu'une autre préfère ne pas l'utiliser. Le même principe pourrait s'appliquer à d'autres modules ou fonctionnalités de l'application (courriers, carte de fidélité, gestion des dépenses, etc.).

Je souhaite mettre en place une architecture flexible permettant à chaque entreprise de configurer les modules qu'elle souhaite utiliser, sans impacter le fonctionnement des autres entreprises.






C'est exactement le problème que résolvent les **Feature Flags** (ou **Feature Toggles**) dans les applications SaaS professionnelles.

Vu que ton application est **multi-entreprises**, je ne rendrais pas les fonctionnalités optionnelles par du code (`if` partout), mais par de la **configuration**.

---

# Option 1 : Modules par entreprise (celle que je recommande)

Créer une table :

```text
Entreprise
------------
id
nom
...
```

```text
Module
------------
id
code
libelle

RESERVATION
COURRIER
FIDELITE
DEPENSE
BAGAGE
GPS
STATISTIQUE_AVANCEE
...
```

Puis :

```text
EntrepriseModule
----------------
id
entreprise_id
module_id
active
dateActivation
dateDesactivation
```

Exemple :

| Entreprise | Module      | Actif |
| ---------- | ----------- | ----- |
| UTB        | Réservation | ✅     |
| UTB        | Courrier    | ❌     |
| STC        | Réservation | ❌     |
| STC        | Courrier    | ✅     |

---

## Ensuite

Avant d'afficher une fonctionnalité :

```php
if ($moduleService->isEnabled($entreprise, 'RESERVATION')) {
    ...
}
```

ou

```php
#[IsModuleEnabled('RESERVATION')]
```

si tu fais un attribut Symfony personnalisé.

---

# Option 2 : Les packs

Comme le font beaucoup de SaaS.

```text
Pack Starter

✔ Billetterie
✔ Voyages
✔ Personnel
```

---

```text
Pack Business

✔ Réservation
✔ Fidélité
✔ Courrier
```

---

```text
Pack Premium

✔ GPS
✔ Temps réel
✔ Reporting avancé
```

L'entreprise choisit son abonnement.

Le système active automatiquement les modules.

---

# Option 3 : Les paramètres

Pour les petites options.

Exemple :

```text
EntrepriseSetting

reservation.enabled=true

courrier.enabled=false

fidelite.enabled=true

showCourierInRevenue=false

seatSelection.enabled=true
```

C'est parfait pour les comportements, mais pas pour des modules complets.

---

# Ce que je ferais

Je mixerais les deux.

## Niveau 1

Des modules.

```text
Réservation

Fidélité

Courrier

GPS

Dépenses

Stock
```

---

## Niveau 2

Des paramètres.

Exemple :

```text
Réservation

Délai expiration

30 min

Autoriser paiement partiel

Oui

Bloquer automatiquement les places

Non

Pénalité

500 FCFA
```

---

# L'architecture

```text
Entreprise
        │
        │
        ▼
EntrepriseModule
        │
        ▼
Reservation
Courrier
Fidélité
GPS
...
```

Toutes les couches utilisent ensuite un **ModuleService**.

```
Controller

↓

ModuleService

↓

EntrepriseModule
```

Le contrôleur ne connaît pas la base.

Il demande simplement :

```php
$isReservationEnabled = $moduleService->isEnabled(
    $entreprise,
    Module::RESERVATION
);
```

---

# Les avantages

* Aucun code spécifique à une entreprise.
* Tu peux ajouter un nouveau module sans modifier l'architecture.
* Tu peux créer plusieurs offres commerciales (Starter, Business, Premium).
* Tu peux activer un module pour une entreprise sans redéployer l'application.
* Les tests sont plus simples.
* L'évolution est plus facile.

## Ma recommandation

Je mettrais en place **deux niveaux de configuration** :

1. **Les modules**, qui permettent d'activer ou de désactiver de grandes fonctionnalités (Réservation, Courrier, Fidélité, GPS, Dépenses, etc.).
2. **Les paramètres de module**, qui permettent de personnaliser le comportement de chaque fonctionnalité (durée d'expiration d'une réservation, inclusion des courriers dans le chiffre d'affaires, blocage automatique des places, etc.).

Cette approche est celle que l'on retrouve dans de nombreux ERP et SaaS professionnels (ERPNext, Odoo, Microsoft Dynamics, SAP, etc.). Elle est évolutive, propre et parfaitement adaptée à ton application multi-entreprises.













Le **temps réel** est une notion qui est souvent mal comprise. Beaucoup pensent que cela signifie "rafraîchir la page toutes les secondes", alors qu'en réalité, le principe est différent.

---

# Le principe du temps réel

Dans une application classique, c'est le **client** qui demande les informations.

```text
Navigateur
      │
      │ "Y a-t-il du nouveau ?"
      ▼
Serveur
      │
      ▼
Réponse
```

Par exemple :

* tu ouvres la liste des tickets ;
* tu appuies sur F5 ;
* le navigateur redemande les données.

C'est le modèle **Request / Response**.

---

## En temps réel

C'est l'inverse.

Le navigateur ouvre une connexion qui reste active.

```text
Navigateur
      ▲
      │
Connexion ouverte
      │
      ▼
Serveur
```

Lorsque quelque chose se produit :

```text
Ticket vendu

↓

Serveur

↓

Le serveur pousse l'information

↓

Tous les navigateurs concernés sont mis à jour.
```

Le client n'a rien demandé.

---

# Exemple dans ton application

Imagine trois postes.

```text
Guichet Abidjan

Commercial

Administrateur
```

Le commercial vend un ticket.

Sans temps réel :

* le guichet ne voit rien ;
* l'administrateur ne voit rien.

Ils doivent actualiser.

---

Avec le temps réel :

```text
Commercial

↓

Ticket vendu

↓

Serveur

↓

Notification

↓

Guichet

↓

Administrateur
```

Tout se met à jour automatiquement.

---

# Le GPS

Même principe.

Le téléphone Flutter envoie :

```text
Latitude

Longitude

Vitesse
```

Toutes les 15 secondes.

Le serveur reçoit.

Puis :

```text
Serveur

↓

WebSocket

↓

Toutes les cartes affichent
la nouvelle position.
```

---

# Les technologies

Il existe plusieurs solutions.

## Polling

Toutes les 5 secondes :

```text
Client

↓

As-tu du nouveau ?

↓

Serveur

↓

Non
```

Puis encore :

```text
Client

↓

As-tu du nouveau ?

↓

Serveur

↓

Non
```

Puis :

```text
Client

↓

As-tu du nouveau ?

↓

Serveur

↓

Oui
```

Simple.

Mais très gourmand.

---

## Long Polling

Le serveur garde la requête ouverte.

Moins de trafic.

---

## Server Sent Events (SSE)

Le serveur envoie uniquement des messages.

Symfony utilise **Mercure**.

Très pratique.

---

## WebSocket ⭐⭐⭐⭐⭐

Le navigateur ouvre une connexion.

Elle reste ouverte.

Les deux côtés peuvent envoyer des données.

```text
Navigateur
⇅
Serveur
```

C'est la solution la plus complète.

---

# Est-ce que cela fonctionne sur un hébergement mutualisé ?

La réponse est : **ça dépend de la technologie utilisée.**

## Polling

✅ Oui.

Tous les hébergeurs le permettent.

Mais ce n'est pas du vrai temps réel.

---

## Mercure

En général :

❌ Non sur un mutualisé classique.

Pourquoi ?

Mercure nécessite un serveur dédié (ou un service managé) qui garde des connexions ouvertes en permanence. Les hébergements mutualisés autorisent rarement ce type de processus.

---

## WebSocket

Là encore :

❌ Généralement non.

Les WebSockets demandent un serveur qui reste actif en permanence, ce qui est incompatible avec la plupart des offres mutualisées.

---

# Pourquoi ?

Sur un mutualisé :

```text
Apache

↓

PHP démarre

↓

Traite la requête

↓

PHP s'arrête
```

Il n'y a aucun processus qui reste vivant.

---

En WebSocket :

```text
Serveur

↓

Reste vivant

24h/24

↓

Écoute les connexions
```

Il faut donc un VPS, un serveur dédié ou un service spécialisé.

---

# Dans ton cas

Tu m'avais indiqué que tu utilises actuellement un **hébergement mutualisé**.

Je procéderais ainsi :

### Phase 1

Application classique.

À chaque action importante :

* vente de ticket ;
* réservation ;
* bagage ;

on fait un rafraîchissement intelligent.

Par exemple avec **React Query** ou **TanStack Query** :

* toutes les 10 secondes ;
* ou au focus de la fenêtre.

Le coût reste faible.

---

### Phase 2

Quand ton SaaS commencera à avoir plusieurs compagnies clientes et un besoin de suivi en direct (GPS, tableau d'exploitation, notifications instantanées), tu migreras vers un **VPS** ou un cloud.

À ce moment-là, tu pourras activer :

* WebSockets ;
* Mercure ;
* Redis ;
* éventuellement Kafka si la plateforme prend beaucoup d'ampleur.

---

## Ma recommandation

Pour ton application actuelle, **je ne chercherais pas à implémenter un véritable temps réel tant que tu restes sur un hébergement mutualisé**. Le coût et les contraintes techniques ne sont pas justifiés.

En revanche, je **concevrais dès maintenant l'architecture** (événements métier, services, séparation des responsabilités) de manière à pouvoir remplacer plus tard un simple polling par du WebSocket ou Mercure sans devoir réécrire toute l'application. Ainsi, tu pourras évoluer vers un véritable temps réel lorsque l'infrastructure le permettra.













Arrange moi ce prompt : Je viens d'installer un projet Next dans le dossier pour le frontend et consommer le backend notre l'application, je veux que tu utilise les meilleurs prtaique Next et on vas aller étape par étape(ou module par module), aussi l'objectif est de rendre l'expérience utilisateur meilleur dans cette nouvelle version, aussi je veux qu'on avance progressivement pour que je comprendre 

J'utilise Shadcn comme librairie de composants et j'ai installer quelques composants et tu peux en rajouter, pour le dashbord j'ai installer "npx shadcn@latest add sidebar-07" et pour le login j'ai installé le "npx shadcn@latest add login-04"



Je viens d'installer un projet **Next.js** qui servira de frontend pour notre application et consommera les API de notre backend Symfony.

Je souhaite que nous développions cette nouvelle version en appliquant les **bonnes pratiques de Next.js** ainsi que celles de l'écosystème React (architecture, organisation du projet, composants réutilisables, gestion des appels API, gestion d'état, performances, sécurité, accessibilité, etc.).

Nous avancerons **progressivement**, module par module, afin que je puisse comprendre les choix techniques et apprendre au fur et à mesure. L'objectif n'est pas seulement d'obtenir un résultat fonctionnel, mais également de construire une application propre, évolutive et maintenable.

L'un des principaux objectifs de cette refonte est d'améliorer significativement l'expérience utilisateur (UX) et l'interface (UI) par rapport à la version actuelle, tout en conservant une architecture robuste et cohérente.

Pour l'interface, j'utilise **shadcn/ui** comme bibliothèque de composants. J'ai déjà installé les composants suivants :

* **Dashboard** : `sidebar-07`
* **Authentification** : `login-04`

Tu peux bien entendu installer et utiliser d'autres composants **shadcn/ui** si cela est pertinent, à condition que leur utilisation reste cohérente avec l'architecture de l'application et contribue à améliorer l'expérience utilisateur.

Je souhaite également que nous respections une architecture claire dès le départ (gestion des routes, layouts, composants, hooks, services API, gestion des erreurs, authentification, protection des routes, gestion du cache et des requêtes, etc.), afin d'obtenir un frontend moderne, performant et facile à faire évoluer.


















# Documents

## Le constat qui domine tout le reste

**Il n'existe aucune clôture de caisse.** L'application calcule la recette au franc près — `RecetteGareService` croise gare et agent, le bordereau l'imprime — mais rien ne rapproche ce chiffre de l'argent réellement remis. Aucune table, aucune entité, aucun écran. Pour une compagnie où presque tout se paie en espèces au guichet, c'est le manque le plus coûteux : un écart ne se détecte qu'a posteriori, sans preuve opposable. C'est la seule proposition que je classe **critique**.

Quatre autres manques vérifiés en base ou dans les sources :

- **Aucun envoi asynchrone** — `MailerService::send()` appelle le mailer dans la requête. Même défaut de conception que celui qu'on vient de corriger sur le journal : un service accessoire qui décide du sort du geste principal.
- **Aucun SMS**, alors que le téléphone est déjà la clé d'identité client dans `ClientResolver`.
- **`/forgot` et `/reset` sans limite de débit**, quand la connexion, elle, est protégée. C'est cette asymétrie qui signale l'oubli — et c'est le meilleur rapport valeur/effort de la liste.
- **La recherche globale ne cherche rien** : 13 lignes qui placent le curseur. Le champ et le raccourci `Ctrl+K` existent, la promesse n'est pas tenue.

## Deux dépendances qui structurent l'ordre

**La vente hors-ligne (B3) ne doit pas précéder la caisse (A1).** Réconcilier des ventes faites hors connexion sans clôture pour les encadrer serait ingérable. C'est aussi la proposition la plus lourde de la liste — un projet, pas une évolution — et je la présente comme telle, avec le quota de sièges par gare comme seul mécanisme qui la rende sûre.

**La tarification dynamique (E1) suppose le contrôle d'embarquement (B2).** Le surbooking est déjà assumé par le modèle, mais personne ne connaît le taux de présentation réel : le QR est généré côté frontend pour l'impression seulement, sans point de lecture ni état d'embarquement sur le billet. Sans cette donnée, toute politique de remplissage se pilote à l'aveugle.

Une réserve sur B2 : le QR encode aujourd'hui le code du billet **en clair**. Il faudra le signer, sinon le contrôle validera n'importe quel code fabriqué.
- 









Le prix ne vient jamais de l'appareil. Le téléphone calcule pour encaisser, le serveur recalcule depuis la grille. L'écart est consigné, pas appliqué : le contrôle anti-fraude reste entier. --- Faire attention à la remise
Billet vendu 9 500 à bord  admin change tarif 	enregistré à 8 000 (grille), écart de 1 500 tracé au journal :: !!

Trouvé au passage : le lot dispensait des permissions. Être le commercial du voyage est un bornage, pas un droit — un vendeur privé de CREER/Ticket l'obtenait en passant hors ligne. VENTE et BAGAGE sont désormais contrôlés opération par opération.

- Faut t'il borner la vente hors ligne, pour limiter la casse si un appareil reste déconnecté longtemps ?
    - Borné au voyage en cours (r) : Le mode hors ligne n'est armé que pour un voyage dont l'instantanéa été téléchargé, et se ferme à sa cloture. Bornage naturel, aligné sur le métier : un commercial est à bord d'un car, pas dans l'absolu => Oui
    - Borner aussi en nombre de billets : En plus du voyage, un plafond de billets non syncronisés au délà du quel l'application refuse de vendre. Protection plus forte, mais elle peut bloquer un vendeur légitime en pleine brousse - exactement au moment ou il en a besoin
    - Aucune borne : L'application vend hors ligne tant que l'utilisateur est connecté à son compte, sans limite de durée ni de volume

- Deux vendeurs peuvent visé le même siège pendant que le commercial est hors ligne (lui à bord, une gare en aval qui ignore sa vente). Comment veux tu traiter ce conflit ?
    - Optimiste + éviction existante (r) : Le commercial vend librement hors ligne, à la syncronisation , un siège contesté est arbitré par la doctrine déjà en place - priorité au passager monté en amont, l'autre passe << evincé >> avec son alerte. Aucun siège gélé, rien de neuf à inventer, mais un client peut évincé après coup => Oui
    - Quota de siège réservé au bord : Avant le départ, N sièges sont réservés à la vente à bord et deviennent invendables par les gares. Collision impossible par construction. Coût : des sièges gelés qui partent parfois vides, et une décision de quota à prendre à chaque départ
    - Les deux : quota, et évincion en dernier recours : Le quota couvre le cas normal, l'éviction rattrape les débordements si le commercial vend au-dlelà. Le plus robuste, le plus long à construire



Excellent choix — c'est *le* vrai enjeu métier : le commercial vend précisément là où il n'y a pas de réseau. Mais la vente hors-ligne n'est pas un simple « cache local » : une vente déclenche côté serveur l'attribution du siège, le calcul du tarif et la génération du code billet. Avant de concevoir quoi que ce soit, je dois savoir **ce que le backend calcule au moment du `POST /api/tickets`** — sinon je conçois dans le vide.

Je vais d'abord investiguer le backend réel (mémoire : *vérifier sur les données réelles*, *README backend = source de vérité métier*), puis te proposer un design avec les arbitrages à trancher.

Le TicketProcessor est le cœur. Je le lis, ainsi que l'entité Ticket et le README backend (docs modules).

J'ai le cœur du problème. Le `POST /api/tickets` est **fondamentalement synchrone et contextuel** — plusieurs choses y sont calculées/validées côté serveur au moment de l'appel, et c'est ça qui rend le hors-ligne non trivial. Je vérifie encore deux points sur l'entité `Ticket` (existe-t-il déjà une clé d'idempotence, une date de vente distincte ?) avant de te proposer l'architecture.

J'ai tout ce qu'il me faut pour poser le diagnostic. Le point dur est clair, et cette fonctionnalité est assez lourde (elle touche le métier backend, y compris une migration) pour mériter un plan validé avant que j'écrive une ligne. Je charge les outils de planification.

Le plan existant couvre le socle de l'app — je le réécrirai pour cette nouvelle phase (vente hors-ligne). J'ai déjà exploré le backend (TicketProcessor, entité Ticket). Je relis les deux fichiers Flutter du tunnel de vente actuel pour savoir précisément où se greffe le hors-ligne.

J'ai maintenant la vue complète, côté backend **et** côté app. Le point de greffe Flutter est clair (`confirmer()` et `_loadSieges()` dans le VenteController).

Récapitulatif du diagnostic — pourquoi la vente hors-ligne n'est pas un simple « cache local ». Au `POST /api/tickets`, le serveur (TicketProcessor) fait, **au moment de l'appel**, des choses qu'on ne peut pas rejouer telles quelles en différé :

| Obstacle | Détail |
|---|---|
| **Garde de position** | Le commercial ne peut vendre que depuis `garecourante`. Or hors-ligne, la resync arrive *après* que le car a avancé → rejet « vendez depuis la position actuelle ». |
| **Prix serveur** | Le prix est recalculé depuis la grille au POST, pas envoyé par l'app → le montant du reçu remis n'est pas garanti. |
| **Code billet serveur** | `TCK-YYYY-N` généré à la persistance → pas de code officiel hors-ligne. |
| **Siège / capacité sous verrou** | Deux ventes peuvent viser le même siège → collision à la resync. |
| **Pas de clé d'idempotence** | Aucun champ sur `Ticket` → risque de doublon si la resync est ambiguë. |
| **Remise / fidélité** | Validations serveur (plafond, éligibilité) non reproductibles hors-ligne. |

Conclusion : la feature exige un **traitement backend dédié aux ventes différées** (+ une migration). Avant de rédiger le plan, trois arbitrages que je ne peux pas trancher à ta place.




Une vente hors-ligne propre exige un traitement backend dédié (endpoint de resync idempotent, assouplissement de la garde de position, code billet renvoyé, migration d'un champ UUID). Comment procède-t-on ?

On vas y revenir plus tard, pour l'instant on vas laisser comme ça

Que peut faire le commercial HORS-LIGNE ?

On vas y revenir plus tard, pour l'instant on vas laisser comme ça

À la synchronisation, comment traiter le prix et les collisions de siège ?

On vas y revenir plus tard, pour l'instant on vas laisser comme ça




Compris — on **laisse en l'état**, je ne touche à rien. La vente reste en ligne (synchrone) comme aujourd'hui.

L'investigation n'aura pas été inutile : elle a produit un diagnostic clair. Je le consigne dans le fichier de plan pour qu'on reparte de là le jour où tu voudras rouvrir le sujet (c'est le seul fichier que je peux écrire en mode plan — je ne modifie aucun code).

C'est noté et rien n'a été touché — la vente reste en ligne comme aujourd'hui.

**Ce qu'on retient pour plus tard** (consigné dans le fichier de plan, pour repartir de là) :
- Le vrai blocage, c'est que le `POST /api/tickets` valide contre l'état **live** du voyage — surtout la **garde de position** (le commercial ne vend que depuis la position courante du car). Une vente rejouée après que le car a avancé est refusée. C'est pour ça qu'une vraie vente hors-ligne **exige du backend** (endpoint de resync idempotent + migration d'un champ UUID), pas seulement du Flutter.
- Trois arbitrages restent ouverts pour quand tu voudras rouvrir : **backend d'un bloc ou socle Flutter d'abord**, **périmètre dégradé ou complet**, **resync snapshot/surbooking ou strict**.

Je ne lance pas d'implémentation et je ne te propose pas de plan à approuver, puisque tu préfères laisser en l'état. 

Pour info, on est encore en **mode plan** — tu peux en sortir quand tu veux. Le socle + la vente en ligne + « ma recette » sont livrés et verts (`flutter analyze` propre, tests OK). Dis-moi vers quoi tu veux aller ensuite.


CommercialInstantaneController
CommercialSyncController
AvanceePositionService
DepartGareService
SynchronisationHorsLigneService
VenteHorsLigneTest



## Plan

# Vente hors ligne pour le commercial à bord

## Contexte

Le commercial vend depuis le car, souvent hors couverture réseau. Aujourd'hui l'application
`commercialflutter` est un client d'API pur : **sans réseau, il ne vend plus**. Aucune persistance
locale n'existe (seuls les jetons JWT sont stockés), aucune détection de connectivité, aucune file
d'attente. Le `README.md:91` de l'app le note comme reporté : « vente hors-ligne (file d'attente +
resync) ».

L'objectif : que le commercial vende, imprime, enregistre un bagage, fasse avancer la position du car
et consulte son manifeste **sans réseau**, puis que tout remonte à la reconnexion.

### Décisions arrêtées

| Sujet | Décision |
|---|---|
| Conflit de siège | **Optimiste** — la vente est acceptée, la doctrine d'éviction existante (`CapaciteService::billetsEvinces`, priorité amont) arbitre qui monte |
| Périmètre hors ligne | Vente + impression, avance de position, bagage, manifeste (lecture) |
| Prix | **Le serveur recalcule** depuis la grille ; un écart avec le montant encaissé est tracé au journal |
| Bornage | Armé **pour un voyage** dont l'instantané a été téléchargé ; se ferme à sa clôture |

---

## Le verrou principal : le `codeticket`

`TicketProcessor::generateCode()` (`src/State/TicketProcessor.php:350`) produit
`{codevoyage}-TCK-{YYYY}-{COUNT(*)+1}`, et **il n'existe aucun index unique sur `codeticket`**
(vérifié : seule la clé primaire sur `id`). Deux appareils émettraient le même code **sans aucune
erreur** — duplication silencieuse, pas rejet. Or le reçu PDF imprimé hors ligne encode ce code en QR
(`lib/core/pdf/recu_pdf.dart:178`) : il doit être définitif dès l'impression.

**Solution — une série dédiée au bord.** Un voyage n'a **qu'un seul commercial** (`Voyage::commercial`
est un `ManyToOne`). Sa série ne peut donc entrer en collision ni avec le guichet, ni avec un autre
appareil :

```
guichet   : LI-ABI-KOR-0004-V37-TCK-2026-8
hors ligne: LI-ABI-KOR-0004-V37-TCK-2026-B3      ← compteur local du commercial, préfixe « B »
```

Le téléphone génère le code **définitif**, le serveur l'accepte tel quel à la synchronisation.

Au passage, **l'index unique manquant est un défaut latent qui existe déjà** : `generateCode` compte
les billets non supprimés, donc un `/tickets/{id}/remove` fait réutiliser un code déjà émis. Base
actuelle saine (261 billets, 261 codes distincts) — la migration passera. **Vérifier le même point sur
la base de production avant de l'appliquer.**

---

## Backend

### 1 · Modèle et migration

`src/Entity/Ticket.php` :
- `$referenceOffline` (`string|null`, unique) — UUID généré par le téléphone, **clé d'idempotence**.
  Même rôle que `Alerte::$cle` (`src/Entity/Alerte.php:101`, « garantit l'idempotence du balayeur »).
  Rejouer un lot ne duplique rien.
- `$montantEncaisse` (`int|null`) — ce que le commercial a réellement perçu hors ligne. Sert
  uniquement à détecter l'écart ; le `prix` reste celui de la grille.

Migration : index unique sur `codeticket`, index unique sur `reference_offline`. Les deux colonnes
sont nullables — une vente en ligne ne porte pas de référence.

### 2 · Mode « vente différée » dans `TicketProcessor`

Ne **pas** dupliquer la logique de vente : c'est le chemin le plus chargé en règles (huit gardes) et
le mieux testé. Extraire le cœur transactionnel (`src/State/TicketProcessor.php:195-233`) en une
méthode publique acceptant un indicateur `differee`, qui relâche **exactement trois** contrôles :

| Contrôle | En ligne | Différé | Pourquoi |
|---|---|---|---|
| `assertSiegeLibre` (`:202`) | bloque | **ignoré** | Décision optimiste : l'éviction arbitre |
| `assertPlaceDisponible` (`:205`) | bloque | **ignoré** | Surbooking déjà assumé par le modèle |
| `generateCode` (`:208`) | génère | **reprend le code du téléphone** | Le QR est déjà imprimé |
| Position du car (`:105`, `:124`) | bloque | **ignoré** | Le commercial EST le car ; il pilote `garecourante` lui-même |

Tout le reste — cohérence du tronçon, provenance effective, grille tarifaire, plafond de remise,
rattachement client, canal commercial — reste **identique**. Le verrou `PESSIMISTIC_WRITE` sur le
voyage est **conservé** : il sérialise le rejeu du lot.

### 3 · Service de synchronisation

`src/Domain/Service/VenteHorsLigneService.php` (nouveau) — pour chaque opération du lot :

1. `referenceOffline` déjà en base → renvoie le billet existant, statut `DEJA_SYNCHRONISE`.
2. Voyage clôturé, sans car, tronçon incohérent → `REFUSE` avec motif (ce sont des erreurs de
   données, pas des conflits).
3. Sinon → émet le billet en mode différé, statut `ACCEPTE`.
4. Si `montantEncaisse !== prix` → `ActiviteLogger::TICKET_ECART_TARIF` (nouveau type), avec les deux
   montants. La gare régularise.

Le contrat de retour suit `VoyageDepartService::marquerDepart(): bool`
(`src/Domain/Service/VoyageDepartService.php:34`, « true si le départ vient d'être enregistré ») :
chaque élément dit ce qui s'est réellement passé.

### 4 · Point d'entrée

`src/Controller/Api/CommercialSyncController.php` (nouveau), `POST /api/voyages/{id}/me/sync`.

Calqué sur `CommercialVentesController` (`src/Controller/Api/CommercialVentesController.php:16-24`),
qui documente déjà pourquoi le commercial sort du pipeline API Platform : « le vendeur À BORD opère
depuis des gares qui ne sont pas sa gare d'attache — les collections filtrées par la gare de l'agent
masqueraient ses ventes en route ».

**Ce sera le premier endpoint de l'API acceptant un tableau d'éléments indépendants** — il n'en existe
aucun aujourd'hui (ni Messenger, ni import, ni lot). Le patron transactionnel à imiter est
`src/State/Corbeille/CorbeilleViderLotProcessor.php`.

Corps : `{ operations: [ {reference, type, instant, payload} ] }`, types `VENTE`, `BAGAGE`,
`POSITION`. Réponse : un résultat par référence. Traitement **en ordre d'émission**, dans une seule
transaction.

### 5 · Avance de position rejouable

`src/State/AvancerCommercialProcessor.php` : accepter un `instant` client optionnel (aujourd'hui
`new \DateTimeImmutable()` en dur, `:96`). `PassageService` est déjà idempotent — « le PREMIER
passage fait foi » (`src/Domain/Service/PassageService.php:11-20`) — et son cache par requête
(`:23`) existe précisément pour le cas « plusieurs écritures avant le flush », qu'on rencontrera en
rejouant un lot.

Le contrôle de monotonie (`:89`) rend un 400 sur un rejeu : dans le lot, le traiter comme
`DEJA_SYNCHRONISE`, pas comme une erreur.

**Cet endpoint n'a aucun test aujourd'hui** — à couvrir au passage.

### 6 · Instantané

`GET /api/voyages/{id}/me/instantane` sur `CommercialEspaceController` : tout ce dont le téléphone a
besoin pour vendre seul — voyage, ligne et arrêts ordonnés, car et sièges, position courante,
billets existants (pour le plan de sièges), grille tarifaire des couples utiles,
`ConfigRemise.maxpourcentage`, en-tête entreprise pour les reçus.

---

## Flutter (`commercialflutter`)

### 7 · Persistance locale — la première du projet

`pubspec.yaml` : ajouter `sqflite`, `path_provider` (déjà transitif via `printing`),
`connectivity_plus`, `uuid`.

`lib/core/offline/` (nouveau) :
- `offline_database.dart` — deux tables : `instantanes(voyage_id PK, payload, telecharge_le)` et
  `operations(id PK, reference UNIQUE, voyage_id, type, payload, statut, tentatives, cree_le)`.
  L'`id` entier donne la file FIFO naturelle.
- `file_operations.dart` — mise en file, relecture des opérations en attente, marquage.
- `connectivite.dart` — état réseau observable.
- `synchronisateur.dart` — vidange de la file au retour du réseau ; rejeu sûr grâce à la référence.

### 8 · Sources de données locales

Les repositories sont aujourd'hui de purs passe-plats
(`lib/features/vente/data/repositories/vente_repository_impl.dart`) : c'est **le point d'injection
naturel**. Ajouter un `*_local_datasource.dart` par feature concernée, et faire choisir au repository
entre distant et local selon la connectivité.

`lib/core/network/api_exception.dart` : exposer un booléen `estHorsLigne` — les deux branches
`connectionError` / timeout existent déjà mais ne sont distinguables que par comparaison de chaînes.

### 9 · Vente hors ligne

`lib/features/vente/presentation/providers/vente_controller.dart` : à la confirmation, si hors ligne,
écrire dans la file au lieu d'appeler l'API, générer localement le `codeticket` série `B` et le
`reference` (UUID), puis produire le reçu PDF — la génération est déjà 100 % locale
(`lib/core/pdf/recu_pdf.dart`).

Supprimer la dépendance réseau du reçu : `monEntrepriseProvider` doit lire l'instantané.

Le plan de sièges hors ligne se calcule depuis l'instantané avec **la même règle que le serveur** —
`tm <= ordreMontee && td > ordreMontee` (`src/State/SiegeStateProvider.php:146`). Le sondage 30 s
(`_syncSeatPolling`) se désactive hors ligne.

### 10 · Visibilité

Un bandeau d'état (hors ligne / N opérations en attente / synchronisé) et un écran listant les
opérations en attente avec leur sort après synchronisation. Un billet dont le siège a été évincé doit
se **voir** : c'est la contrepartie assumée du choix optimiste.

---

## Ce qui reste interdit hors ligne

Désistement (remboursement — caisse de gare), modification d'un billet, réservation, récompense
fidélité (`FideliteService` dérive l'état de tout l'historique : deux appareils brûleraient la même
récompense).

---

## Vérification

**Backend** — `tests/Api/VenteHorsLigneTest.php` (nouveau), avec `ScenarioBuilder` :
- rejouer deux fois le même lot ne crée qu'un billet (idempotence par `referenceOffline`) ;
- une vente hors ligne sur un siège déjà pris est **acceptée**, et `billetsEvinces` désigne ensuite
  le perdant selon la priorité amont ;
- un écart entre `montantEncaisse` et la grille produit la trace au journal ;
- voyage clôturé → `REFUSE`, rien n'est écrit ;
- rejeu d'une avance de position déjà enregistrée → `DEJA_SYNCHRONISE`, pas 400.

Plus le **premier test de `PATCH /voyages/{id}/avancer`**, aujourd'hui non couvert.

Lancer : `php vendor/bin/phpunit` (165 tests au vert avant travaux).

**Migration** — avant d'appliquer, vérifier l'absence de doublons :
`SELECT codeticket, COUNT(*) FROM ticket GROUP BY codeticket HAVING COUNT(*) > 1`.

**Flutter** — tests unitaires de la file (mise en file, ordre, idempotence). Pour éprouver le mode
hors ligne sans couper le Wi-Fi : lancer l'app avec `--dart-define=API_BASE_URL=http://127.0.0.1:9`
(port mort) → `connectionError`, puis rebasculer sur le vrai port et vérifier la vidange.

**Bout en bout** — démarrer les deux serveurs, armer un voyage, couper l'API, vendre trois billets et
imprimer, faire avancer la position, rallumer l'API, synchroniser, puis vérifier en base : trois
billets série `B`, la position avancée, et le manifeste cohérent.

---

## Limites assumées

- **Un client peut être évincé après coup.** C'est le prix du choix optimiste ; le mécanisme et son
  alerte existent déjà, mais le passager, lui, est physiquement à bord.
- **La trace d'activité n'est pas rétroactive.** Les remises hors ligne seront journalisées à la
  synchronisation, pas au moment de la vente.
- **L'écart de tarif se régularise à la main.** Aucun mouvement de caisse automatique.
- **La capacité peut être dépassée.** Déjà le cas aujourd'hui (« surbooking assumé »), mais le hors
  ligne en augmente la fréquence.

---

# Ce qui a été livré

Journal de réalisation tenu à jour au fil du travail. Il note surtout **ce qui a divergé du plan**,
parce que c'est là que le plan s'est trompé.

## Backend — `Backend-Transport`

| Fichier | Rôle |
|---|---|
| `src/Entity/Ticket.php` | `$referenceOffline`, `$montantEncaisse`, + 2 index uniques |
| `src/Entity/Bagage.php` | idem, + unicité `(identreprise, codebagage)` |
| `migrations/Version20260912090000.php` | billet : colonnes + unicité de `codeticket` |
| `migrations/Version20260912140000.php` | bagage : colonnes + unicité de `codebagage` |
| `src/State/TicketProcessor.php` | pipeline unique, stratégie d'écriture injectée, `emettreHorsLigne()` |
| `src/State/BagageProcessor.php` | même refonte, `enregistrerHorsLigne()` |
| `src/Domain/Service/SynchronisationHorsLigneService.php` | rejeu du lot : `VENTE`, `BAGAGE`, `POSITION`, `DEPART` |
| `src/Domain/Service/AvanceePositionService.php` | avance de position, partagée en ligne / rejeu |
| `src/Domain/Service/DepartGareService.php` | départ de gare, partagé en ligne / rejeu *(2ᵉ passe)* |
| `src/Domain/Service/PassageService.php` | `connu()` : lecture par le cache de requête *(2ᵉ passe)* |
| `src/State/AvancerCommercialProcessor.php` | délègue au service |
| `src/State/RepartirVoyageProcessor.php` | délègue au service *(2ᵉ passe)* |
| `src/Controller/Api/CommercialSyncController.php` | `POST /api/voyages/{id}/me/sync` |
| `src/Controller/Api/CommercialInstantaneController.php` | `GET /api/voyages/{id}/me/instantane` |
| `src/Domain/Service/ActiviteLogger.php` | 4 types : `TICKET_HORS_LIGNE`, `TICKET_ECART_TARIF`, `BAGAGE_HORS_LIGNE`, `BAGAGE_ECART_TARIF` |
| `tests/Api/VenteHorsLigneTest.php` | 24 tests |
| `tests/Support/ScenarioBuilder.php` | `tarifbagage()` |

## Flutter — `commercialflutter`

| Fichier | Rôle |
|---|---|
| `lib/core/offline/base_locale.dart` | SQLite : `instantanes`, `operations` |
| `lib/core/offline/operation_hors_ligne.dart` | l'unité de file — `VENTE`, `BAGAGE`, `POSITION`, `DEPART` |
| `lib/core/offline/file_operations.dart` | file, instantanés, cache des départs, signal de changement |
| `lib/core/offline/codes_hors_ligne.dart` | séries « B » du bord : billets et étiquettes |
| `lib/core/offline/synchronisateur.dart` | vidange, rejeu sûr, bilan |
| `lib/core/offline/connectivite.dart`, `armement.dart`, `offline_providers.dart` | câblage |
| `lib/core/widgets/bandeau_hors_ligne.dart` | ce qui n'est pas remonté, en permanence |
| `lib/features/vente/data/**` | repli sur l'instantané : sièges, tarif, vente, bagage |
| `lib/features/manifeste/data/**` | manifeste servi depuis l'instantané |
| `lib/features/gestion/data/**` | « Mes ventes » servi depuis l'instantané, corrections désactivées *(2ᵉ passe)* |
| `lib/features/voyages/data/repositories/voyage_repository_impl.dart` | cache des départs, avance et départ en file, correction locale *(2ᵉ passe)* |
| `lib/features/auth/**`, `lib/core/auth/token_store.dart` | session conservée hors ligne, profil en coffre |
| `lib/features/entreprise/**` | en-tête du reçu pris dans l'instantané |
| `lib/features/horsligne/**` | l'écran du sort des opérations |
| `android/app/src/main/AndroidManifest.xml` | permission `INTERNET` — sans elle, aucun APK release ne parle *(2ᵉ passe)* |
| `test/file_operations_test.dart`, `instantane_test.dart`, `progression_hors_ligne_test.dart`, `regles_rejouees_test.dart` | 44 tests dédiés au hors ligne |
| `lib/core/offline/vidange_automatique.dart` | la file repart seule : 4 déclencheurs *(3ᵉ passe)* |

---

## Là où le plan s'est trompé

**1 · Le bagage ne pouvait pas désigner son billet par un identifiant.**

Le plan listait `BAGAGE` parmi les types du lot sans regarder ce que `BagageProcessor` exige : un
`ticket` par son **id**. Or hors ligne, le billet auquel on rattache un bagage vient le plus souvent
d'être vendu par le même téléphone — il attend deux lignes plus haut dans la même file et n'a
strictement aucun identifiant serveur. Le seul lien qui existe à ce moment-là est le **code imprimé
sur le reçu du client**.

D'où : le payload `BAGAGE` porte `codeticket`, et le serveur résout le billet par son code. L'ordre
d'émission fait le reste — la vente est rejouée avant, donc le billet est en base quand le bagage le
cherche. Un bagage dont la vente a été refusée est refusé à son tour, **en disant pourquoi** :
« Billet … introuvable — sa vente a-t-elle été refusée ? ». Sans cette formulation, le vendeur
chercherait deux problèmes là où il n'y en a qu'un.

**2 · `codebagage` souffrait du même défaut latent que `codeticket`.**

Le plan avait identifié l'index unique manquant sur `codeticket`. Le même générateur
(`COUNT(*) + 1` sur les non-supprimés) sert les bagages, sans index non plus. Une mise en corbeille
faisait donc déjà réutiliser un code imprimé sur une étiquette remise au client.

Différence qui compte : l'unicité d'un `codebagage` est portée **par entreprise** — « BAG-2026-7 »
existe légitimement chez chaque compagnie. L'index est donc composite, et la série du bord porte le
code voyage (`LI-…-V37-BAG-2026-B2`) pour que deux commerciaux de la même compagnie roulant le même
jour n'entrent pas en collision.

**3 · Le lot dispensait des permissions.**

Le contrôleur vérifiait que l'appelant est le commercial **du voyage**. C'est un bornage, pas un
droit. Les opérations en ligne passent par `is_granted` sur l'opération API Platform ; ce lot en
sort. Un vendeur privé du droit de créer un billet l'aurait donc obtenu en passant hors ligne.
Corrigé : `VENTE` exige `CREER/Ticket`, `BAGAGE` exige `CREER/Bagage`, et le refus est rapporté
opération par opération. `POSITION` n'en demande aucune — en ligne comme ici, elle n'est gardée que
par la qualité de commercial du voyage.

**4 · Le reçu hors ligne s'imprimait anonyme.**

`monEntrepriseProvider` appelait l'API. Sans réseau, l'écran de vente retombait sur ses valeurs par
défaut et imprimait « BILLET / Compagnie de transport » : un reçu que le client ne peut rattacher à
personne, et qui ne vaut rien en cas de litige. L'instantané portait déjà l'en-tête — il suffisait de
le lire.

**5 · Le manifeste a demandé plus que « lecture ».**

Le plan le rangeait dans le périmètre sans dire ce qu'il fallait embarquer. Deux ajouts à
l'instantané : l'identité et le siège affiché de chaque billet, et surtout un booléen `evince`
**calculé par le serveur**. Un téléphone ne peut pas refaire ce calcul — l'éviction se déduit de la
capacité du car et de la priorité amont sur l'ensemble des billets. Le manifeste hors ligne écarte
donc les évincés comme le fait le serveur, et ajoute les ventes de ce téléphone encore en file : ces
passagers-là sont assis dans le car et n'existent nulle part ailleurs.

**6 · Deux extractions non prévues, pour ne pas dupliquer une règle.**

`AvanceePositionService` (partagé entre l'endpoint en ligne et le rejeu) et la refonte des deux
processors autour d'une **stratégie d'écriture injectée**. Le principe tenu partout : une règle, un
seul endroit. Le mode différé ne relâche que ce qui est énuméré dans le tableau du plan, et chaque
relâchement porte son commentaire.

---

**7 · L'essai sur appareil a trouvé ce que les tests ne pouvaient pas voir.**

Quatre défauts, tous invisibles en test unitaire parce qu'ils tiennent au CYCLE DE VIE de
l'application — pas à ses calculs. Ils sont listés ici parce qu'ils disent quelque chose du mode hors
ligne : **il ne suffit pas que les données soient sur le téléphone, il faut que l'application sache
encore s'en servir.**

- *Le plan de sièges se dessinait sur une seule ligne.* L'instantané n'embarquait que
  `id` et `numero` ; `SeatGrid` groupe par `rangee` et sépare par `cote`. Cinquante cases en file
  indienne, débordant de l'écran. `rangee`, `colonne` et `cote` sont désormais dans l'instantané.

- *Le redémarrage en route déconnectait le vendeur.* `AuthController._bootstrap()` purgeait les
  jetons sur **toute** `ApiException`, panne réseau comprise, et renvoyait à l'écran de connexion —
  où il n'y a rien à faire sans serveur. Les ventes encaissées devenaient inaccessibles. On ne purge
  plus que lorsque le SERVEUR a répondu que la session ne vaut plus rien, et le profil (permissions
  comprises) est gardé dans le coffre chiffré.

- *Le redémarrage vidait aussi la liste des départs.* `mesVoyagesProvider` était purement distant :
  écran d'accueil vide, aucun voyage ouvrable, donc pas d'écran de vente — alors que l'instantané
  était là. La dernière liste reçue est maintenant gardée en base locale.

- *Les compteurs restaient figés.* Le bandeau annonçait « rien en attente » et le journal des
  opérations s'affichait vide alors que deux ventes attendaient : les providers avaient lu la file à
  leur première construction et rien ne les réveillait. La file émet désormais un signal à chaque
  écriture.

Un cinquième, cosmétique : le journal affichait « Siège 225 » — l'identifiant, que le vendeur ne
connaît pas. Le numéro se lit maintenant dans l'instantané.

---

## Vérifications passées

- **Backend** : `php vendor/bin/phpunit` → **184 tests, 451 assertions, OK** (165 avant travaux).
- **Flutter** : `flutter test` → **40 tests OK** ; `flutter analyze` → **0 issue**.
- **Migrations** : appliquées sur la base de développement après vérification des doublons
  (261 billets / 261 codes ; 66 bagages / 66 codes, 1 compagnie). `doctrine:schema:validate` en
  phase.
- **API** : instantané (4 arrêts, 50 sièges, 6 couples tarifaires, 3 tranches de poids, plafond de
  remise, en-tête compagnie) ; lot vente + bagage + bagage orphelin → 2 acceptées, 1 refusée avec le
  bon motif ; rejeu → 0 acceptée / 2 déjà synchronisées ; écart de grille consigné.

### Bout en bout, sur émulateur Android

Parcours complet joué sur `emulator-5554`, backend sur `10.0.2.2:8000` :

| Étape | Résultat |
|---|---|
| Ouverture du voyage V34 (réseau présent) | instantané téléchargé, base locale créée |
| **API coupée** | — |
| Plan de sièges | 50 sièges, disposition du car, servis par l'instantané |
| Tarif Bouaké → Korhogo | 8 000 FCFA, grille embarquée |
| Vente siège 5 | `LI-ABI-KOR-0001-V34-TCK-2026-B1`, reçu imprimable, mention « Vendu hors ligne » |
| Bagage 18 kg | `LI-ABI-KOR-0001-V34-BAG-2026-B1`, 2 500 FCFA (tranche 11–25 kg embarquée) |
| Redémarrage de l'application | session conservée, départs servis par le cache |
| Journal des opérations | 0 remontées · **2 en attente** · 0 refusées |
| **API rétablie**, bouton de synchronisation | **2 remontées** · 0 en attente · 0 refusées |

En base après remontée : billet `…TCK-2026-B1` au siège 5, prix 8 000 (grille), commercial = le
vendeur ; bagage `…BAG-2026-B1` à 2 500 rattaché à ce billet, statut `EMBARQUE` (le voyage est
parti), `montantforce = 0` ; deux traces `TICKET_HORS_LIGNE` / `BAGAGE_HORS_LIGNE`. Aucun écart de
tarif — la grille du téléphone et celle du serveur concordaient.

Données d'essai supprimées ensuite (base revenue à 261 billets / 261 codes distincts).

---

## Reste à faire

- **Vérifier l'absence de doublons sur la base de PRODUCTION** avant d'appliquer les deux migrations
  (les deux requêtes sont dans les en-têtes de migration).

---

# Deuxième passe — ce que l'usage réel a révélé

Trois signalements après la première livraison, tous fondés. Ils partagent un trait : **le mode hors
ligne avait été construit par le milieu.** Le serveur savait recevoir, le téléphone savait stocker,
mais entre les deux il manquait tantôt l'émetteur, tantôt l'écran qui s'en sert.

## 1 · L'APK release ne pouvait joindre aucun serveur

Symptôme : « Impossible de joindre le serveur » à la connexion, quelle que soit l'adresse — ngrok
comme hébergement réel.

La permission `INTERNET` n'était déclarée que dans les manifestes `debug` et `profile` — les fichiers
du gabarit Flutter, où l'outil la pose pour **son** usage (hot reload, points d'arrêt). Le variant
`release` ne les inclut pas. L'APK publié ne pouvait donc ouvrir **aucune connexion**, et chaque
appel échouait en `connectionError`, c'est-à-dire exactement le message affiché.

Détail révélateur : `connectivity_plus` apportait `ACCESS_NETWORK_STATE` au manifeste principal.
L'application pouvait **observer** le réseau sans jamais s'en **servir** — d'où le bandeau hors ligne
qui ne se déclenchait pas davantage.

Déclarée dans `main/AndroidManifest.xml` des DEUX applications (`commercialflutter` et `resaflutter`,
qui avait le même manque). Vérifié dans l'APK produit : `INTERNET` présent, trafic en clair absent.

**Au passage, `resaflutter` ne se construisait plus du tout** : `intl` figé à `0.20.2` quand le SDK
exige `^0.20.3`, et wrapper Gradle resté en 8.9 quand l'AGP installé réclame 9.1.0. Les deux alignés
sur `commercialflutter` ; l'application se construit de nouveau.

## 2 · L'avance de position demandait la connexion

Le côté RÉCEPTION était entièrement là — type `POSITION`, service de rejeu, horodatage du téléphone,
affichage au journal. L'ÉMETTEUR ne l'était pas : `VoyageRepositoryImpl.avancer()` était resté un
passe-plat vers l'API. Le geste n'avait jamais eu la moindre chance de partir en file.

**Mettre en file ne suffisait pas.** Tout ce que le vendeur peut faire ensuite — destinations
proposées, droit de vendre, prochain arrêt — se déduit de `garecouranteId`. Sans correction locale,
il déclarerait l'arrivée à Korhogo et l'application le croirait encore à Bouaké, lui refusant les
trajets qu'il doit justement vendre à partir de là. Le voyage en cache est donc corrigé sur place
(`FileOperations::modifierVoyageEnCache`), et la prochaine lecture réussie du serveur l'écrase — le
serveur reste la référence dès qu'il est joignable.

### Le geste jumeau : « le car repart »

Ajouté dans la foulée, parce que le laisser en ligne aurait été incohérent : le vendeur aurait pu
déclarer l'arrivée à Bouaké mais pas le départ de Yamoussoukro qui la précède. Les deux horodatages
donnent le **temps d'arrêt en gare**, et c'est tout l'objet du geste.

Nouveau type d'opération `DEPART`, et `DepartGareService` extrait de `RepartirVoyageProcessor` sur le
modèle d'`AvanceePositionService` : le service porte les règles métier, l'appelant garde
l'autorisation — elle diffère selon la voie (en ligne : commercial, agent de la gare courante ou
admin ; hors ligne : le commercial du voyage, que le contrôleur a déjà établi).

### Le défaut que seul l'appareil pouvait montrer

À la synchronisation, le départ était **refusé** — « L'arrivée du car à Gare de Bouaké n'a pas encore
été enregistrée » — alors que l'arrivée venait d'être ACCEPTÉE deux lignes plus haut dans le même lot.

`PassageService` persiste **sans flusher** : sa propre docstring l'annonce (« findOneBy ne verrait pas
l'entité non encore flushée »), et c'est pour ça qu'il tient un cache de requête. Mon
`DepartGareService` interrogeait le repository en direct et passait donc à côté de ce que l'opération
précédente venait de poser. Corrigé par une lecture publique `PassageService::connu()` qui consulte le
cache avant la base.

Invisible en test isolé — le test que j'avais écrit créait le passage à la main. Un test rejoue
désormais le cas réel : arrivée puis départ dans un même lot.

## 3 · « Mes ventes » n'était pas couvert

Non, la page n'avait aucun repli. Corrigée avec une **asymétrie volontaire** :

| | Hors ligne |
|---|---|
| Lire la liste, **réimprimer** | ✔ servi par l'instantané |
| Modifier le client, descendre un passager, annuler/modifier un bagage | ✘ désactivé, avec le motif affiché |

Réimprimer est le cas qui justifie l'écran : un passager perd son reçu en route, exactement là où il
n'y a pas de couverture. Corriger, à l'inverse, touche des données que le serveur arbitre — le faire
à l'aveugle sur une copie vieillissante créerait des divergences qu'aucune synchronisation ne saurait
départager.

L'instantané embarque donc aussi `prix`, `remise`, `statut`, `dateEmission` sur chaque billet, et un
bloc `bagages` restreint au canal du commercial — le même périmètre que l'endpoint en ligne.

Un billet vendu hors ligne n'a pas d'identifiant serveur : il n'est corrigeable par personne tant que
la file n'a pas été vidée, **même réseau revenu**. La page le lit à `id == 0` et le marque « En
attente ».

---

## Vérifications de cette passe

- **Backend** : `php vendor/bin/phpunit` → **187 tests, 468 assertions, OK**.
- **Flutter** : `flutter test` → **45 tests OK** ; `flutter analyze` → 0 issue.
- **APK release** : construit et inspecté (`aapt2 dump permissions`) pour les deux applications.

### Bout en bout, sur émulateur

| Étape | Résultat |
|---|---|
| Ouverture du voyage V35 (réseau présent) | instantané armé |
| **API coupée** | — |
| « Le car est arrivé à Gare de Bouaké » | position corrigée localement, itinéraire à jour, **1 en attente** |
| « Le car repart de Gare de Bouaké » | « Départ enregistré », **2 en attente**, bouton retiré |
| `Mes ventes` | 4 billets servis par l'instantané, bandeau affiché, corrections retirées, « Réimprimer » seul offert |
| **API rétablie**, synchronisation | tout remonte |

En base : arrivée à Bouaké 23:34:27, départ 23:35:07 — les heures du téléphone, donc le temps d'arrêt
réel.

**Réserve** : lors de la reprise, un processus PHP avait survécu à l'arrêt du serveur et l'arrivée est
passée EN LIGNE. Le parcours « arrivée + départ dans le même lot » — celui qui a révélé le défaut de
cache — n'a donc pas été rejoué sur appareil ; il est couvert par le test backend écrit pour lui.

---

## Ce qui reste hors ligne interdit

Inchangé, et délibéré : désistement (remboursement — caisse de gare), modification d'un billet ou
d'un bagage, réservation, récompense de fidélité.

---

# Audit des règles rejouées

Chaque fois que le téléphone corrige son cache ou calcule hors ligne, il **rejoue une règle du
serveur**. Une règle rejouée peut diverger de son original, et la divergence ne se voit pas : les deux
codes marchent, simplement pas pareil. Le défaut du terminus était de cette famille — d'où cette
relecture systématique.

Neuf règles sont rejouées. **Cinq s'étaient écartées**, toutes dans le même sens : le téléphone était
plus PERMISSIF que le serveur. Et cette direction-là est la mauvaise, parce que l'écart se découvre
après l'encaissement.

> **Le principe qui les gouverne toutes : on refuse AVANT d'encaisser, jamais après.** Un refus avant
> la vente est un message au vendeur. Le même refus à la synchronisation, c'est un passager parti avec
> un billet qui n'existera jamais.

## Le plus grave : le plafond de remise n'était pas appliqué

`plafondRemisePourcentage` était embarqué dans l'instantané depuis le premier jour. **Personne ne le
lisait.** Pire, un commentaire du code justifiait son absence :

> « Le PLAFOND, lui, n'est pas appliqué ici : il est déjà appliqué par l'écran de vente, qui le lit
> dans l'instantané. »

C'était faux. L'écran de vente ne plafonne rien — il affiche « Le montant final est calculé au serveur
(plafond de la compagnie) » et s'en remet à lui. J'avais écrit un commentaire qui justifiait une
omission en désignant un contrôle inexistant.

Conséquence : **en ligne**, sans gravité — le serveur refuse avant qu'un billet n'existe. **Hors
ligne**, le téléphone calculait la remise sans plafond, imprimait, encaissait — et le serveur refusait
la vente à la synchronisation.

Le plafond est désormais lu et appliqué avant la mise en file, avec la même arithmétique que
`TicketProcessor` — tolérance `+ 0.001` comprise, sans laquelle un arrondi ferait refuser une remise
exactement au plafond.

## Les quatre autres

| # | Règle | Serveur | Téléphone (avant) |
|---|---|---|---|
| 2 | Remise supérieure au prix | refuse | **bornait au tarif** → le client payait 0, la vente était refusée à la synchronisation |
| 3 | Type de remise inconnu | refuse | **remise 0** → vendait, puis refus à la synchronisation |
| 4 | Bornes de tronçon illisibles | **bloque le siège** (« sécurité : ticket hors ligne → on bloque ») | ignorait le billet → **siège affiché libre** alors qu'il est occupé |
| 5 | Périmètre de « Mes ventes » | `commercial = moi` | déduit de `aBord` (= « vendu par un commercial ») |

Le n° 5 n'était pas un bug : les deux coïncident tant qu'un voyage n'a qu'un commercial. Mais un
périmètre ne doit pas reposer sur une coïncidence — l'instantané porte maintenant `aMoi`, dit par le
serveur, distinct de `aBord` qui désigne le canal et sert le manifeste.

Au passage, les refus MÉTIER du repli hors ligne portaient `estHorsLigne: true`, alors que la
docstring du drapeau dit l'inverse (« PAS un refus métier »). C'est lui qui décide si une vente part
en file : sa signification doit rester exacte.

## Les quatre règles conformes

- **Prix** — grille plate `(départ, arrivée) → montant`, identique.
- **Grille de poids des bagages** — première tranche couvrante, dernière illimitée : identique, bornes
  comprises.
- **Montant forcé d'un bagage** — les quatre cas de `BagageProcessor::resoudreMontant` se
  correspondent un à un.
- **Éviction au manifeste** — pas rejouée du tout : calculée par le serveur et embarquée (`evince`).
  Un téléphone ne peut pas la refaire, elle se déduit de la capacité du car sur l'ensemble des billets.

## Verrouillage

`test/regles_rejouees_test.dart` — 8 tests. Éprouvés en rétablissant l'ancien comportement : **4 des 5
écarts font échouer un test**, chacun le sien. Le cinquième (périmètre) est couvert par
`instantane_test.dart`.

Compteurs après cette passe : `phpunit` → **188 tests, 473 assertions** ; `flutter test` → **58
tests** ; `flutter analyze` → 0 issue.

## Ce qu'il faut retenir pour la suite

Toute nouvelle règle rejouée doit venir avec **son refus**, pas seulement son calcul. Et si un
commentaire affirme qu'un contrôle est fait ailleurs, il faut aller vérifier qu'il l'est vraiment —
c'est exactement ce qui a masqué le défaut du plafond.

---

# Troisième passe — la file vivait, mais seule

Quatre questions de l'utilisateur. Deux ont confirmé des manques, une a confirmé que tout allait
bien, et la vérification sur appareil en a déterré un cinquième — le plus grave de toute la
fonctionnalité.

## 1 · La remontée ne se faisait pas toute seule

La file ne partait qu'à trois moments : juste après avoir mis une opération en file, à l'ouverture
d'un voyage, ou sur le bouton de synchronisation. Un vendeur qui retrouve du réseau en restant sur
son écran de vente ne voyait rien remonter.

Le plus gênant : `Connectivite` annonce dans **sa propre docstring** qu'elle sert à « savoir QUAND
retenter une vidange »… et personne ne l'écoutait.

`VidangeAutomatique`, monté à la racine de l'application, avec **quatre déclencheurs** — aucun ne
suffit seul :

| Déclencheur | Ce qu'il couvre |
|---|---|
| Ouverture de la base locale | au démarrage, la première tentative part avant que SQLite soit prêt |
| Changement de connectivité | le tunnel, la zone blanche, le mode avion |
| Retour au premier plan | le vendeur rouvre son téléphone |
| Retentative toutes les 2 min tant que la file n'est pas vide | **l'antenne reste « connectée » sans débit, puis le débit revient** — aucun changement d'interface, donc aucun événement de connectivité |

Le quatrième est celui qui couvre le cas le plus fréquent en brousse, et c'est celui auquel on pense
le moins. Le sondage est borné : file vide, la tentative se solde par une lecture SQLite, aucun appel
réseau.

Le premier a été trouvé par une sonde : au démarrage, `synchronisateurProvider` est encore nul et la
tentative échouait en silence. Le notifier **surveille** désormais ce provider au lieu de le lire.

## 2 · « Ma performance » ne bougeait pas

Recette, billets, bagages, places libres : tout vient du serveur, et le serveur ignore encore la
vente. Le vendeur encaissait trois billets et voyait sa recette immobile — un compteur qui ment sur
son propre travail, et le seul retour chiffré qu'il ait de sa journée.

Corrigé par le même mécanisme que la position du car : `modifierVoyageEnCache` à l'encaissement,
`maRecette` / `mesTickets` / `placesoccupees` pour un billet, `maRecette` / `mesBagages` pour un
bagage. La recette suit le **net encaissé**, pas le tarif plein.

Les mêmes chiffres alimentent l'accueil et « Ma recette » : les trois écrans sont corrigés d'un coup.

Et après une remontée, `mesVoyagesProvider` est invalidé — par la vidange automatique comme par le
bouton manuel : les chiffres du serveur remplacent les corrections locales.

## 3 · L'état des sièges hors ligne — conforme

Vérifié sur appareil avec six billets réels. Le car à Bouaké, vente vers Korhogo :

- sièges **5, 11, 15, 28, 44** barrés — montés avant ou à Bouaké, descendent après ;
- siège **35** libre — Yamoussoukro → Bouaké, il descend *à* Bouaké.

C'est mot pour mot la priorité amont de `SiegeStateProvider`.

## 4 · L'accueil après le retour en ligne — conforme

`mesVoyages()` écrase le cache à chaque chargement réussi. Vérifié : après la vidange, l'accueil est
revenu de 65 150 (corrigé localement) à 57 150 (vérité du serveur) sans intervention.

## 5 · Ce que l'appareil a trouvé : une file bloquée pour toujours

En vérifiant le point 1, la vidange s'est déclenchée correctement… et a rendu `remontees=0`. Le
serveur répondait **409 « Un enregistrement avec ces informations existe déjà »**.

La série « B » du téléphone se déduisait de la **seule** file locale. J'avais effacé cette file entre
deux essais ; le compteur est reparti à B1 alors que le serveur détenait déjà B1 et B2. Mais le vrai
problème n'est pas là :

> **Une violation de contrainte d'unicité ferme l'EntityManager, annule la transaction du LOT ENTIER
> et remonte en 409 opaque. Le téléphone ne marque rien. L'opération fautive bloque la file POUR
> TOUJOURS — toutes les ventes suivantes s'empilent derrière sans jamais partir.**

C'est le pire comportement possible pour une file d'attente, et il était atteignable par une simple
réinstallation de l'application.

**Deux corrections :**

- *Serveur* — le doublon est détecté **avant** l'écriture (`findOneBy` sur `codeticket`, et sur
  `(identreprise, codebagage)`). On ne touche jamais la contrainte : le refus reste local à
  l'opération, le reste du lot passe, et le motif dit quoi faire — « Le code … est déjà porté par un
  autre billet : celui-ci doit être ressaisi à la gare ».
- *Téléphone* — le compteur prend le **plus haut** de deux sources : la file locale ET les codes « B »
  déjà présents dans l'instantané. Une réinstallation ne le ramène plus à zéro.

Vérifié sur appareil : la file bloquée s'est débloquée **seule au démarrage suivant**, l'opération
fautive passant de « en attente » à « refusée » avec son motif lisible.

---

## Vérifications de cette passe

- `php vendor/bin/phpunit` → **189 tests, 480 assertions, OK**
- `flutter test` → **65 tests OK** · `flutter analyze` → 0 issue
- Parcours appareil complet : vente hors ligne → « Ma performance » corrigée (57 150 → 65 150,
  5 → 6 billets, 54/60 → 53/60) → vidange automatique au démarrage → accueil revenu à la vérité du
  serveur.

Les tests des deux corrections du point 5 ont été éprouvés en rétablissant l'ancien comportement.















php bin/console app:creer-super-admin

Un point que j'ai corrigé après l'avoir testée : quand un super admin existe déjà, la version initiale posait une question de confirmation — qui, en --no-interaction, retombait sur « non » et rendait un succès sans rien créer. Un script de déploiement aurait cru le compte créé. Elle échoue maintenant avec un message clair, et --force permet d'en ajouter un délibérément.

5 nouveaux tests dans CorrectionTicketTest.php, dont un qui inscrit noir sur blanc la conséquence que tu as acceptée : passé le départ, un billet de guichet n'est plus corrigible par personne, admin compris. Si ça devient gênant en exploitation, le test dira exactement quelle règle relâcher.

composant modifié pour les zindex

Impression depuis les fiches. Nouveau module vanille impression.js, sur le modèle de suivi.js : une délégation de clic sur [data-imprimer]. Les six liens concernés (bagage, courrier, billet, bordereau de gare, bordereau chauffeur ×2) impriment sans quitter la page. Un repli ouvre l'onglet si la visionneuse PDF refuse d'être pilotée.

Le sélecteur affiche bien 4 voyages — dont LI-ABI-DAL-0002-V1, daté d'hier soir et non clôturé : le bug signalé est corrigé dans l'application réelle.
Mais j'y vois aussi LI-ABI-KOR-0001-V4, qui part de Bouaké. Je vérifie si la vente depuis Adjamé serait acceptée.
Confirmé : la vente est refusée (« aucune vente possible avant cette gare »), alors que le sélecteur propose quand même le voyage — l'inverse exact du bug que tu as signalé, dans le même sélecteur. Je corrige. :: VoyagesReservablesProvider.php
Au passage, j'ai trouvé le défaut symétrique : un départ partiel lancé depuis Bouaké était proposé au guichet d'Abidjan, alors que TicketProcessor refuse la vente derrière. Le sélecteur applique désormais la même borne. Huit tests couvrent l'ensemble.


/bordereau refondue

La page s'ouvre déjà remplie sur « ma gare, aujourd'hui » — plus de ligne obligatoire, qui devient un filtre replié. Bandeau de totaux (recette, billets, bagages, courriers), raccourcis Hier/Aujourd'hui/Demain, puis une carte par départ avec l'heure en évidence, le statut (à quai / en route / clôturé), les chiffres de la gare et les deux boutons d'impression. Un dépliant montre les horaires réels, le retard, et la liste nominative des passagers avec le total encaissé — ce que contiendra le PDF, visible avant de lancer l'impression. Les fichiers d'impression n'ont pas été touchés, comme convenu.

Deux garde-fous : au-delà de 30 départs les recettes ne sont pas calculées (un appel par voyage), et les totaux ne s'affichent que s'ils sont complets — un chiffre partiel sur un écran de caisse serait pire que pas de chiffre. J'ai aussi écarté les départs partiels qui ne passent pas par la gare : ils s'affichaient à zéro et faisaient douter du total.






PHPUnit 13 veut un stub quand aucune attente n'est configurée. C'est la bonne remarque — je n'ai besoin que d'un stub. PermissionVoterTest

cd Backend-Transport && make test-db && make test


## Ce que ça produit

**1 258 enregistrements**, deux compagnies (`ira-transport` complète, `sahel-voyages` minimale pour prouver l'isolation), et un super admin sans entreprise.

Mot de passe unique pour tous les comptes : `Password123!`

| Compte | Ce qu'il permet de tester |
|---|---|
| `super@itransport.ci` | hors périmètre entreprise : corbeille, maintenance |
| `admin@ira-transport.ci` | fondateur, dashboard global avec finances |
| `chef.abidjan@ira-transport.ci` | `ROLE_ADMIN_GARE`, bypass limité aux entités de gare |
| `agent.bouake@ira-transport.ci` | gare **intermédiaire** : réception, revente |
| `commercial@ira-transport.ci` | vente à bord (app Flutter commerciale) |
| `exploitation@ira-transport.ci` | central sans gare : voit tout, sans le financier |
| `suspendu@ira-transport.ci` | `UserChecker` + `JWTSubscriber` |

Le scénario d'exploitation couvre les six états d'un départ : clôturé (hier, passages horodatés), **en route** (le car est à Bouaké, commercial à bord), planifié, **départ partiel** depuis Bouaké, imminent sans équipage, et retour. Plus un mois d'historique clôturé — sans lui, tout le module Rapports est vide de sens.

## Trois choses qui ont demandé une décision

**`EntityBase::onPrePersist()` écrase `createdAt` à chaque insertion.** Or `TicketRepository` filtre les recettes dessus. Tout aurait été daté de la seconde du chargement — aucune courbe exploitable. D'où [HorodatageTrait](Backend-Transport/src/DataFixtures/HorodatageTrait.php), qui repasse en DQL après le flush, en groupant par classe et par date. L'étendue va bien de J-30 à aujourd'hui.

**Le cycle `ticket` ↔ `reservation` casse le rechargement.** Le premier chargement passe (base vide), le second échoue — et `--purge-with-truncate` échoue aussi. J'ai ajouté [PurgerSansContrainte](Backend-Transport/src/DataFixtures/Purger/PurgerSansContrainte.php), qui décore la fabrique du bundle pour suspendre les contraintes le temps de la purge. Le rechargement marche sans option.

**Le bundle est en `require-dev`.** Tes fixtures étaient déjà exposées en prod via `App\: resource: '../src/'` sans `exclude` — en `composer install --no-dev`, la classe parente `Fixture` n'existe pas et le conteneur ne compile plus. J'ai exclu `src/DataFixtures/` du bloc principal et redéclaré sous `when@dev`/`when@test`. Ça neutralise au passage ce risque préexistant sur ton déploiement.

## Vérifications passées

- **Stock** : `stockinitial` égale exactement la somme algébrique des mouvements `Inventaire`, sur les 7 pièces. Un ajustement est bien écrit en `SORTIE` + référence `AJUSTEMENT` (pas en type `AJUSTEMENT`), et l'appro annulé garde son entrée + une sortie compensatoire.
- **Étanchéité** : 0 billet dont le voyage appartient à une autre compagnie, 0 arrêt hors compagnie.
- **Le balayeur d'alertes**, lancé sur ces données, produit les 5 situations construites pour lui — rupture, stock faible, dépannage prolongé, voyage sans personnel, no-show — sur les deux portées et les trois sévérités. Je n'ai créé aucune `Alerte` en fixture : elles sont dérivées, les écrire à la main aurait produit des alertes qui ne s'éteignent jamais.
- **Fidélité** : `Koné Salif` culmine à 13 voyages, seuil franchi. J'ai dû ajouter 80 voyageurs occasionnels — avec 8 clients pour 260 billets, tout le monde affichait 33 trajets et le membre était indistinguable.

Deux réserves à connaître. Les identifiants d'entreprise **changent à chaque rechargement** (la purge par `DELETE` ne remet pas l'auto-increment à zéro) — utilise le slug, pas l'id, dans tes requêtes de contrôle. Et les heures des voyages V2/V4/V5 sont **relatives à l'instant du chargement**, sinon un jeu chargé à 8 h montrerait un car arrivé à midi.



php bin/console doctrine:fixtures:load --no-interaction

Le bundle de fixtures est réservé à l'environnement de développement ; décorer son service depuis le fichier `services.yaml` principal casserait la build de production (`composer install --no-dev`). Je vais restreindre sa portée correctement.

Le décorateur possède désormais l'alias par défaut. Laissez-moi recharger l'application en utilisant la base de données alimentée : c'est le véritable test.







## Ce qui a été monté

Le backend n'avait aucune infrastructure de test. J'ai installé PHPUnit 13 + browser-kit + css-selector (en miroir du front), créé la base `bk_transport_test` — le suffixe `_test` était déjà prévu dans `doctrine.yaml` — et ajouté les cibles `make test`, `test-domain`, `test-api`, `test-db`.

| Suite | Tests | Ce qu'elle protège |
|---|---|---|
| `Domain/CapaciteService` | 16 | priorité amont, comptage de **sièges** et non de passagers, descente anticipée, éviction en chaîne, occupation maximale |
| `Domain/ReservationEcheance` | 10 | heure de passage = somme des tronçons, départ partiel, règle du tout-ou-rien, paiement vs présentation |
| `Domain/VoyageGuard` | 18 | origine prépare · intermédiaire réceptionne · terminus clôture ; `monteeAtteinte` vs `monteeDepassee` |
| `Api/Authentification` | 9 | JWT, refresh, suspension **avant et après** émission du jeton, anti-force-brute |
| `Api/PerimetreEntreprise` | 7 | étanchéité entre compagnies, soft delete, échappatoire du super admin |
| `Api/PerimetreGare` | 7 | les trois régimes de scope, cumul avec le périmètre entreprise |
| `Api/ReservationPublique` | 10 | surface anonyme bornée par `?slug=` |
| **Front** (3 fichiers) | 29 | `ApiUser::hasPermission`, `PermissionVoter`, `TableQueryBuilder` |

Chaque test construit ses propres données via [ScenarioBuilder](Backend-Transport/tests/Support/ScenarioBuilder.php), comme convenu, et son `TestDox` énonce la conséquence métier — pas le nom de la méthode.

## Un vrai défaut trouvé, et corrigé

`GET /api/me` **sans jeton renvoyait 500**, pas 401 : API Platform exécute le provider *avant* d'évaluer `security:`, donc [MeProvider](Backend-Transport/src/State/MeProvider.php) appelait `getId()` sur `null`. C'est exactement le piège « provider-avant-security » que ton README mentionne à propos de la corbeille — il mordait ici.

La conséquence n'était pas cosmétique : le front redirige vers le login sur 401 (`AuthenticationExceptionListener`), un 500 lui faisait afficher une page d'erreur ; côté commercial Flutter, l'intercepteur ne déclenchait pas son refresh. Le `PATCH /api/me` était touché aussi. Corrigé par une garde de trois lignes qui laisse l'entry point JWT produire le 401. C'est la seule modification de code applicatif que j'aie faite.

## Deux décisions techniques

**Isolation par transaction annulée**, pas par TRUNCATE. Vider les 50 tables avant chaque test coûtait 3,6 s — 65 s pour 16 tests. La base n'est mise à plat qu'une fois par exécution, chaque test annule ensuite ses écritures : **65 s → 5 s**. Les transactions imbriquées sont converties en savepoints, sans quoi le `wrapInTransaction` de `ReservationCreationService` refermerait la transaction du test.

**Le compteur anti-force-brute est remis à zéro par test.** Il vit dans le cache, pas en base : il échappait donc au rollback et survivait d'une exécution à l'autre, provoquant des 429 sur des tests sans rapport. Au passage, le limiteur lui-même est maintenant testé.

## Deux détails relevés au passage

Le docblock de `heurePassage()` illustre le calcul avec des durées **cumulées** (`Bouaké(240) → Korhogo(420)`), vestige du champ `dureeDepuisOrigineMinutes` renommé par la migration `Version20260722100000`. Le code somme bien des tronçons ; seul le commentaire est resté en arrière. L'arithmétique de l'exemple donne le même résultat, ce qui le rend d'autant plus trompeur.

Le suffixe `_test` étant géré par `dbname_suffix`, la base de test suit automatiquement ton `DATABASE_URL` — mais **`make test-db` est à rejouer après chaque migration**, sinon les tests valident un schéma périmé.






On vas se connecter en tant qu'administrateur de l'entreprise, pour commencer l'administrateur doit créer les paramètres de la compagnie donc on vas créer les : gares, lignes, tarifs, cars, personnels(chauffeurs, mécaniciens..) : ensuite, l'administrateur crée son équipe qui sont les administrateurs de chaque gare pour qu'ils puissent créer leurs agents (guichetiers, agents courrier/bagage) et leur attribue des permissions et il ne peuvent pas créer d'autres administrateurs de gare

La formule du scénario — l'origine prépare, l'intermédiaire réceptionne, le terminus clôture => Le périmètre par gare

On vas simuler le scénario d'un voyage complet sur la ligne `Abidjan → Bouaké → Korhogo` donc on aura n acteurs (du genre chaque administrateur de gare se connecte) puis Abidjan qui est l'origine de la ligne prépare un voyage complet et fait partir le car(du genre affecter un car, commercial qui est un user(on vas y revenir plus tard), du personnel, vendre des tickets, enregistrer des courriers et bagages, Démarrer le voyage — l'heure de départ réelle est horodatée. Les courriers passent En transit et les bagages Embarqué automatiquement) .. ensuite tiré le bordereau pour le donner au chaffeur .. une fois le car en route la gare intermédiaire(Bouaké voit venir le car, le réceptionne et le fait repartir) voit le car arrivée vers lui et une fois arrivée il réceptionne(ce qui fait que L'heure d'arrivée est horodatée. En une action, l'application bascule les courriers qui descendent ici en Réceptionné(Remettre les colis — les courriers arrivés attendent leur destinataire. Quand il se présente, confirmez la livraison : le courrier passe Livré) et les bagages en Livré) le voyage et l'agent vend des tickets etc... bordereau.. puis fais repartir le car(l'heure de départ de Bouaké est horodatée) .. une fois le car en route la gare de terminus(Korhogo clôture le voyage) voit.. et une fois arrivée il clôturer le voyage pour libérer le car pour pouvoir organiser un voyage dessus .. L'heure d'arrivée réelle est enregistrée, les bagages restants passent Livré, et le voyage n'est plus modifiable

Après ce scénario concret on suppose que la journée est terminé donc l'administrateur de l'entreprise va se connecter pour voir les statistiques ainsi que la recettes etc... , enfin, on explique les autres notions comme "Fidélité", "Remise", "Stock & approvisionnement", "Flotte & maintenance", "Parametre"

déclarer et annuler logué
expliquer le statut des sièges et la priorité
réservation, fidélité vient après


Réglez les paramètres métier pendant que vous y êtes : plafond de remise, programme de fidélité, délais de réservation et composition de la recette. Ils sont détaillés à la fin de ce guide

Aussi expliqué pour le commercial (du genre expliqué au commercial ce qu'il est censé faire)

Aussi ne pas oublié d'expliquer les détails comme quand le car tombe en panne en cours de route on peut le changer, aussi le faite la mise à jour automatique du statut des bagages et courriers à la reception et clôturation .. le confirmer livraison, aussi les détails conçernant les réservations no-show.., aussi le déclarer comme perdu, les annulations, la vente par tronçon, le désister et aussi pour l'évincé, aussi les différents états sur le plan de siège, ainsi de suite...









Seul manque notable : pas de stat dédiée aux alertes (répartition par famille/gare/gravité sur une période) — utile pour repérer les problèmes récurrents. Autres pistes optionnelles : notifications par email/SMS (aujourd'hui les alertes sont in-app)

- Pour la simulation du paiement dans la partie réservation on vas utilisé Stripe pour simuler



/api/stats/commercial est ROLE_ADMIN → vue manager (classement de tous les commerciaux). Inadaptée à « ma recette » d'un vendeur simple.
/api/voyages/me/commercial est déjà scopé au commercial connecté et porte maRecette / mesTickets / mesBagages par voyage, mais seulement pour les voyages actifs non clôturés. :: ou toute l'histori.. avec meme les cloturer

Deux choix de conception à confirmer au passage : le vidage en lot est transactionnel (si un élément est encore référencé par une FK, rien n'est purgé — plus sûr), et l'audit ActiviteLogger n'est pas branché sur restaurer/purger (il exige l'entreprise de l'acteur, que le super admin n'a pas). Ça te convient ou tu veux un autre comportement ?

Cause principale (réglage) : les pilotes thermiques (Epson TM, Xprinter, Star…) ont une option de coupe qui vaut par défaut « couper en fin de document ». Avec un PDF de 2 pages = 1 seul job → une seule coupe à la fin, donc 2 tickets collés. Le réglage à changer : Préférences d'impression → Options du périphérique → Découpe : après chaque page (« Cut per page » / « Page cut »).

Ma recommandation : retirer l'action « Supprimer » des documents opérationnels (annuler suffit) et la remplacer par « Archiver/Désactiver » sur les référentiels. C'est un chantier à part entière — dis-moi si tu veux qu'on le fasse et je te propose la liste ressource par ressource.

2. Impression des tickets ✅
- Hauteur de page constante (80 × 160 mm, PdfService::TICKET_HAUTEUR_PT) au lieu de l'auto-fit variable qui décalait le repère de coupe — avec repli automatique sur l'auto-fit si un billet exceptionnel déborde, pour ne jamais tronquer.
- Une tâche d'impression par billet (printTickets.ts) : envoi séquentiel via iframes, donc pas de pop-ups bloquées comme dans la tentative précédente (que j'ai trouvée en commentaire dans le code).
- Côté imprimante, règle le pilote sur 80 × 160 mm + découpe après chaque page — les deux corrections visent ce réglage.

## 1. Tickets « collés » à l'impression

Ton PDF est correct : `batch/print` génère bien N pages distinctes (`page-break-after` entre chaque ticket, hauteur ajustée par dichotomie dans [PdfService.php:73](Frontend-Transport/src/Domain/Service/PdfService.php)). Le problème est **au niveau du pilote d'imprimante**, avec un facteur aggravant venant de l'app :

- **Cause principale (réglage)** : les pilotes thermiques (Epson TM, Xprinter, Star…) ont une option de coupe qui vaut par défaut *« couper en fin de document »*. Avec un PDF de 2 pages = 1 seul job → une seule coupe à la fin, donc **2 tickets collés**. Le réglage à changer : `Préférences d'impression → Options du périphérique → Découpe : après chaque page` (« Cut per page » / « Page cut »).
- **Facteur aggravant (côté app)** : `generateThermalAutofit` produit une hauteur de page **variable** (calculée par dichotomie sur le lot). Or le pilote a une taille de papier **fixe** (ex. 80×297 mm). Quand les deux ne coïncident pas, le repère de coupe dérive — d'où des coupes au mauvais endroit même avec la bonne option.

Deux corrections possibles côté application : **hauteur de page constante** pour les tickets (au lieu de l'auto-fit) afin de coller à la taille papier du pilote, et/ou **un job d'impression par ticket** (N documents au lieu d'un PDF de N pages) pour que la coupe « fin de document » s'applique à chacun.

- - 
Comment pouvons-nous concevoir le système afin que chaque entreprise puisse activer ou désactiver certaines fonctionnalités selon ses besoins ?

Par exemple, une entreprise peut souhaiter utiliser le module de **réservation**, tandis qu'une autre préfère ne pas l'utiliser. Le même principe pourrait s'appliquer à d'autres modules ou fonctionnalités de l'application (courriers, carte de fidélité, etc.).

Je souhaite mettre en place une architecture flexible permettant à chaque entreprise de configurer les modules qu'elle souhaite utiliser, sans impacter le fonctionnement des autres entreprises.
- - 

- - 
Générer les alertes à partir des données actuelles (idempotent, relançable) :
    php bin/console app:alertes:generer
- Le cron sur `app:reservations:expirer` pour la réservation vu que c'est lui qui matérialise le passage en à régulariser
    > L'expiration via le cron `app:reservations:expirer` (avant le départ) → no-show `A_REGULARISER` :: Une place réservée est tenue jusqu'à ce délai avant le départ, puis libérée. 0 = jusqu'au départ. (120 = 2 h)
    > php bin/console app:reservations:expirer
        */5 * * * * cd /chemin/vers/BK-Transport && /usr/bin/php bin/console app:reservations:expirer --env=prod --no-interaction >> var/log/cron-reservations.log 2>&1
    > Ou 'loc..:8000/api/cron/reservations-expirer?token=' pour tester côter backend
    > Le cron qui ne s'exécute pas sur l'hébergeur => C'est un grand classique de l'hébergement mutualisé (mauvais binaire PHP, mauvais `APP_ENV`, ou host qui ne propose qu'un cron **par URL** et pas en ligne de commande). Je regarde la sécurité pour te proposer une solution robuste (un endpoint HTTP déclenchable + du log pour vérifier).
- La notification temps réel `WebSocket` ou polling `/alertes`

- démarrer → réceptionner → repartir → clôturer un voyage
- Le cumul 12H + 100min font 13 heures et 40 minutes du genre l'heure de départ prévue du voyage remplace celui de ligne et le cumul est appliqué
- L'échéance de présentation d'un passager qui monte en gare intermédiaire se calcule sur le départ prévue de la gare intermédiaire sinon son bon peut expirer alors que le car roule encore vers lui

- Mais il reste un trou que je dois te signaler. Le webhook arrive après que le client a payé chez le prestataire. Refuser la confirmation ne lui rend pas son argent : on empêche l'incohérence, pas le prélèvement.
    > La vraie parade est en amont : tenir la place pendant la fenêtre de paiement. C'est exactement à ça que sert le nouveau delaiPaiementMinutes (30 min) — un « hold » court, comme dans n'importe quel tunnel de réservation. Une EN_ATTENTE tiendrait sa place, mais seulement 30 minutes, jamais jusqu'au départ comme dans l'ancien modèle. => Le compromis : quelques places bloquées jusqu'à 30 min pour des clients qui ne paieront peut-être pas. À l'inverse, sans ça, tu continueras d'encaisser des paiements que tu devras rembourser à la main.

D. Renforcements recommandés (par ordre d'impact) :
    Remise : motif/bénéficiaire obligatoire au-delà d'un seuil + plafond de remise par agent.

Base temporelle de la ligne : le cargo est filtré sur sa date de saisie (createdAt), les billets sur la date de départ du voyage. Écart mineur sur des périodes courtes, sans impact sur des périodes normales.

Perf des stats	N+1 évités, mais endpoints lourds	🟡 Prévoir index/cache à volume élevé
Infra (backups, monito, déploiement)	non abordé ici	🟡 À cadrer côté ops

aussi sache que je privilégie les provider aux controllers comme tu l'a fais avec (BilletterieStatsController, CommercialStatsController, CourrierStatsDetailsController, DepartStatsController, FlotteStatsDetailsController),  (BilleterieStatsProvider, CourrierStatsProvider, FlotteActiviteStatsProvider) ce qui provoqué 2 appel dans le controller du frontend :: lequel est le mieux les controllers ou provider vu que j'utilise API Platform

## Note sur `Criteria` vs `filter()`

Tous les compteurs ajoutés (ici et sur `Voyage`) utilisent `matching(Criteria)` : sur une collection non chargée, Doctrine traduit en SQL et `count()` devient un `COUNT`. C'est ce qui distingue une vraie optimisation d'un simple déplacement de code — `filter()` aurait continué à tout hydrater en silence.
- - 

























resaflutter	Réservation grand public	Flutter, Riverpod, Dio, GoRouter, freezed
resanative	Réservation grand public	Expo / React Native, TanStack Query, Zustand


## Vue d'ensemble

C'est un système à **4 dépôts**, pas 2 : le backend API, le frontend d'exploitation, et deux apps mobiles jumelles.

| Projet | Rôle | Stack |
|---|---|---|
| `Backend-Transport` | API REST, toute la logique métier | Symfony, API Platform, Lexik JWT + refresh token, Doctrine/PostgreSQL |
| `Frontend-Transport` | Back-office des compagnies | Symfony/Twig **client de l'API** (pas de base propre), React UX, shadcn, Tailwind v4 |
| `resaflutter` | Réservation grand public | Flutter, Riverpod, Dio, GoRouter, freezed |
| `resanative` | Réservation grand public | Expo / React Native, TanStack Query, Zustand |

Les deux mobiles couvrent exactement le même périmètre (booking, history, track) : ce sont deux implémentations concurrentes de la même app, ce qui a du sens comme comparatif de stack.

## Ce qui structure vraiment le backend

Le multi-tenant repose sur **deux couches d'extensions Doctrine qui se superposent**, appliquées automatiquement à toutes les requêtes API Platform sauf les POST. `EntrepriseScopeExtension` borne à l'entreprise (via `EntrepriseOwnedInterface`, en filtrant aussi le softDelete au passage), puis `GareScopeExtension` borne à la gare — mais uniquement pour un agent rattaché à une gare et non-admin.

Le point le plus élégant, c'est que le périmètre par gare est **déclaré par interface plutôt que codé en dur**, avec trois formes selon la nature de la donnée :

- `GareOwnedInterface` — une seule gare (`User.gare`, `Ticket.gare`)
- `MultiGareScopedInterface` — visible si l'une des deux gares est la sienne (`Courrier`, `Bagage`)
- `LigneGareScopedInterface` — visible si la ligne dessert sa gare, via un chemin de relations (`Voyage: ['ligne']`, `Ticket: ['voyage','ligne']`)

Le commentaire sur le `EXISTS` du cas ligne est important et mérite d'être conservé : un `INNER JOIN` sur les arrêts tronquerait la collection `arrets` sérialisée par l'eager-loading, et `/api/lignes/{id}` ne renverrait qu'un seul arrêt.

Côté droits, il y a une séparation nette que j'ai trouvée bien pensée : `PermissionVoter` gère le RBAC en base (une seule requête via `hasPermission`), et le bypass `ROLE_ADMIN_GARE` est délibérément découplé des interfaces de scope — il s'appuie sur la liste explicite `GareScopedEntities::ENTITIES`. Une `Ligne` est filtrée par gare en lecture mais reste de la configuration entreprise qu'un admin de gare ne modifie pas. Cette distinction visibilité/possession est le genre de chose qu'on casse facilement par inadvertance.

## Deux décisions métier à garder en tête

**La capacité par tronçon.** `CapaciteService` modélise chaque billet comme un intervalle `[montée, descente)` et calcule l'occupation maximale sur les segments élémentaires — c'est ce qui permet de revendre un siège sur des tronçons disjoints. Et surtout : **une réservation non émise ne consomme aucune place**, elle n'est qu'indicative (surréservation tolérée). Seul un billet `VALIDE` bloque.

**La façade publique.** `ReservationPublique` est une ressource non persistée, `PUBLIC_ACCESS`, où le périmètre entreprise ne vient pas d'un JWT mais du `?slug=` résolu par `EntreprisePubliqueResolver` (404 si slug inconnu ou entreprise suspendue). C'est le pivot des deux mobiles, et les deux ont fait le même choix : **une build = une compagnie** (`COMPANY_SLUG` en `--dart-define` côté Flutter, `EXPO_PUBLIC_COMPANY_SLUG` côté Expo).

## Un écart entre le README et le code

Le README (ligne 69) décrit la préparation d'un voyage comme : « créer, modifier, affecter car/personnel, supprimer → toute gare SAUF la destination ». Le code a depuis **scindé cette règle en deux**, et les appels dans les processors le confirment :

- `assertPeutPlanifier` — gare d'**ORIGINE uniquement** : création, modification, commercial, suppression
- `assertPeutGerer` — **toute gare sauf le terminus** : affecter le car et le personnel, pour couvrir l'incident en route (panne)

Donc l'affectation car/personnel ne suit plus la même règle que la création/modification, contrairement à ce que dit le README. Le docblock de `VoyageGuard` documente bien la nouvelle doctrine (« l'origine prépare · l'intermédiaire réceptionne · le terminus clôture »), mais le README est resté sur l'ancienne. Je peux le corriger si tu veux.








## 2. Slug vs ID — je te déconseille de basculer

Ton intuition est probablement « l'id est stable, le slug peut changer ». Mais dans ton code **le slug est déjà immuable** : il est généré une seule fois dans `RegisterProcessor:48` et le champ n'est pas dans le groupe `write:Entreprise`, donc rien ne le réécrit jamais. L'argument principal en faveur de l'id tombe.

Et passer à l'id coûterait deux choses :

**L'énumération.** Avec `?id=1,2,3…` n'importe qui parcourt la liste complète de tes compagnies en une boucle. Or ces endpoints publics renvoient le branding, les villes, les gares, les destinations **et les tarifs** — c'est la carte commerciale de chaque compagnie, moissonnable intégralement. Le slug n'est pas un secret, mais il faut connaître le nom : il n'offre pas d'itérateur.

**Le pinning au build.** Tes deux apps figent l'identifiant à la compilation (`--dart-define=COMPANY_SLUG`, `EXPO_PUBLIC_COMPANY_SLUG`), une build = une compagnie. `COMPANY_SLUG=transport-ira` est auto-documenté et une faute de frappe fait un 404 bruyant. `COMPANY_ID=7` est opaque, et un chiffre erroné pointe **silencieusement** la build vers les données d'une autre compagnie. Sur une release store, c'est le pire type de bug.

Accessoirement la perf ne joue pas : `slug` est en `unique: true`, donc indexé.

La seule vraie faiblesse du slug actuel est cosmétique : il dérive du libellé à l'inscription et n'est jamais régénéré, donc après un changement de nom commercial il reste figé sur l'ancien. Si ça te gêne, la bonne réponse n'est pas l'id — c'est de découpler le slug du libellé (le laisser saisissable une fois à l'inscription). Dis-moi si tu veux quand même la bascule, je la ferai, mais je pense que le slug est le bon choix ici.

## 4. Plan des sièges — proposition (non implémentée)

Le diagnostic : `PlanCar` est un **tableur**, pas un véhicule. Des carrés de 40px, des numéros de rangée en marge, et deux barres texte « Avant »/« Arrière ». Rien ne dit « bus » — la barre « Avant » est le seul indice et c'est du texte.

Par ordre de rapport impact/effort :

**Le couloir, en premier.** C'est le signal le plus manquant. Aujourd'hui `absCol` pose tous les sièges dans une grille contiguë et les trous sont des `<div>` vides — **un siège manquant et le couloir central sont visuellement identiques**. Un vrai bus se lit « 2 + couloir + 2 » instantanément. Rendre le couloir comme une vraie voie (fond distinct, plus étroit que les sièges) fait basculer la lecture à lui seul.

**La coque.** Remplacer la carte rectangulaire par une silhouette : nez avant nettement plus arrondi que l'arrière (rayon asymétrique), une paroi extérieure et un plancher intérieur légèrement distincts, deux ou trois encoches de roues sur les flancs. Détails peu coûteux, effet « véhicule » immédiat.

**La cabine.** À la place de la barre « Avant » : une zone conducteur avec volant, le siège chauffeur orienté, et la porte avant. C'est l'ancre d'orientation — on sait d'où on regarde.

**La forme du siège.** Un carré `rounded-md` ne dit pas « siège ». Un dossier plus haut que l'assise (`rounded-t-lg rounded-b-sm` + une ligne d'assise en `inset shadow`), voire deux accoudoirs en pseudo-éléments, suffisent. Subtil, mais c'est ce qui fait la différence entre une case et une place.

**La banquette arrière.** Ton enum `cote` a déjà `ARRIERE` : en bus c'est presque toujours une banquette pleine largeur de 4-5 places. La rendre comme un bloc continu plutôt que des sièges isolés, c'est gratuit et très reconnaissable.

**Le blocage à trancher d'abord** : le couloir **n'existe pas dans le modèle**. Avec `cote: GRILLE`, `colonne` est absolue et rien ne dit quelle colonne est la voie. Trois options :

1. **L'inférer** — la colonne vide sur la majorité des rangées est le couloir. Zéro migration, marche sur les cars existants, mais fragile sur un plan à trous légitimes.
2. **Le déclarer** — un champ `alleeApres` (n° de colonne) sur `Car`. Une migration, explicite, robuste.
3. **Par convention** — couloir après `floor(maxCol/2)`. Gratuit, mais faux dès qu'un plan est asymétrique (30 places 2+1).

Je partirais sur **2**, quitte à initialiser via l'heuristique 1 dans la migration : c'est une donnée du véhicule, pas une devinette d'affichage.

**Deux pièges à garder en tête** : le numéro de siège est ce que l'agent lit à voix haute, la métaphore ne doit jamais le rendre moins lisible — et 40px est petit pour un guichet tactile, je monterais à 44px minimum.

Dis-moi si tu veux que j'attaque le plan des sièges, et par quelle option pour le couloir.








## Le design cible

La maquette montre les éléments qui donnent la lecture « véhicule » : coque au nez arrondi, poste de conduite (volant, siège chauffeur, porte avant), roues sur les flancs, dossiers de sièges (la barre sombre en bas de chaque place), et la banquette arrière rendue comme un **contenant à 5 cellules** — le siège 20, occupé, reste gris à l'intérieur du bloc, donc on garde l'état par place. Les 5 états et les pastilles `R`/`↻` sont là, à l'identique du composant fusionné.

## Un point à clarifier avant de coder (couloir)

Dans la maquette, j'ai dessiné le couloir en **bande de sol continue** — c'est ce qui distingue le couloir du siège manquant en rangée 3. Mais cette bande suppose qu'on sache *où* est le couloir, ce qui est justement l'info que `alleeApres` aurait portée.

Puisque tu as tranché « laisse le couloir tel quel, pas de `alleeApres` », la version réelle gardera le couloir en **simple espace vide** comme aujourd'hui. Tout le reste du véhicule (coque, cabine, roues, dossiers, banquette) ne dépend pas de la position du couloir et sera implémenté tel que dessiné. Deux options pour le couloir lui-même :

- **A — on le laisse en gap vide** (strictement ta décision) : la silhouette porte à elle seule la métaphore, mais couloir et siège manquant restent visuellement identiques.
- **B — bande de sol par inférence frontend** : on déduit la colonne-couloir (celle vide sur la plupart des rangées) au rendu, sans champ ni migration — donc sans `alleeApres`. C'est une heuristique, faillible sur un plan très irrégulier, mais elle rétablit la distinction couloir / trou.

Je partirais sur **B** (elle respecte « pas de `alleeApres` » tout en gardant le bénéfice principal), mais **A** est le plus fidèle à « laisse le ainsi ». 










## Ce qui a changé

**Duplication supprimée.** Le `SiegeCell` + `PlanCar` internes au `TicketForm` (~200 lignes) sont partis. Le fichier importe maintenant le composant partagé :

```ts
import PlanCar from "./PlanCar"
```

**Source unique.** [Billetterie/PlanCar.tsx](Frontend-Transport/assets/react/controllers/Billetterie/PlanCar.tsx) est désormais consommé par les trois écrans :
- `TicketForm` (vente) — interactif complet, 5 états, `reservedIds` + `onLiberer`
- `DesistementForm` (report) — sélection simple
- `PlanCarReadonly` (fiche véhicule) — lecture seule

Toutes les améliorations (correctif badge « R », légende adaptative, variantes dark sur les sièges) vivent donc à **un seul endroit**. Ça inverse le compromis que je t'avais signalé la dernière fois : plus deux fichiers à maintenir, un seul.

## Le seul point technique

Le composant partagé type ses callbacks en `SiegePlan` (le dénominateur commun), alors que les handlers du `TicketForm` (`toggleSiege`, `handleLibererSiege`) attendent son type `Siege` plus riche (`@id` requis, infos occupant). D'où deux casts à l'appel :

```tsx
onToggle={(siege) => toggleSiege(siege as Siege)}
onLiberer={(siege) => handleLibererSiege(siege as Siege)}
```

C'est le genre d'incompatibilité qui te cassait le build avant. Le cast est **sûr** : les sièges rendus sont exactement `siegesData.sieges` (des `Siege[]`), donc l'objet que le plan renvoie au callback est réellement un `Siege` — le cast ne fait que le redire à TypeScript. C'est commenté dans le code. Si tu préfères zéro cast, l'alternative propre serait de rendre `PlanCar` générique (`<T extends SiegePlan>`) ; dis-le et je bascule là-dessus.







La façon la plus propre, c'est de **rendre `PlanCar` générique** sur le type de siège — `PlanCar<T extends SiegePlan>` — au lieu de caster. Chaque consommateur garde son type riche : `TicketForm` infère `T = Siege` et passe ses handlers directement, sans `as`. Je te montre en l'implémentant. Je relis d'abord le `SiegeCell` actuel.

Le plan : passer `onToggle`/`onLiberer` du `SiegeCell` en callbacks sans argument (pour que le générique ne propage pas la contravariance dedans), puis rendre `PlanCar` générique. J'enchaîne les modifications.

Maintenant l'usage du `SiegeCell` dans la boucle — `PlanCar` capture `s: T` et le transmet aux callbacks.

Enfin, dans `TicketForm`, je retire les casts : les handlers se passent directement.

Zéro cast, build propre. Voilà la façon la plus propre — et pourquoi elle l'est.

## Le principe : composant générique plutôt que cast

Le cast n'était qu'un pansement : il forçait TypeScript à taire une incompatibilité réelle. La vraie source du problème, c'est que le composant partagé imposait **un seul** type de siège (`SiegePlan`) à tous ses appelants, alors que chacun a le sien. La solution propre est de le rendre **générique sur le type de siège** :

```ts
interface PlanCarProps<T extends SiegePlan> {
    sieges: T[]
    onToggle?: (siege: T) => void
    onLiberer?: (siege: T) => void
    // …
}
export default function PlanCar<T extends SiegePlan>({ … }: PlanCarProps<T>) { … }
```

Résultat, chaque écran récupère **son** type sans rien caster :
- `TicketForm` passe `sieges={siegesData.sieges}` (`Siege[]`) → TypeScript infère `T = Siege`, donc `onToggle` attend un `Siege` et `toggleSiege` se passe **directement**.
- `DesistementForm` → `T = SiegePlan & { "@id" }`, inchangé, toujours valide.
- `PlanCarReadonly` → `T = SiegePlan`, pas de callback.

## Le détail qui rend le générique possible

Un générique naïf aurait juste déplacé le problème dans `SiegeCell` : lui passer `onToggle: (T) => void` là où il attend `(SiegePlan) => void` rebute sur la même contravariance. La clé, c'est de **découpler `SiegeCell` du type générique** en lui donnant des callbacks **sans argument** :

```tsx
// SiegeCell : ne sait plus QUEL siège, juste « clic sélection » vs « clic libération »
onSelect?: () => void
onLiberer?: () => void
```

C'est `PlanCar` qui capture le siège typé `s: T` dans la boucle et le réinjecte :

```tsx
onSelect={onToggle ? () => onToggle(s) : undefined}
onLiberer={onLiberer ? () => onLiberer(s) : undefined}
```

`SiegeCell` n'a de toute façon besoin du siège que pour l'**afficher** (`siege: SiegePlan` suffit) ; c'est `PlanCar` qui a besoin du type riche pour les callbacks. La frontière est au bon endroit.

## Pourquoi c'est mieux que le cast

Le cast `as Siege` était sûr *à cet instant*, mais reposait sur une promesse non vérifiée par le compilateur : « fais-moi confiance, c'est bien un `Siege` ». Si demain quelqu'un branche `PlanCar` sur des sièges sans `@id`, le cast continue de mentir en silence — bug au runtime. Le générique, lui, fait **vérifier** cette contrainte par TypeScript à chaque appel : plus de promesse, une garantie.

Un point runtime : générique ou pas, c'est effacé à la compilation — aucun impact sur `registerReactControllerComponents` ni sur les perfs. Build propre (8 warnings PostCSS préexistants).









Perf du compte : sans relation directe Client→Bagage, getBagagesCount() traverse les billets du client (donc les hydrate) sur chaque ligne du listing. Acceptable pour un listing admin, mais si une fiche devient très volumineuse, il faudra passer à un DQL COUNT joignant tickets→bagages (comme le README le recommande pour les gros décomptes). Je peux le faire si tu veux.

## Réserve #1 — compte des bagages optimisé (N+1 → 1 requête)

J'ai remplacé la méthode qui traversait les billets par un **provider décorateur** calqué sur `InventaireProvider` :

- [ClientRepository.php](Backend-Transport/src/Repository/ClientRepository.php) : `bagagesCountByClientIds()` — un seul **DQL COUNT groupé** (billets→bagages, `deletedAt IS NULL` des deux côtés) pour tout un lot d'IDs.
- [ClientProvider.php](Backend-Transport/src/State/ClientProvider.php) : décore les providers Doctrine (collection + item), **conserve tout le pipeline** (filtres, tri, pagination, `EntrepriseScopeExtension`) et enrichit chaque client de `bagagesCount`.
- [Client.php](Backend-Transport/src/Entity/Client.php) : `bagagesCount` devient un champ **transient** (non mappé, comme `User.fileUrl`) rempli par le provider ; `provider: ClientProvider::class` branché sur `GetCollection` et `Get`.

Coût passé de « 1 requête + N counts par client sur la page » à **une seule requête pour toute la page**.










**3. Revendre un siège occupé sur le MÊME tronçon → non, ça casse l'invariant.** Tout ton modèle repose sur « un siège = au plus un passager par segment » (`TicketProcessor::assertSiegeLibre` bloque un siège occupé sur un tronçon *qui chevauche*). Revendre le même siège sur le même tronçon = deux billets `VALIDE`, même siège, même trajet = deux personnes sur une place → conflit à l'embarquement, litiges de remboursement, et la capacité peut dépasser le nombre de sièges. Ce que tu veux probablement, c'est l'un de ces deux cas *légitimes*, déjà couverts ou couvrables autrement :
- **No-show / départ anticipé** : libère le siège (`/descendre` ou annulation du billet occupant) *puis* revends — c'est le mécanisme « libérer » existant. Une place ne se revend qu'une fois **réellement** libérée.
- **Survente volontaire** : c'est du **place-level** (vendre plus de places que de sièges en pariant sur les absents), pas du siège. À faire comme politique de surréservation par voyage, jamais en assignant deux fois le même siège physique.

Donc : ne l'ouvre pas au niveau siège. Si tu veux gérer les absents ou survendre, dis‑le, c'est un autre design (propre).

## Modification de ticket (#2/#3) — tu as raison, voici le gap précis

Aujourd'hui la modif ne change QUE `nomclient`/`contactclient` (identité passager), via `UpdatedbyProcessor` qui garde seulement : voyage **clôturé** + gare émettrice + `updatedBy`. Deux vrais manques :

1. **Le lien `client` devient incohérent.** Si on change `contactclient` (le téléphone = clé du client), rien ne **re‑résout** le `Client` (find‑or‑create via `ClientResolver`). Le snapshot change mais le FK `client` reste sur l'ancien. C'est ça la logique « pas à jour » — et c'est *cette* logique-là qui vit dans `TicketProcessor`. **Mais attention** : réutiliser le `TicketProcessor` complet (flux de création : capacité, prix, siège, remise, fidélité) pour un simple changement de nom serait dangereux. La bonne réponse n'est pas « swap vers TicketProcessor », c'est **ajouter la re‑résolution client au chemin de mise à jour**.

2. **Pas de garde « car a atteint la gare de montée ».** Ton instinct est exact, et cette logique **existe déjà** au désistement ([DesistementProcessor.php:99‑118](Backend-Transport/src/State/DesistementProcessor.php)) : `datedepartreelle !== null` (le voyage a démarré) **+** position du car (`garecourante`) ≥ ordre de la gare de montée du billet → blocage, intermédiaire‑aware. Le `UpdatedbyProcessor` ne l'a pas. « Détecter si le voyage a démarré » = `datedepartreelle !== null` : exactement ça.








Le template courrier existe déjà et couvre les bons champs. Mais je repère un vrai problème technique : il utilise display: flex (en‑tête, ligne valeur/frais) — or Dompdf ne supporte pas flexbox, donc ces blocs se rendent empilés au lieu d'être côte à côte, contrairement à ton image. Il faut passer par des <table>. Je lis le reçu bagage pour aligner les deux.





## 1. Reçu courrier sur 2 pages — corrigé

La cause : `PdfService::generate()` en mode thermique impose une page **fixe de 160 mm**, et mon reçu la dépassait. Plutôt que de seulement rétrécir la police, j'ai basculé sur **`generateThermalAutofit()`** — déjà présent dans le service et utilisé pour les billets : il calcule la hauteur de page qui tient exactement le contenu.

C'est le bon choix ici parce que le nombre de **colis varie** : n'importe quelle hauteur fixe finira tôt ou tard par déborder sur une 2ᵉ page. Avec l'autofit, c'est une page quel que soit le nombre de lignes, et sans grand vide en bas.

J'ai **aussi** resserré la typographie comme tu l'autorisais (titre 21→17 px, code 26→20 px, sections 14→12 px, marges réduites d'environ un tiers) : le reçu reste compact et économe en papier. Le reçu bagage bénéficie du même autofit.







D'abord je cartographie : comment ces collections sont exposées côté backend, et qui les consomme réellement côté frontend (je ne veux pas casser une vue qui affiche les listes).

`getTicketsCount()` existe mais via `->filter()`, qui hydrate quand même la collection. Avant de retirer quoi que ce soit de `read:Voyage`, je dois savoir qui consomme réellement ces listes côté frontend.

Résultats bruités (ce sont surtout des stats, et `depannage.detailpersonnels` — autre entité). Je cible précisément les usages liés au **voyage**.

Diagnostic clair : la **fiche voyage** (`voyage/show.html.twig`) a réellement besoin des listes complètes, alors que les **tableaux** n'en veulent que la taille. Les deux partagent le groupe `read:Voyage`. La bonne réponse ApiPlatform est un groupe dédié à l'item — et ce pattern existe déjà chez toi (`read:Gare:item`). Je vérifie son câblage.

Pattern confirmé (`Get` avec `normalizationContext` enrichi), et `Detailpersonnel` étend `EntityBase` (donc `deletedAt`). Je lis l'opération `Get` du voyage.

J'implémente. Le `Get` (fiche) reçoit un groupe dédié pour conserver les listes complètes :

Maintenant les compteurs. Point important : `getTicketsCount()` utilisait `filter()`, qui **hydrate** toute la collection — inoffensif tant que les billets étaient sérialisés, mais ce serait devenu une régression. Je passe tout en `matching(Criteria)`, traduit en `COUNT` SQL.

Backend fait. Côté frontend, je mets à jour le modèle et les deux tableaux qui comptaient via `.length`.

## Le problème

La liste des voyages sérialisait **quatre collections entières** (`tickets`, `courriers`, `bagages`, `detailpersonnels`) pour chaque ligne… alors que l'interface n'en affichait que le **nombre**. Sur une page de 25 voyages, l'API hydratait et transportait potentiellement des milliers d'objets pour afficher trois chiffres par ligne.

Un piège que j'ai trouvé en chemin : `getTicketsCount()` utilisait `->filter()`, qui **charge toute la collection** en mémoire. C'était masqué tant que les billets étaient sérialisés de toute façon — mais retirer les collections sans y toucher aurait laissé l'hydratation en place, donc annulé le gain.

## La correction

**Séparation liste / fiche** (pattern déjà présent chez toi avec `read:Gare:item`) :
- les 4 collections passent en **`read:Voyage:item`** → sérialisées **uniquement** sur `/api/voyages/{id}`, que la fiche voyage affiche réellement ;
- l'opération `Get` reçoit `normalizationContext` avec ce groupe ;
- la **liste** ne reçoit plus que des compteurs.

**Compteurs en `COUNT` SQL** — `matching(Criteria)` au lieu de `filter()` : sur une collection non chargée, Doctrine traduit le critère en SQL et `count()` devient un `COUNT`, sans jamais hydrater. J'ai ajouté `courriersCount`, `bagagesCount`, `detailpersonnelsCount` et **réécrit `getTicketsCount()`** sur ce principe. Les éléments supprimés (`deletedAt`) sont exclus, cohérent avec les listes affichées.

Effet de bord bienvenu : partout où un voyage est **imbriqué** dans une autre réponse (billet, bagage…), la charge s'allège aussi, puisque ces collections ne sont plus dans le groupe par défaut.


## Note sur `Criteria` vs `filter()`

Tous les compteurs ajoutés (ici et sur `Voyage`) utilisent `matching(Criteria)` : sur une collection non chargée, Doctrine traduit en SQL et `count()` devient un `COUNT`. C'est ce qui distingue une vraie optimisation d'un simple déplacement de code — `filter()` aurait continué à tout hydrater en silence.





Découverte importante : les tableaux de dépannage ne font pas que compter detaildepannages — ils somment les quantités (.reduce((sum, d) => sum + d.quantite, 0)). Ce cas sort donc du simple .length. Je vérifie les opérations et le repository pour décider.






Cette dérivation repose sur une convention : la banquette est la rangée du fond qui remplit le couloir. C'est vrai pour tout ce que ton générateur produit. Mais si un jour tu veux marquer comme banquette une rangée qui ne remplit pas le couloir (par ex. un car avec une porte arrière laissant un trou), la géométrie ne suffira plus.

Si ce cas t'intéresse, la vraie réponse n'est pas un booléen à côté du plan, mais enrichir le plan lui‑même — par exemple un marqueur de rangée dans plansieges (B: 57 58 59 60 61). On garde une source unique, et l'intention devient explicite au lieu d'être devinée.

1 2 . 3 4
5 6 . 7 8
B: 9 10 11 12 13     ← banquette, déclarée













Bonnes questions — et elles touchent au cœur du modèle. Je ne veux pas répondre de mémoire, je lis le code réel : expiration, capacité, et ce qui rend un départ réservable.

Découverte clé : à l'échéance, payée ≠ impayée (l'impayée meurt, la payée devient récupérable). Voyons maintenant **comment la date d'expiration est calculée** et ce qui rend un départ réservable.

Voilà le nœud : `dateexpiration = départ − delaiExpirationMinutes` (120 par défaut), calculée **une seule fois à la création**, avec **le même délai** que la réservation soit payée ou non. Il me manque la règle « départ réservable ».

Il me manque un point pour être juste : est‑ce que la création de réservation vérifie la capacité, et que se passe‑t‑il sans tarif ?

J'ai les trois réponses, code en main. Et ton intuition est juste sur les trois points.

## 1. Quand une place réservée est-elle revendable ? — **Toujours. Elle ne tient jamais.**

Une réservation ne consomme **aucune** capacité, même payée :

- `CapaciteService::placesDisponibles()` ne compte que les billets `VALIDE` (émis) ;
- `placesReservees()` est purement **indicatif** (« X places réservées ») ;
- même à la création, sous verrou pessimiste sur le voyage, `assertPlaceDisponible()` ignore les autres réservations.

Conséquences concrètes : le guichet peut vendre **100 % des sièges** alors que 100 % sont réservés **et payés** ; et deux clients mobiles peuvent réserver « la dernière place » en même temps — le verrou sérialise les accès, mais le test lui‑même ne regarde pas les réservations.

C'est un choix assumé et documenté (« surréservation tolérée », les no‑shows sont fréquents). Il se défend pour une réservation **impayée** — ce n'est qu'une intention. Il ne se défend pas pour une réservation **payée** : on encaisse sans rien garantir. C'est là l'incohérence de fond.

## 2. Quand un voyage est-il réservable ?

Conditions cumulées (`DepartsPubliquesProvider` + `ReservationCreationService`) :

- voyage **futur**, non clôturé, rattaché à une ligne, avec date de départ ;
- **`maintenant < départ − delaiExpirationMinutes`** (120 min par défaut) ;
- montée et descente sont des arrêts de la ligne, dans le bon ordre ;
- montée **pas en amont de la provenance réelle** (départ partiel) ;
- capacité définie (car affecté ou `placesprevues`) et `placesDisponibles > 0` — calcul qui, encore une fois, ignore les réservations ;
- un **tarif** existe pour le tronçon.

Petite incohérence au passage : le tarif est **exigé à la création** (erreur bloquante), mais la **liste publique ne filtre pas** les départs sans tarif — elle les affiche avec `montant: null`. Le client peut donc voir un départ sans prix… qui échouera au moment de réserver.

## 3. Même délai pour en attente et payée ? — Le délai oui, la conséquence non

`dateexpiration = départ − delaiExpirationMinutes`, **figée à la création**, identique dans les deux cas. Ce qui diffère, c'est l'issue : `EN_ATTENTE` → `EXPIREE` (perdue), `CONFIRMEE` → `A_REGULARISER` (récupérable, avec pénalité + complément, pendant `fenetreRegularisationJours`).

Ton doute est fondé : **un seul réglage porte deux notions incompatibles**.

| Statut | Ce que le délai signifie réellement |
|---|---|
| `EN_ATTENTE` | délai de **paiement** |
| `CONFIRMEE` | délai de **présentation** (no‑show) |

Deux effets gênants :

- **Un client qui a payé et se présente 1h30 avant le départ est traité en no‑show.** Pour voyager, il doit une pénalité + un complément tarifaire. Alors qu'arriver 90 minutes avant le bus est parfaitement normal.
- **Le délai de paiement est ancré sur le départ, pas sur la création.** Réserver un départ dans trois semaines laisse ~trois semaines pour payer.

## Ce que je propose

1. **Scinder le réglage en deux** : `delaiPaiementMinutes` (à partir de la **création**, ex. 30 min, borné par le départ) et `delaiPresentationMinutes` (avant le **départ**, ex. 15 min, voire 0 = jusqu'au départ).
2. **Faire tenir la place par une réservation payée** : `placesDisponibles` déduit les `CONFIRMEE` non émises et non expirées. L'impayée continue de ne rien tenir — ou seulement pendant sa courte fenêtre de paiement. C'est le point qui rend le reste cohérent.
3. **Filtrer les départs sans tarif** dans la liste publique.

Le point 2 est le plus structurant : il change le comportement de la vente au guichet (l'agent verrait moins de places disponibles quand des clients ont payé en ligne) et donc la recette. Je préfère que tu le valides avant que j'y touche.

Tu veux que je parte sur les trois, ou seulement certains ?







On y va. C'est un changement structurant, donc je lis d'abord les pièces exactes : statuts, repository des réservations, et le service de confirmation de paiement.

Le repository m'apprend deux choses utiles : la réservation porte un `ticket` (donc « émise » est détectable), et `findActivesPourVoyage` récupère déjà le bon ensemble. Je recense tous les usages du délai actuel avant de le scinder.

Recensement complet. `ReservationStatus::actifs()` n'est utilisé **nulle part** (et son commentaire affirme faussement que `EN_ATTENTE` tient une place) — je le corrigerai. Je lis l'entité de config pour le nommage exact des colonnes.

J'implémente. **1/ L'entité de configuration** — le délai unique devient deux réglages distincts :

**2/ La capacité** — c'est le cœur : une réservation **payée** tient désormais la place.

Maintenant `CapaciteService` : les réservations payées entrent dans le calcul d'occupation.

**3/ Les services** — délais scindés à la création, bascule d'échéance au paiement, et filtre du tarif.

Au paiement, l'échéance doit basculer du délai de paiement vers celui de présentation.

Reste les deux appelants du délai et le filtre du tarif dans la liste publique.

**4/ Le DTO de config, son processeur et la migration.**

La migration : je renomme la colonne existante (pour préserver les valeurs déjà réglées) et j'ajoute la nouvelle.

Reste le frontend (formulaire de config) puis validation complète.

Validation complète : plus aucune référence à l'ancien délai, lint, migration et mapping.

Migration passée, schéma synchro, et le renommage a bien **préservé la valeur déjà réglée** (30 min) au lieu de la réinitialiser.

## Ce qui change, point par point

**1. Une réservation payée tient sa place.** C'était le cœur du problème : on encaissait sans rien garantir. Désormais `CapaciteService` déduit de la capacité les réservations `CONFIRMEE` non émises et non échues, avec la même logique d'intervalles par tronçon que les billets. Aucun double comptage : dès qu'une réservation devient un billet, elle porte un `ticket` et sort du calcul.

Une réservation **impayée** continue de ne rien tenir — c'est une simple intention, la surréservation reste tolérée. C'est cette distinction qui manquait.

`placesReservees()` ne compte donc plus que les `EN_ATTENTE` : les payées sont déjà retirées des places disponibles, les recompter aurait fait doublon à l'écran. Les pastilles ambre « R » du guichet gardent exactement leur sens — « pressenti, encore vendable ».

**2. Deux délais au lieu d'un.**

| Réglage | Ancré sur | Rôle |
|---|---|---|
| `delaiPresentationMinutes` (15) | le départ | dernière limite pour retirer son billet — et pour réserver |
| `delaiPaiementMinutes` (30) | la création | temps pour payer une réservation en attente |

`dateexpiration` porte l'échéance **qui s'applique à cet instant** : celle du paiement tant que c'est impayé, puis elle bascule sur la présentation **au moment de l'encaissement**. C'est ce qui corrige ton cas : un client ayant payé n'est plus déclaré no-show à l'heure limite de paiement. Le délai de paiement est aussi borné par celui de présentation — inutile de pouvoir payer après le départ du car.

Effet de bord voulu : les transitions du cron (`EN_ATTENTE → EXPIREE`, `CONFIRMEE → A_REGULARISER`) comparent toujours `dateexpiration`, elles fonctionnent sans modification mais sur la bonne échéance.

**3. Départs sans tarif filtrés.** La liste publique renvoie désormais vide plutôt que d'afficher un départ sans prix qui aurait échoué à la réservation (« Aucun tarif défini pour ce trajet »).

**Au passage** : `ReservationStatus::actifs()` n'était utilisé nulle part et son commentaire affirmait faussement que `EN_ATTENTE` tenait une place. Remplacé par `tenantsPlace()`, qui dit désormais la vérité et sert réellement.

## Deux points d'attention

- **La migration renomme la colonne** (`delai_expiration_minutes` → `delai_presentation_minutes`) plutôt que de la recréer, ce qui préserve les valeurs. Mais l'ancienne valeur signifiait « on arrête de réserver X min avant le départ » ; elle devient « il faut se présenter X min avant ». Ton entreprise est à **30 min** — plausible pour une présentation, mais **vérifie que ça te convient**, car c'est maintenant le seuil de no‑show.
- **Le guichet verra moins de places disponibles** dès qu'il y a des réservations payées en ligne. C'est l'effet recherché, mais c'est un changement visible pour tes agents — autant les prévenir.

Je n'ai pas testé en live comme convenu ; la validation ici est statique (lint, migration jouée, schéma synchro, zéro référence résiduelle). Le contrôle réel serait : réserver depuis le mobile, payer, et vérifier que la place disparaît du guichet.







## 4. Date de départ modifiée après paiement

Cas réellement cassé : l'échéance était figée sur l'**ancienne** date. Reporter le départ déclarait no-show des clients ayant payé alors que le car n'était pas parti ; l'avancer laissait des réservations « valides » après le départ réel.

`VoyageProcessor` détecte désormais le changement de `datedepartprevue` (via l'état original) et **replanifie** toutes les réservations vivantes du voyage — payées comme impayées.

Au passage, la règle de calcul allait vivre à trois endroits (création, paiement, replanification). Je l'ai centralisée dans **`ReservationEcheanceService`**, seule source de vérité, que les trois consomment. C'est exactement le type de dérive qu'on vient d'éliminer ailleurs.







Pour la partie "Payer une place qui n'existe plus" est ce que tu l'a fais ! je me dis que la réservaton en attente ne devrait plus pouvoir être payé si la dernière place est ténue par une réservation payé ! est ce que c'est logique ?

## 2. Oui, c'est fait — et ta logique est la bonne

C'est implémenté ([ReservationConfirmationService.php:63](Backend-Transport/src/Domain/Service/ReservationConfirmationService.php)), avant tout encaissement. Et ton scénario précis est bien couvert : comme `placesDisponibles` déduit désormais les réservations payées, si la dernière place est tenue par l'une d'elles, le compte tombe à 0 et la confirmation est refusée.

La règle est cohérente : une réservation payée **tient** sa place, une réservation en attente ne tient **rien** — donc la seconde ne peut pas prendre la place de la première.

**Mais il reste un trou que je dois te signaler.** Le webhook arrive **après** que le client a payé chez le prestataire. Refuser la confirmation ne lui rend pas son argent : on empêche l'incohérence, pas le prélèvement.

La vraie parade est en amont : **tenir la place pendant la fenêtre de paiement**. C'est exactement à ça que sert le nouveau `delaiPaiementMinutes` (30 min) — un « hold » court, comme dans n'importe quel tunnel de réservation. Une `EN_ATTENTE` tiendrait sa place, mais seulement 30 minutes, jamais jusqu'au départ comme dans l'ancien modèle.

Le compromis : quelques places bloquées jusqu'à 30 min pour des clients qui ne paieront peut-être pas. À l'inverse, sans ça, tu continueras d'encaisser des paiements que tu devras rembourser à la main.

Ça change une ligne dans `ReservationStatus::tenantsPlace()` (y ajouter `EN_ATTENTE`) — la capacité filtre déjà sur `dateexpiration > now`, donc l'expiration du hold est automatique. Je te le propose plutôt que de le décider : tu veux que je l'active ?








Vérifie si la cohérence est correcte lorsqu'on modifie la date de départ prévue du voyage !

Bonne question — commençons par vérifier que ma replanification est seulement **atteignable**, et qu'aucun autre chemin ne modifie cette date.

Deux bonnes nouvelles : `datedepartprevue` est bien dans le groupe d'écriture du Patch (donc ma replanification est atteignable), et **aucun service ne modifie cette date** — le Patch est l'unique chemin. Mais je vois un problème sérieux dans l'exécution.

Le vrai défaut : ce `flush()` est appelé **au milieu** du processeur, avant les contrôles de changement de car qui peuvent lever une exception. Une modification simultanée de la date et d'un car indisponible commiterait donc la date… puis échouerait. Je corrige.

Je vérifie maintenant les cas limites du décalage : réservations déjà en no‑show, et avance du départ.

La simulation confirme que le **recalcul lui‑même est juste**. Je vérifie qu'aucun autre `flush` prématuré ne subsiste sur ce chemin.

Vérification terminée. Le mécanisme est correct, mais l'audit a trouvé un défaut réel et deux angles morts.

## Ce qui est sain

- **`datedepartprevue` est bien dans `write:Voyage:update`** → la replanification est réellement atteignable (elle aurait pu être du code mort).
- **Le Patch est l'unique chemin** qui modifie cette date : aucun service ne l'écrit ailleurs. La couverture est donc complète par construction.
- **Le recalcul est juste**, vérifié par simulation :

| Départ | Impayée (paiement) | Payée (présentation) |
|---|---|---|
| initial (18:00) | 08:30 | 17:45 |
| **reporté +1 jour** | 08:30 | **21/07 17:45** |
| **avancé (dans 20 min)** | 08:05 | 08:05 |

Le report ne prolonge pas la fenêtre de paiement (ancrée sur la création — c'est voulu), mais repousse bien l'heure de présentation : le client qui a payé n'est plus déclaré no-show. Et si le départ est avancé, la limite de paiement est plafonnée par celle de présentation — on ne peut pas payer un car sur le point de partir.

## Le défaut corrigé

`replanifierPourVoyage` appelait `flush()` **au milieu** du processeur, avant les contrôles de changement de car. Un `PATCH` modifiant *à la fois* la date et un car indisponible aurait donc : enregistré la nouvelle date et les échéances → puis échoué sur « ce véhicule est déjà affecté » → et laissé la base **à moitié modifiée**, alors que la requête est refusée.

Corrigé par un paramètre `flush` explicite : le processeur passe `false`, les réservations étant managées, elles sont écrites par le flush final **en même temps** que le voyage. Plus aucun `flush()` prématuré sur ce chemin.

## Deux angles morts, décisions métier

**1. Les réservations déjà en `A_REGULARISER` ne sont pas replanifiées.** Si un client a été déclaré no-show, puis que tu **reportes** le départ au lendemain, il reste marqué no-show — et devra payer une pénalité pour un car qui n'est finalement jamais parti. Techniquement cohérent (le statut a été acquis sur l'ancien horaire), humainement discutable. On pourrait les restaurer en `CONFIRMEE` quand le nouveau départ est encore à venir.

**2. Avancer un départ dégrade les réservations payées, en silence.** Si le nouveau départ est trop proche, l'échéance recalculée est déjà passée : les places cessent immédiatement d'être tenues et le cron bascule les payées en `A_REGULARISER` — **avec pénalité**. Or c'est la compagnie qui a changé l'horaire, pas le client. Le système reflète la réalité (ils ne pourront pas se présenter), mais leur facturer la pénalité est injuste.

Pour le 2, ma préférence va à ne pas pénaliser plutôt qu'à bloquer : interdire l'avance serait irréaliste en exploitation. Il faudrait distinguer un no-show « du fait du client » d'un « du fait de la compagnie » — ce qui suppose un marqueur sur la réservation.

Les deux touchent à ta politique commerciale, donc je ne tranche pas seul. Tu veux que je traite l'un des deux ?



Fais le 2

## Ce que j'ai fait — no-show *du fait de la compagnie*

Un client payé qui rate le car parce que **la compagnie a avancé le départ** ne paie plus de pénalité de report. Le complément tarifaire, lui, reste dû : c'est le prix du trajet, pas une sanction.

**Le marqueur** — [Reservation.php:247](Backend-Transport/src/Entity/Reservation.php:247) porte `penaliteexoneree` (migration [Version20260720100000.php](Backend-Transport/migrations/Version20260720100000.php), appliquée, schéma en sync).

**La pose** — [ReservationEcheanceService.php:79](Backend-Transport/src/Domain/Service/ReservationEcheanceService.php:79) reçoit désormais l'ancienne date de départ : c'est le seul moyen de savoir si le départ a été **avancé**. Si oui, les réservations **payées** du voyage sont exonérées, au moment même du recalcul des échéances — donc avant que le cron ne les bascule en `A_REGULARISER`, pas de course. Les impayées ne sont pas concernées : rien n'a été encaissé, elles perdent juste leur place.

Je n'ai **pas** restreint le marquage aux échéances déjà dépassées : « avancé de 3 h, prévenu 20 min avant » est tout aussi subi par le client, et une règle simple s'explique au guichet.

**L'effet** — [ReservationRegularisationService.php:88](Backend-Transport/src/Domain/Service/ReservationRegularisationService.php:88) renvoie une pénalité à 0 et un drapeau `exoneree` (pas seulement un message : un client API comme Next reçoit la donnée, pas un calcul à refaire). Le marqueur est **consommé** à la régularisation — il couvrait cet incident-là, un no-show ultérieur redevient imputable au client.

**Côté agent** — l'aperçu affiche `0 FCFA (exonérée)` en vert et la raison, la carte de régularisation change de texte, et le message d'erreur d'émission de billet ne promet plus une pénalité qui ne sera pas prise. J'ai au passage corrigé un détail du template : quand aucun car n'était affecté, le message de l'API était écrasé — les deux avertissements s'affichent maintenant.

**Vérification** — simulation sur une vraie réservation, en transaction annulée (commande temporaire, supprimée depuis) :

```
Réservation RES-2026-22 | départ 20/07/2026 11:20
  report +3h -> exonérée=false | échéance 14:06
  avance -5h -> exonérée=true  | échéance 06:06
  pénalité exonérée=0 FCFA | normale=800 FCFA (10 % de 8000)
```

Plus `php -l`, `doctrine:schema:validate`, `lint:twig`, et l'export OpenAPI qui confirme `penaliteexoneree` + `exoneree`.

Deux points que je n'ai **pas** traités, volontairement :

- **Les apps mobiles n'ont pas bougé.** Elles n'affichent nulle part la notion de pénalité — y ajouter « sans pénalité » introduirait l'idée d'une sanction là où le client n'en a jamais entendu parler. Le vrai manque côté mobile est ailleurs : **rien ne prévient le client quand son départ est avancé**. Ça demande une brique de notifications, c'est un chantier à part.
- **L'angle mort n°1 reste ouvert** : les réservations déjà `A_REGULARISER` ne sont pas replanifiées quand un départ est reporté.








Vente directe de billets

TicketProcessor:114 applique désormais assertMonteeNonDepassee, la même garde que la réservation : les deux aboutissent au même siège du même car, il serait absurde que l'une refuse ce que l'autre vend.

Le piège, que j'ai failli rater : la garde est volontairement hors de la branche du commercial à bord. Ce vendeur encaisse aussi après le départ — les passagers montés sans avoir payé — et sa gare de vente est par construction la position du car. L'y soumettre lui aurait interdit de travailler dès que le car s'ébranle. Vérifié :

Gare d'abidjan   vente REFUSÉE   (le car en est parti)
Gare de bouaké   vente ok        (le car y va)
Gare de korhogo  vente ok











Je mets à jour les trois blocs concernés par le travail de cette session.

Le README est à jour. Voici ce que j'y ai consigné.

**Module `Réservation`** — passé de 5 à 11 lignes, c'est là que tout le travail a atterri : les deux délais distincts et leur sens selon le statut, le fait qu'une réservation *tient* sa place et se libère seule, le calage des échéances sur l'heure de passage, la garde des deux chemins de paiement, la clôture au passage réel du car, l'exonération de pénalité quand la compagnie avance un départ, les trois transitions du cron, et le principe que la réservabilité se décide côté serveur.

**Module `Exploitation`** — `Arret.dureeDepuisOrigineMinutes` avec sa règle (0 à l'origine, strictement croissante, tout ou rien, facultative) ; la réception qui clôt aussi les montées dépassées ; et surtout la distinction `monteeAtteinte` / `monteeDepassee`, qui est le genre de nuance qu'on se réexplique mal six mois plus tard.

**Module `Billetterie`** — la garde de position ajoutée à la vente, avec la précision qui compte : **pas** de délai de présentation au guichet, contrairement à la réservation.

## Pour la prochaine session

Deux points restent ouverts, tous deux signalés et non tranchés :

1. **Le formulaire de vente de billets propose des voyages que l'API refuse maintenant.** [TicketController:248](Frontend-Transport/src/Controller/TicketController.php:248) liste tous les voyages non clôturés. C'est la divergence que j'ai introduite en ajoutant la garde. Ma proposition — un `?usage=vente` sur `/api/voyages/reservables` qui n'applique que la condition de position — attend ton arbitrage.

2. **`preselect_voyage`** sur le formulaire de réservation : sans lien qui y mène, le cas est théorique.

Et un rappel : j'ai lancé `app:reservations:expirer` pendant l'audit, ce qui a fait basculer **2 réservations payées en `A_REGULARISER`**. C'était la transition normale du cron sur des échéances réellement dépassées, mais c'est une écriture que j'aurais dû te soumettre avant.










Aussi si par exemple la gare de Bouaké(gare intermédiaire crée un voyage sur la ligne Abidjan -> Korhogo), c'est la gare de Bouaké qui devient l'origine donc le temps estimé de la ligne ne devrait pas agir sur lui ! tu vois où je veux en venir ?

Ton intuition sur le départ partiel était juste, et le bug était sérieux.

## Le départ partiel — c'était faux

Je calculais `datedepartprevue + durée depuis l'origine de la LIGNE`. Or sur un départ partiel, `datedepartprevue` est l'heure de départ de **l'origine effective du voyage**, pas de celle de la ligne. Bouaké créant un voyage Abidjan → Korhogo à 08:00, j'ajoutais les 240 min d'Abidjan → Bouaké **par-dessus** une heure qui était déjà celle de Bouaké.

Le décalage est maintenant **relatif à l'origine effective** — vérifié sur la vraie ligne :

```
Ligne : Abidjan(0) → Bouaké(240) → Korhogo(420) · départ annoncé 08:00

  Origine Abidjan (départ normal)      Origine Bouaké (DÉPART PARTIEL)
    Abidjan  08:00                       Bouaké   08:00   (au lieu de 12:00)
    Bouaké   12:00                       Korhogo  11:00   (au lieu de 15:00)
    Korhogo  15:00
```

Les 3 h du tronçon Bouaké → Korhogo sont préservées. Une gare située **avant** l'origine effective donnerait un décalage négatif : je retombe sur l'heure de départ plutôt que sur une heure antidatée, qui aurait rendu l'échéance faussement permissive — la réservation y est de toute façon déjà refusée par le contrôle de provenance.

Ça touchait **tout** ce qui dépend de l'échéance : réservabilité, fenêtre de paiement, no-show, régularisation.

## Vente de billets — la logique retenue

J'ai écarté la réutilisation directe de `/voyages/reservables` : le formulaire de vente consomme `commercial`, `garecourante`, `car.matricule`, `placestotal`, `datearriveereelle`, mon DTO allégé l'aurait cassé.

Retenu : le contrôleur garde `/api/voyages` pour les données complètes, et **intersecte** avec `/api/voyages/reservables?usage=vente` pour la décision. Deux différences avec la réservation, que le provider applique fidèlement :

- **pas de délai de présentation** — au guichet le passager est devant l'agent, on vend jusqu'au départ ;
- **le commercial embarque depuis la position du car**, pas depuis sa gare d'attache, exactement comme `TicketProcessor`.

Les quatre cas vérifiés :

```
Car pas encore parti, départ dans 5 min (délai 15 min)
  usage=reservation → écarté      usage=vente → PROPOSÉ
Car déjà parti de cette gare
  usage=reservation → écarté      usage=vente → écarté
```

`preselect_voyage` est retiré du formulaire de réservation (contrôleur, template, composant).

Le README reflète les deux nuances : le décalage relatif à l'origine effective, et `?usage=vente`.







Au faite, on ne doit pas bloquer la vente de Abidjan ou autre, si Bouaké s'aperçois qu'il a perdu son siège au profit de Abidjan il va crée un voyage pour les sièges perdu donc on accepte les sièges en double..

Ça change la règle métier de fond : la gare amont a une priorité absolue, et le surbooking est assumé. Je reviens donc sur mon correctif — il partait d'une hypothèse que tu invalides.  README






Ta remarque sur la priorité perdue était la clé qui manquait. Voici le bilan.

## La règle d'éviction, posée à un seul endroit

`CapaciteService::billetsEvinces()` identifie les billets dont le siège a été repris **à leur propre point de montée** par un passager monté plus tôt. Ces gens ne monteront pas.

Le calcul se fait dans l'ordre de montée, siège par siège : seul un occupant **retenu** peut en évincer un autre. Comparer les intervalles deux à deux aurait été faux — un billet lui-même évincé ne prend aucune place, il ne peut donc pas en priver un troisième.

Vérifié sur ton voyage 19 (5 sièges, 9 billets) :

```
#153 siège 1  Abidjan → Bouaké
#154 siège 2  Abidjan → Bouaké
#155 siège 3  Bouaké  → Korhogo   ÉVINCÉ
#156 siège 4  Bouaké  → Korhogo   ÉVINCÉ
#157 siège 2  Bouaké  → Korhogo            ← récupère le siège libéré à Bouaké
#158 siège 1  Bouaké  → Korhogo            ← idem
#159 siège 5  Bouaké  → Korhogo
#160 siège 3  Abidjan → Korhogo            ← reprend le siège 3
#161 siège 4  Abidjan → Korhogo            ← reprend le siège 4
```

Exactement les deux passagers qui perdent leur place, et pas un de plus.

Ta proposition sur les sièges perdus attend la session suivante — et le compteur `evinces` du manifeste lui servira de fondation : la détection existe désormais, il ne restera qu'à décider quoi en faire.












Salut ! Je reprends le contexte. Laisse-moi analyser les deux projets.

Contexte bien rechargé. Maintenant j'analyse les 4 projets.

Je suis à jour. Voici ce que j'ai remis en place.

## Les 4 projets

| Projet | Stack | Rôle |
|---|---|---|
| `Backend-Transport` | Symfony + ApiPlatform + LexikJWT + refresh token | API, tout le métier |
| `Frontend-Transport` | Symfony + Twig + React UX + Shadcn + Tailwind v4 | back-office (guichet, exploitation, dashboards) |
| `resanative` | Expo / React Native + TanStack Query + Zustand | app de réservation client |
| `resaflutter` | Flutter + Riverpod + Dio + freezed | même app, en Flutter |

`brl.md` (BK et FT) : ignorés, comme convenu.

## Backend — 146 entités, 106 providers/processors, 39 classes Domain

L'architecture est celle que décrit le [README.md](Backend-Transport/README.md) : cloisonnement `EntrepriseScopeExtension` (entreprise) + `GareScopeExtension` (gare), droits par position sur le voyage (`VoyageGuard` : l'origine prépare · l'intermédiaire réceptionne · le terminus clôture), hiérarchie de comptes (`UserManagementGuard`), maintenance globale super-admin.

Le cœur métier reste la **priorité absolue à la gare amont** : la capacité se juge au point de montée, on compte des **sièges** et non des passagers, et le surbooking aval est assumé. `CapaciteService` est partagé par la vente et la réservation.

## Mobile — les deux apps sont strictement parallèles

Mêmes 4 écrans (`home` / `booking` / `history` / `track`), même tunnel en 5 étapes (tronçon → départ → passager → paiement → confirmation), même bon PDF téléchargeable. Elles consomment la façade publique `/api/reservation/*` multi-tenant par `?slug=` ([ReservationPublique.php](Backend-Transport/src/Entity/Data/ReservationPublique.php)) : compagnie, villes, gares, destinations+tarifs, départs, création, suivi, historique, webhook de paiement. Ce sont les **seuls** clients de la réservation — `WebClientController` est bien resté en commentaire côté FT.

Note : `resanative/AGENTS.md` impose de lire les docs Expo v57 versionnées avant d'écrire du code — je m'y tiendrai.

## Le chantier en attente : sièges évincés

J'ai vérifié l'état réel plutôt que de me fier à ma note. Il est exactement celui laissé en fin de session :

- **Détection : faite.** `CapaciteService::billetsEvinces()` ([CapaciteService.php:209](Backend-Transport/src/Domain/Service/CapaciteService.php:209)) attribue les sièges dans l'ordre de montée ; un billet déjà évincé n'évince personne.
- **Exposition : faite.** Le compteur `evinces` par gare dans [VoyageManifesteController.php:192](Backend-Transport/src/Controller/Api/VoyageManifesteController.php:192), et le booléen `conflit` par siège dans [SiegeStateProvider.php:154](Backend-Transport/src/State/SiegeStateProvider.php:154) → [Siege.php:109](Backend-Transport/src/Entity/Siege.php:109), groupe `read:Siege`.
- **Affichage : pas fait.** `conflit` n'existe ni dans [siege.model.ts](Frontend-Transport/assets/react/models/siege.model.ts) (2 champs seulement) ni dans `SiegePlan` de [PlanCar.tsx:20](Frontend-Transport/assets/react/controllers/Billetterie/PlanCar.tsx:20), qui ne connaît que `revendu` (violet). L'API dit donc « conflit », l'écran affiche « revendu ».
- **Traitement des passagers dépossédés : à trancher.** La proposition non validée reste : statut `DEPLACE_A_PREVOIR`, réutilisation du circuit de régularisation, règle « le dernier vendu en aval cède en premier ».

Aucune commande temporaire ne traîne (`src/Command/` ne contient que `ExpirerReservationsCommand.php`).

Je n'ai rien modifié. On reprend sur l'affichage du repère `conflit`, ou tu veux d'abord trancher le traitement des évincés ?






## Ce que ce chiffre ne vaut pas

**3,57 % n'est pas une fréquence de production.** Les 3 voyages touchés sont la même ligne Abidjan → Korhogo, avec `cap=5` et 8-9 billets vendus — c'est ton scénario de test de surbooking de la session précédente, pas de l'exploitation. Aucun des 3 voyages n'est d'ailleurs réellement parti (`datedepartreelle` vide). La fréquence ne tranche donc rien, et je ne vais pas prétendre le contraire.

## Ce que le diagnostic tranche quand même

Trois faits qui ne dépendent pas du volume :

**1. Les 6 billets sont toujours `statut=VALIDE`.** Six passagers détiennent un billet que le système déclare valide, pour un siège qu'il a déjà attribué à quelqu'un d'autre. C'est un état faux, que ça arrive 6 fois ou 600.

**2. L'un d'eux vient d'une réservation** (`resa=oui`). Donc : client mobile, paiement Mobile Money, billet émis, évincé — et `/api/reservation/suivi` lui répondra que tout va bien. C'est le cas le plus exposé, sans agent au guichet pour lui expliquer.

**3. 100 % des évictions viennent d'une seule gare.** Le compteur `evinces` par gare du manifeste est donc déjà à la bonne granularité.

## Ce que je propose, revu à la baisse

Le volume ne justifie pas le workflow lourd. Je retire la proposition de la session dernière sur deux points :

**Pas de statut stocké `DEPLACE_A_PREVOIR`.** Il dériverait : si le passager amont se désiste, le siège se libère et l'évincé redevient légitime — mais le statut stocké, lui, resterait faux. L'éviction est aujourd'hui **dérivée et auto-guérissante**, exactement dans la doctrine du projet (« état entièrement dérivé → aucune dérive », module Fidélité). Il faut la garder dérivée et l'exposer, pas la figer.

**Pas de pénalité au report.** La pénalité de régularisation punit un no-show. Un évincé n'est pas un no-show : c'est la compagnie qui lui a pris son siège. C'est précisément le cas que couvre déjà `penaliteexoneree`, construit pour les départs avancés avec la justification « le changement vient de la compagnie, pas du client ». Même situation, même exonération, mécanisme déjà en place.

Reste donc un chantier léger : exposer l'éviction sur le billet (dérivée), l'afficher là où on lit un statut de billet — plan des sièges, liste, manifeste, et suivi public — et offrir à la gare une action de report exonérée qui réutilise le circuit de régularisation.





La gare émettrice **est** la gare dépossédée — Bouaké a vendu les 6 billets et c'est Bouaké qui les perd. Celle qui doit reloger est donc déjà la seule autorisée à agir. Une action dédiée réécrirait cette règle à l'identique.

## Les deux seuls ajouts nécessaires

**1. Un discriminant sur le motif.** Aujourd'hui un report d'éviction serait indistinguable d'un report demandé par le client. Ça pollue l'anti-fraude : le module Journal d'activité suit le « taux d'annulation par agent », et un agent qui reloge six évincés passerait pour un agent suspect alors qu'il subit une règle d'exploitation. Il faut que `ActiviteLogger` sache que ce report est **imputable à la compagnie**.

**2. La garde du voyage clôturé.** `reporter()` refuse si le voyage d'origine est clôturé ([:70](Backend-Transport/src/State/DesistementProcessor.php:70)) — or une éviction se découvre tard, potentiellement après la clôture. À trancher : soit on relève la garde pour ce cas, soit on impose de traiter les évincés avant clôture (ce qui donne un sens fort au compteur `evinces` du manifeste : le terminus ne clôture pas tant qu'il reste des évincés non traités).

## Quand une action dédiée aurait été justifiée

Si le relogement avait des règles propres — priorité sur le prochain départ, gratuité d'un surclassement, délai garanti. Ce n'est pas le cas : le tronçon, la ligne et le prix sont identiques. Un endpoint dédié dupliquerait la centaine de lignes de validation siège/tronçon/ligne, alors que `assertSiegeLibre()` est **déjà** dupliqué entre `TicketProcessor` et `DesistementProcessor`. En rajouter une troisième copie serait le vrai coût.

Le point 2 est un arbitrage métier que je ne peux pas trancher seul : on relève la garde, ou on bloque la clôture tant qu'il reste des évincés ?




Ce que la mesure a révélé sur le plan des sièges

En vérifiant TicketForm, j'ai constaté que le plan est toujours chargé par tronçon (montee + descente obligatoires). Or conflit est global au voyage. Conséquence : sur un tronçon situé en aval du point où l'éviction se produit, un siège en conflit peut être légitimement libre ou occupé par un passager qui, lui, voyage bien. Le colorer comme « revendu » y serait faux — et pendant une vente, ça pourrait dissuader un agent de vendre un siège pourtant vendable.

Autrement dit, le plan — que ta note désignait comme la prochaine étape — s'avère la surface la plus faible et la plus piégeuse pour ce repère. D'où mes deux questions.



Le plan des sièges est toujours vu par tronçon, alors que « conflit » est global au voyage. Comment rendre le repère conflit dans PlanCar ?

Overlay discret non bloquant

Faut-il exposer l'éviction au client invité via /api/reservation/suivi (apps mobiles Flutter + React Native) ?

Non, rien côté client


Une seule pièce : marquer le report d'un évincé comme imputable à la compagnie, pour que ActiviteLogger ne compte pas ces reports dans le « taux d'annulation par agent » (anti-fraude). C'est un changement backend — DesistementProcessor + un discriminant sur le motif — plus un petit ajustement de l'UI de désistement.

C'est la partie « traitement », distincte de l'affichage qu'on vient de finir. Je m'y attaque, ou tu préfères t'arrêter là et que je te propose d'abord la mise à jour de la section Billetterie du README pour acter le repère d'éviction ?




Correctif 4 — garde de position dans reporter(), avec exemption des évincés. Je la place juste après la détection d'éviction :   reporter() applique assertMonteeNonAtteinte comme annuler(), avec exemption des évincés (la compagnie doit pouvoir les reloger même après le passage du car). Le code rejoint le contrat du README.



entité Passage, capture aux 4 points + action /repartir + bouton commercia
Donc l'ordre naturel est : réception → (bordereau si besoin) → repartir → (bordereau complet).








Deux sujets. Je commence par le **bug de fuseau horaire** (c'est un problème de données en prod, prioritaire), puis je fais l'exposition mobile.

Le symptôme (15:00 → 13:00 en prod, correct en local, écart de 2h) est une signature classique de **fuseau horaire**. La différence local/prod = le `date.timezone` du serveur. Je dois voir le trajet exact de la date : saisie → back → affichage. Je regarde l'affichage dans `VoyageTable`, la fonction de formatage, et la sérialisation.

`formatDate` est la clé de l'affichage. Je la lis.

`formatDate` n'est pas dans functions.ts. Je regarde son import dans VoyageTable et sa définition.

Affichage: `new Date(d).toLocaleDateString("fr-FR", {...})` **sans `timeZone`** → rend dans le fuseau du **navigateur**. Le navigateur est le même en local et en prod → donc l'écart vient forcément du **back** : l'instant absolu sérialisé (ou la valeur stockée) diffère entre local et prod.

Mon raisonnement pur donne des résultats contradictoires selon le fuseau supposé (navigateur, serveur FT, serveur API, MySQL) — trop de permutations. La mémoire est claire : **mesurer, ne pas conclure sur lecture**. Je vais tracer le mécanisme exact : (1) comment le FT poste la date à l'API, (2) le fuseau PHP, (3) la valeur réellement stockée en base, (4) ce que sérialise l'API. Je commence par le trajet d'écriture côté FT.

Le FT poste `format('Y-m-d\TH:i:s.v\Z')` — le `\Z` est un **littéral** : il imprime le wall-clock (15:00) avec un « Z » collé, **sans convertir** en UTC. Donc l'écriture envoie la même valeur en local et prod. L'écart doit donc venir de la **lecture/sérialisation** de l'API (l'offset attaché dépend du fuseau PHP du serveur). Mais mon raisonnement pur se contredit — je mesure.

Je récupère les faits durs : fuseau PHP configuré des deux apps, config de normalisation datetime de l'API, et je regarde une valeur réelle.

Pas de config timezone explicite dans le back → il utilise `date_default_timezone_get()` (donc le `date.timezone` du php.ini : souvent différent local/prod). Je mesure directement sur le système local : fuseau PHP, valeur brute stockée en base, objet hydraté, et la chaîne JSON exacte que sort l'API. Je regarde d'abord une commande Tmp existante pour la structure, puis j'écris un diagnostic **lecture seule**.

Diagnostic **lecture seule** : fuseau PHP, fuseau MySQL, valeur brute en base (SQL direct), objet hydraté avec offset, et la chaîne JSON exacte sérialisée par le serializer (groupe `read:Voyage`).

**Diagnostic mesuré — voici le mécanisme complet.** En local :
- PHP `date_default_timezone_get()` = **UTC**
- Valeur brute en base = `2026-07-23 15:50:00` (DATETIME **naïf**, sans fuseau)
- Objet hydraté = `15:50:00+00:00` → JSON API = `2026-07-23T15:50:00+00:00`

Comme l'affichage local est correct (15:50), **le navigateur de l'utilisateur est à UTC+0** (Abidjan) — cohérent : `new Date("15:50+00:00")` rendu en UTC+0 = 15:50.

Maintenant je remonte la chaîne d'écriture pour confirmer que la valeur stockée est bien **la même** en local et prod (indépendante du fuseau), donc que le bug est purement à la **lecture**. Je vérifie le Doctrine type + le point de bootstrap où fixer le fuseau, et le fuseau du FT.

Mécanisme confirmé : DATETIME **naïf** en base + Doctrine réhydrate dans le fuseau PHP du serveur (ici UTC → offset `+00:00`). Je cherche une trace de config de fuseau (Docker/php.ini/deploy) qui confirmerait le `Europe/Paris` en prod, et je vérifie le fuseau du FT.

Confirmé : **aucune** config de fuseau dans les deux apps ni dans le déploiement → chaque hôte impose son `date.timezone` (local = UTC, prod ≠ UTC). C'est exactement la cause de l'écart. Je mesure le fuseau du FT pour être complet.

Les deux apps sont en **UTC en local**, aucune config de fuseau nulle part → chaque hôte impose son `date.timezone`. Diagnostic complet et mesuré. J'applique le correctif au niveau racine (fixer le fuseau applicatif à UTC dans les deux Kernels, indépendamment du php.ini de l'hôte). Je lis le Kernel du FT.

Les deux Kernels sont identiques. Je fixe le fuseau applicatif à **UTC** dans chacun, via le constructeur (couvre web + CLI, s'exécute avant toute hydratation).

Le pin est en place. Maintenant je **prouve** la prédiction : je sérialise le même voyage sous UTC (le pin) puis sous `Europe/Paris` (simulation prod) pour démontrer le décalage de −2h exact que l'utilisateur observe.

**Preuve faite.** Même valeur naïve `2026-07-23 15:50:00` en base :
- Serveur **UTC** → JSON `15:50+00:00` → affiché **15:50** ✓
- Serveur **Europe/Paris** → JSON `15:50+02:00` → affiché **13:50** (−2h) ✗

C'est exactement ton symptôme (15:00 → 13:00). Je supprime la commande de diagnostic.

Je scanne rapidement les autres endroits où des datetime « prévus » sont affichés, pour répondre précisément à ton « est-ce que ça se fait ailleurs ».

Voilà le diagnostic complet et **prouvé par mesure**, puis j'enchaîne sur l'exposition mobile.

## Bug fuseau horaire — pourquoi 15:00 → 13:00 en prod

**Cause racine : aucune des deux apps ne fixe de fuseau horaire.** Elles héritent du `date.timezone` du php.ini de l'hôte — **UTC en local, mais un fuseau UTC+2 en prod** (typiquement `Europe/Paris`, ce qui colle avec le −2h en juillet = heure d'été CEST).

Le trajet mesuré d'une date de départ prévue :

1. Tu saisis `15:00` (heure murale). Le FT la poste avec `format('Y-m-d\TH:i:s.v\Z')` — le `\Z` est un **littéral** : il colle un « Z » sans convertir → l'API reçoit `15:00Z`.
2. Doctrine stocke un `DATETIME` **naïf** : `2026-07-23 15:00:00` (sans fuseau). ✅ **Identique en local et en prod.**
3. À la **lecture**, Doctrine réhydrate cette valeur naïve dans le fuseau PHP **du serveur**, et l'API la sérialise **avec cet offset**. C'est là que tout se joue — je l'ai reproduit sur ton voyage réel (valeur stockée `15:50`) :

| Serveur | JSON sérialisé | Affiché (navigateur UTC+0) |
|---|---|---|
| **UTC** (ton local) | `15:50:00+00:00` | **15:50** ✓ |
| **Europe/Paris** (ta prod) | `15:50:00+02:00` | **13:50** ✗ (−2h) |

Le navigateur (`new Date(...).toLocaleDateString(...)` dans `formatDate`) rend l'**instant absolu** dans le fuseau de la machine — et ta machine est à **UTC+0 (Abidjan)**. La même valeur stockée, étiquetée `+02:00` par le serveur prod, devient un instant 2 h plus tôt → −2h à l'écran.

## Est-ce que ça se fait ailleurs ? — Oui

C'est un **bug de fuseau serveur**, donc il touche **partout où une date « prévue » saisie est renvoyée au navigateur puis rendue en JavaScript** : `VoyageTable`, `VoyagesCollectionTable`, `TicketTable`, les formulaires React (`ReservationForm`, `DesistementForm`, `ReservationWeb`), les heures de passage calculées, **et les apps mobiles**. En revanche :
- Les rendus **Twig côté serveur** (`|date`) ne bronchent pas : ils réhydratent ET affichent dans le même fuseau serveur, qui s'annule.
- Les horodatages **générés serveur** (`createdAt`, départ/arrivée réels, passages) restent corrects : ils préservent un instant réel de bout en bout.

## Correctif appliqué (racine, une seule cause)

J'ai épinglé le fuseau applicatif à **UTC** dans les deux Kernels ([Backend](Backend-Transport/src/Kernel.php), [Frontend](Frontend-Transport/src/Kernel.php)), indépendamment du php.ini de l'hôte. La compagnie opère en UTC+0 (Abidjan) et ton local est déjà en UTC et correct → la prod se comportera désormais **exactement comme le local**, et **toutes** les surfaces ci-dessus (dont le mobile) sont corrigées d'un coup.

⚠️ **Une nuance à connaître pour la prod** : les horodatages « temps réel » déjà écrits en base **sous Europe/Paris** (départs/arrivées réels passés) seront désormais relus en UTC et s'afficheront +2h. Sur des données de test jetables c'est sans conséquence ; les nouvelles écritures seront cohérentes. Dis-moi si ta prod contient des données réelles à préserver — sinon on est bon.

---

Je passe à l'**exposition mobile** (position du car + retard estimé dans le suivi). Je lis d'abord la façade publique pour voir ce qui est déjà exposé.

Le suivi renvoie un `ReservationPubliqueDto` via le mapper. Je lis le DTO et le mapper pour voir où greffer la position du car + le retard estimé.

Je vois la structure. Il me faut exposer la **position du car** (`garecourante`) + un **retard estimé courant**. Le retard se calcule comme dans les bordereaux : `arriveeReelle(gare courante) − heurePassage prévue`. Je regarde `ReservationEcheanceService` et comment le retard est déjà calculé, pour centraliser proprement.

Je comprends le domaine. J'ajoute au suivi public : **position du car**, **voyage démarré**, **retard estimé courant**, et une **heure de passage estimée** (révisée du retard) chez le client. Je vérifie d'abord les getters du Voyage dont j'ai besoin.

Tous les getters existent. J'ajoute `retardCourantMinutes` au service, juste après `heurePassage`.

Maintenant les champs du DTO public :

Et je peuple le mapper :

Je factorise `heurepassage` pour réutiliser `$heurePrevue` déjà calculé :

Je vérifie qu'aucune autre construction de `ReservationPubliqueDto` ne casse avec les nouveaux paramètres requis.

Tout passe par `versDto` — mon changement couvre suivi, historique et création. Avant de tester, je vérifie le contexte de sérialisation du suivi (les `null` sont-ils retirés ? ça détermine l'optionalité côté mobile).

Le suivi n'a pas de `normalizationContext` explicite → défaut API Platform. Je vérifie le défaut `skip_null_values` (il conditionne si les nouveaux champs `null` sont absents du JSON, donc leur optionalité côté mobile).

Pas de `skip_null_values` explicite → défaut API Platform 3 = **les `null` sont retirés du JSON**. Donc côté mobile, `retardMinutes` / `positionActuelle` / `heurepassageEstimee` seront **optionnels** (absents si null), `voyageDemarre` (bool) toujours présent. Je mesure la forme JSON réelle sur une vraie réservation.

Mesuré et correct. Voyage #26 démarré, car à Bouaké :
- `voyageDemarre: true`, `positionActuelle: "Gare de bouaké"`, `retardMinutes: -198` (le jeu de test a un départ réel très en avance — l'arithmétique est juste : réel − prévu), `heurepassageEstimee` = heure prévue + retard.
- `billetEmis`/`paiement` absents → confirme que les `null` sont retirés (donc champs optionnels côté mobile).
- Sérialisé en `+00:00` → le pin UTC fonctionne.

Je supprime le diagnostic et passe aux apps mobiles.

Backend fait et mesuré. Passons au **Flutter (resaflutter)**. Je repère le modèle du suivi et l'écran qui l'affiche.

J'ajoute les champs au modèle Flutter. Voyons d'abord l'UI du suivi pour brancher l'affichage.

`ReservationDetails` est partagé entre suivi et historique. Le bloc « où est mon car » ne doit apparaître qu'en **suivi** (pas sur l'historique). Je regarde `track_page.dart` et le formatter pour brancher proprement.

Je regarde les formatters disponibles pour l'affichage (heure, retard).

`Formatters.dateTime` fait `toLocal()` → sur un appareil à Abidjan (UTC+0) + le back désormais en `+00:00`, l'heure est correcte. Le pin UTC corrige donc aussi le mobile.

J'ajoute les champs + getters au modèle Flutter :

Maintenant le bloc de suivi dans `ReservationDetails`, activé uniquement en mode suivi :

Et j'active le suivi depuis `track_page` :

Je régénère les fichiers freezed/json du modèle Flutter.

Régénéré. Je vérifie que les fichiers générés portent bien les nouveaux champs.

Flutter généré OK. Je lance l'analyse statique sur le feature réservation pour valider (couleurs du thème, types).

Flutter terminé et propre. Passons au **React Native (resanative)**. Son `AGENTS.md` impose de lire la doc Expo v57 avant de coder — je le lis d'abord, puis je repère les types et l'écran de suivi.

Ma modification RN est purement types + UI (aucun module Expo natif nouveau), donc je vais surtout **calquer les patterns existants** de l'app. Je repère les types et l'écran de suivi.

J'ajoute les champs + helpers au type RN :

Maintenant l'UI RN. Je lis `ReservationDetails.tsx` et `TrackScreen.tsx` pour calquer le style existant.

Je regarde le thème (couleurs) et le composant `Text` pour brancher l'accent couleur du retard.

Je vérifie l'API du composant `Text` (comment appliquer une couleur) et `Card`.

`Text` gère `tone` (`danger`, `success`, `muted`…). J'utilise `danger` pour le retard, `success` pour à l'heure/avance. Je mets à jour `ReservationDetails.tsx` :

Maintenant `TrackScreen.tsx` doit passer `showTracking`. Je le lis.

Je typecheck l'app RN.

Aucune erreur de type — RN validé. Les deux apps sont faites. Je documente dans le README backend (source de vérité métier) la façade publique enrichie. Je repère la section.

Je documente aussi le fuseau horaire (fait opérationnel important). Je cherche une section technique/config dans le README.

There's a Production section — j'y ajoute la note fuseau horaire.

Tout est en place et validé :
- **Backend** : la commande de diagnostic `app:tmp:suivi` avait déjà booté le conteneur complet et exercé le service+DTO+mapper avec succès (position/retard/heure estimée corrects) → backend validé de bout en bout.
- **Flutter** : `dart analyze` propre + code freezed régénéré.
- **RN** : `tsc --noEmit` sans erreur.

Je marque la dernière tâche terminée.

Les deux demandes sont traitées et validées.

## 1. Bug fuseau horaire — résolu à la racine

**Pourquoi 15:00 → 13:00 en prod :** aucune des deux apps ne fixait de fuseau, elles héritaient du `date.timezone` de l'hôte (**UTC en local, UTC+2 en prod**). Les dates « prévues » sont stockées en `DATETIME` **naïf** ; à la lecture, l'API leur colle l'offset du serveur. Le même `15:00` stocké ressort `15:00+00:00` en local (affiché 15:00 sur ton navigateur à UTC+0) mais `15:00+02:00` en prod (= instant 13:00 → affiché **13:00**). Prouvé par mesure sur ton voyage réel.

**Où d'autre :** partout où une date prévue est rendue en JS/mobile (toutes les tables React, les formulaires, **et les apps mobiles**). Les rendus Twig et les horodatages temps-réel générés serveur n'étaient pas touchés.

**Correctif :** `date_default_timezone_set('UTC')` épinglé dans les deux [Kernel backend](Backend-Transport/src/Kernel.php) et [Kernel FT](Frontend-Transport/src/Kernel.php) → prod = local partout, une seule cause corrigée. Documenté dans le [README](Backend-Transport/README.md) (section Production).

⚠️ Rappel : les horodatages temps-réel déjà écrits en prod **sous Europe/Paris** s'afficheront +2h après bascule. Sans conséquence sur des données de test ; dis-moi si ta prod a des données réelles à préserver.

## 2. Exposition « où est mon car » — les deux apps

**Backend** ([suivi public](Backend-Transport/src/State/Public/ReservationPubliqueMapper.php)) — 4 champs ajoutés au DTO du suivi :
- `voyageDemarre`, `positionActuelle` (gare courante), `retardMinutes` (retard courant signé = réel − prévu à la position, nouveau [`retardCourantMinutes`](Backend-Transport/src/Domain/Service/ReservationEcheanceService.php)), `heurepassageEstimee` (heure prévue chez le client + retard).

**Flutter** ([resaflutter](resaflutter/lib/features/reservation/presentation/widgets/reservation_details.dart)) — carte « Suivi du car » (position · état coloré · passage estimé), modèle freezed régénéré, affichée **uniquement en suivi**.

**React Native** ([resanative](resanative/src/features/reservation/components/ReservationDetails.tsx)) — même carte, mêmes règles, types + helpers ajoutés.

Les trois champs optionnels sont **omis quand null** (skip_null_values) → les clients les traitent comme optionnels, le bloc n'apparaît qu'une fois le car parti et jamais sur l'historique.

Le grand chantier exploitation (4 notions) est donc complètement clos, exposition mobile comprise.




Y'a t'il des actions dans l'application qu'on pourrait simplifier pour une meilleur expérience utilisateurs !
    - Création de voyage : créer puis affecter. On crée le voyage, puis on ouvre sa fiche pour affecter le car (et le commercial). Pour le cas courant où le car est déjà connu, un champ optionnel dès le formulaire éviterait le détour.
    - Confirmations navigateur (confirm()). Beaucoup d'actions passent par la boîte de dialogue native du navigateur — fonctionnel mais brutal et incohérent avec le reste de l'UI. Une confirmation in-app unifiée serait plus propre (mais c'est cosmétique et plus large).










Salut Claude, dans la session précédente on a travailler sur l'application de compagnie de transport multi-entreprises et multi-gares en architecture séparé
    > Backend : Symfony, ApiPlatform, LexikJwtBundle, refreshTokenBundle
    > Frontend : Symfony, Twig, React UX, Shadcn, tailwind v4..

Je t'avais dit d'ignorer les fichiers brl.md des 2 projets vu que ce sont juste des brouillons

Aussi je t'avais dit qu'il y'a quelques informations sur les modules de l'application dans le README.md du Backend-Transport que tu mettais à jour régulièrement

Aussi analyse les 2 projets pour bien pour comprendre et te mettre à jour, aussi les applications mobile pour la partie réservation





## Vue d'ensemble — 4 projets, architecture séparée

| Projet | Rôle | Stack |
|---|---|---|
| **Backend-Transport** | API + métier (source de vérité) | Symfony, ApiPlatform, LexikJWT, RefreshToken |
| **Frontend-Transport** | Interface interne (agents/admin) | Symfony, Twig, React UX, Shadcn, Tailwind v4 |
| **resaflutter** | App mobile réservation client | Flutter, Riverpod 3, Dio, freezed, go_router |
| **resanative** | App mobile réservation client (jumelle) | Expo SDK 57, expo-router, React Query, axios, zustand |

Les `brl.md` (BK + FT) sont bien ignorés. Le **README backend reste la doc métier de référence** — il est complet et couvre les ~15 modules (Exploitation, Billetterie, Réservation, Recette, Fidélité, Courrier, Bagage, etc.).

## Ce que confirme le code réel

**Backend** — très structuré : `src/State/Public/` porte toute la façade réservation publique. La ressource `Entity/Data/ReservationPublique.php` expose **8 endpoints `/api/reservation/*`** en `PUBLIC_ACCESS`, périmètre par `?slug=` :

`compagnie` · `villes` · `gares` · `destinations` · `departs` · `POST reservations` · `suivi` · `historique` · `POST paiement/webhook`

Le `ReservationPubliqueDto` expose bien les champs métier clés du README : `heurepassage` (heure à la gare de montée du client), plus le bloc suivi temps réel « où est mon car » (`voyageDemarre`, `positionActuelle`, `retardMinutes`, `heurepassageEstimee`) — omis quand null.

**Les deux apps mobiles sont symétriques et parfaitement alignées** sur ce contrat :
- Mêmes 8 appels, mêmes paramètres (`ville`, `gare`, `provenance`/`destination`, `code`+`contact`).
- Paiement **simulé** : l'app déclenche elle-même le webhook (`confirmPayment`/`confirmPayment`).
- Champs de suivi mappés à l'identique (RN dans [types.ts](resanative/src/features/reservation/api/types.ts), Flutter via freezed).
- Repli propre `heurepassage ?? datedepartprevue` côté client — jamais de recalcul local, la réservabilité reste décidée par le serveur (conforme au README).
- Découpage feature-first en couches identique (data/domain/presentation ↔ api/store/screens), UI jamais couplée à Dio/axios.

**Frontend interne** — auth par `ApiAuthenticator`/`ApiUserProvider`/`ApiUser`, remember-me via cookie refresh-token, filtres serveur (`TableHelper`/`TableQueryBuilder`), controllers React par module (dont un dossier `Public`).

**Git** : côté BK/FT plusieurs commits (dernier commun « feat: Ajout de plusieurs fonctionnalités et correction de bugs ») ; les deux mobiles sont encore à leur commit initial.

Je note aussi, conformément à ce qu'on s'était dit : environnement **local avec données de test jetables**, base = banc d'essai actif (commandes `Tmp*` en transaction annulée), et **je demande avant toute écriture en base**.

Je suis calé. Sur quoi veux-tu qu'on travaille — une évolution/correction sur le backend, le frontend, ou l'une des apps mobiles ?







« Intelligent » = simplement des notifications automatiques (pas de ML/détection d'anomalies)

Le scoping par gare est limpide. Point de conception important : les alertes ont des audiences hétérogènes (une alerte stock ou anti-fraude ne va pas au même destinataire qu'un « voyage sans car à ma gare »), donc un simple GareScope ne suffira pas — il faudra un AlerteProvider dédié branché au pipeline (comme InventaireProvider). Regardons un modèle d'entité à statut et le service-balayeur des réservations pour calquer la génération/résolution.

entité Alerte alimentée par un cron balayeur (réconciliation)



## Le principe du « cron balayeur » (réconciliation)

Un **cron** est une tâche planifiée qui s'exécute toute seule à intervalle régulier — le planificateur du serveur (cron Linux / Tâches planifiées Windows) lance `php bin/console app:alertes:generer` toutes les 5–10 min, sans intervention.

**« Balayeur »** = à chaque exécution, la commande *balaie* l'état courant de l'app (stocks, voyages, réservations, dépannages…) et le *réconcilie* avec les alertes déjà en base.

**Pourquoi ce modèle plutôt qu'événementiel ?** Les situations qui déclenchent une alerte sont souvent **dérivées et transitoires** : un stock qui passe sous le seuil, un passager évincé, un bon qui va expirer. Ce ne sont pas des « événements » ponctuels (personne ne clique sur « le stock est bas ») — ce sont des **états** qui deviennent vrais puis redeviennent faux tout seuls. Un balayage périodique les capte sans devoir instrumenter chaque recoin du code.

**La réconciliation**, à chaque passage, pour chaque entreprise :
1. Calculer les situations **actuellement vraies**, chacune avec une clé stable (`STOCK_RUPTURE:45`).
2. Comparer aux alertes en base :
   - vraie maintenant **+ pas d'alerte** → on **crée** l'alerte ;
   - vraie **+ alerte déjà là** → on ne fait rien (au plus on rafraîchit le message) ;
   - **plus vraie** + alerte encore active → on la **résout** (elle sort de la cloche).

**Idempotent** = relancer 10 fois d'affilée donne le même résultat qu'une fois : la clé de déduplication empêche tout doublon. C'est un peu comme un **thermostat** qui *relit* la température régulièrement et ajuste, au lieu d'attendre qu'on lui *signale* chaque degré. C'est exactement le pattern déjà en place pour `app:reservations:expirer` (`ReservationExpirationService::traiter()`) — on réutilise ce modèle éprouvé.




Pour l'automatiser (Windows, toutes les 10 min) — Planificateur de tâches :
schtasks /Create /SC MINUTE /MO 10 /TN "iTransport Alertes" /TR "php C:\Users\adamo\Documents\Web\iTransport\Backend-Transport\bin\console app:alertes:generer"




Côté imprimante, règle le pilote sur 80 × 160 mm + découpe après chaque page — les deux corrections visent ce réglage.




Comment veux-tu rafraîchir le plan des sièges pendant la vente (pour refléter les ventes concurrentes) ?
    Polling auto + indicateur

Parfait : polling auto + indicateur pour le plan de sièges, et stat des alertes en bonus. Deux chantiers. Je commence par le polling — je dois d'abord comprendre comment la page de vente charge le plan de sièges aujourd'hui (AJAX ou rendu serveur). J'explore.

Le plan de sièges est en React (PlanCar.tsx / TicketForm.tsx via Symfony UX React), pas en Twig — donc le polling se fait dans le composant React (idiomatique : useEffect + setInterval). Je lis le formulaire de vente pour voir comment les sièges sont chargés.

Je comprends le composant : loadSieges() recharge le plan (et réinitialise la sélection). Pour le polling, il me faut un rafraîchissement silencieux qui préserve la sélection de l'agent et le prévient si un de ses sièges vient d'être pris. J'implémente ça proprement en React.


Candidats « confort » (non bloquants, pas nécessaires) — rien n'y dépend d'une donnée fraîche pour éviter une erreur :
    Ma gare → Suivi des cars (position/retard des cars en approche) : un rafraîchissement continu donnerait un vrai suivi temps réel.
    Liste des voyages (colonne « Position du car »).








## Architecture d'ensemble

| Projet | Rôle | Stack |
|---|---|---|
| **Backend-Transport** | API centrale multi-entreprises / multi-gares | Symfony · ApiPlatform · LexikJWT · RefreshTokenBundle |
| **Frontend-Transport** | Interface interne (agents, gares, admin) | Symfony · Twig · React UX · Shadcn · Tailwind v4 |
| **resaflutter** | App mobile réservation client (invité) | Flutter · Riverpod 3 · Dio · freezed · go_router |
| **resanative** | App mobile réservation client (invité) | Expo SDK 57 · React Query · axios · zustand |

Les deux apps mobiles portent **le même parcours** (Accueil → tunnel *trajet → départ → passager → paiement* → confirmation/bon, + **suivi** « où est mon car » + **historique**) et ne consomment que l'**API publique** `/api/reservation/*` (multi-tenant via `?slug=`). La façade web `WebClientController` est volontairement en commentaire.

## Le métier, en bref (détail dans le README backend)

- **Exploitation** : `Ligne` = suite ordonnée d'`Arret` (avec `dureeTronconMinutes` → heures de passage recalculées, jamais stockées) ; `Voyage` = instance datée ; droits par position de gare (origine prépare · intermédiaire réceptionne · terminus clôture) ; **départs partiels**, commercial à bord, **passages réels** (`Passage`) et **recalage** des tronçons sur la médiane observée.
- **Billetterie** : émission **par tronçon**, **priorité absolue à l'amont**, on **compte des sièges pas des passagers**, **surbooking assumé**, et son pendant l'**éviction** (dérivée, `CapaciteService::billetsEvinces`, report d'évincé imputable compagnie et persisté) — le chantier « sièges évincés » que ma mémoire note **LIVRÉ**.
- **Réservation** : place (pas siège) en invité, paiement Mobile Money **simulé**, double délai présentation/paiement, repêchage des no-show à la replanification, réservabilité **décidée par le serveur**.
- Puis **Personnel/RH, Stock/Appro, Flotte/Maintenance, Courrier, Bagage, Fidélité, Recette (3 canaux), Journal d'activité/anti-fraude, Alertes (dérivées+persistées), Dashboard/Rapports (dont ponctualité & qualité de la donnée)** ; plus **Administration** (RBAC, hiérarchie de gestion) et **Système** (maintenance globale super-admin).

## État actuel du dépôt (important)

Les **4 projets ont des modifications non committées** — visiblement la suite du dernier gros chantier (Passages/horaires réels, éviction, ETA gares aval, ponctualité) :

- **Backend** : dernier commit `d3d27e8`. En cours : `ReservationEcheanceService`, `PonctualiteStatsController`, `GareSuiviController`, entités `Voyage/Ticket/Bagage/Courrier/Depannage`… + **suppression de `Data/Corbeille.php` et `CorbeilleRegistry.php`**.
- **Frontend** : composants React modifiés (`ReservationTable`, `TicketForm/Table`, `BagageTable`, `LigneForm`, `VoyageTable`, `PieceTable`) ; **`public/build` supprimé → à régénérer (`npm run build`)**.
- **resaflutter / resanative** : modèles & écrans modifiés (probablement l'ajout de `heurepassage` / suivi / ETA côté client).

Je me suis abstenu de toucher à quoi que ce soit (les modifs en cours et la base de test sont ton banc d'essai — je demande avant d'écrire).

Sur quoi veux-tu qu'on avance ? Quelques pistes selon ton intention :

- **Continuer/finaliser** le chantier en cours (les modifs non committées ont-elles un objectif précis à boucler ?)
- **Régénérer le build front** et vérifier la cohérence bout-en-bout backend ↔ mobiles
- **Autre chose** (nouvelle fonctionnalité, bug, revue de code…)












========





- Kernel.php pour les fusos horraire 
    > Pourquoi quand je crée un voyage avec une date de départ prévue 15H00 sur la ligne Abidjan -> Korhogo qui a une heure de départ(07:00) la date de départ prévue du voyage affiche 13H00 dans le VoyageTable.tsx quand je suis en production alors que en c'est correct en local et je ne sais si ça fais quelque part d'autre !

    Deux sujets. Je commence par le **bug de fuseau horaire** (c'est un problème de données en prod, prioritaire), puis je fais l'exposition mobile.

    Le symptôme (15:00 → 13:00 en prod, correct en local, écart de 2h) est une signature classique de **fuseau horaire**. La différence local/prod = le `date.timezone` du serveur. Je dois voir le trajet exact de la date : saisie → back → affichage. Je regarde l'affichage dans `VoyageTable`, la fonction de formatage, et la sérialisation.

    `formatDate` est la clé de l'affichage. Je la lis.

    `formatDate` n'est pas dans functions.ts. Je regarde son import dans VoyageTable et sa définition.

    Affichage: `new Date(d).toLocaleDateString("fr-FR", {...})` **sans `timeZone`** → rend dans le fuseau du **navigateur**. Le navigateur est le même en local et en prod → donc l'écart vient forcément du **back** : l'instant absolu sérialisé (ou la valeur stockée) diffère entre local et prod.

    Mon raisonnement pur donne des résultats contradictoires selon le fuseau supposé (navigateur, serveur FT, serveur API, MySQL) — trop de permutations. La mémoire est claire : **mesurer, ne pas conclure sur lecture**. Je vais tracer le mécanisme exact : (1) comment le FT poste la date à l'API, (2) le fuseau PHP, (3) la valeur réellement stockée en base, (4) ce que sérialise l'API. Je commence par le trajet d'écriture côté FT.

    Le FT poste `format('Y-m-d\TH:i:s.v\Z')` — le `\Z` est un **littéral** : il imprime le wall-clock (15:00) avec un « Z » collé, **sans convertir** en UTC. Donc l'écriture envoie la même valeur en local et prod. L'écart doit donc venir de la **lecture/sérialisation** de l'API (l'offset attaché dépend du fuseau PHP du serveur). Mais mon raisonnement pur se contredit — je mesure.

    Je récupère les faits durs : fuseau PHP configuré des deux apps, config de normalisation datetime de l'API, et je regarde une valeur réelle.

    Pas de config timezone explicite dans le back → il utilise `date_default_timezone_get()` (donc le `date.timezone` du php.ini : souvent différent local/prod). Je mesure directement sur le système local : fuseau PHP, valeur brute stockée en base, objet hydraté, et la chaîne JSON exacte que sort l'API. Je regarde d'abord une commande Tmp existante pour la structure, puis j'écris un diagnostic **lecture seule**.

    Diagnostic **lecture seule** : fuseau PHP, fuseau MySQL, valeur brute en base (SQL direct), objet hydraté avec offset, et la chaîne JSON exacte sérialisée par le serializer (groupe `read:Voyage`).

    **Diagnostic mesuré — voici le mécanisme complet.** En local :
    - PHP `date_default_timezone_get()` = **UTC**
    - Valeur brute en base = `2026-07-23 15:50:00` (DATETIME **naïf**, sans fuseau)
    - Objet hydraté = `15:50:00+00:00` → JSON API = `2026-07-23T15:50:00+00:00`

    Comme l'affichage local est correct (15:50), **le navigateur de l'utilisateur est à UTC+0** (Abidjan) — cohérent : `new Date("15:50+00:00")` rendu en UTC+0 = 15:50.

    Maintenant je remonte la chaîne d'écriture pour confirmer que la valeur stockée est bien **la même** en local et prod (indépendante du fuseau), donc que le bug est purement à la **lecture**. Je vérifie le Doctrine type + le point de bootstrap où fixer le fuseau, et le fuseau du FT.

    Mécanisme confirmé : DATETIME **naïf** en base + Doctrine réhydrate dans le fuseau PHP du serveur (ici UTC → offset `+00:00`). Je cherche une trace de config de fuseau (Docker/php.ini/deploy) qui confirmerait le `Europe/Paris` en prod, et je vérifie le fuseau du FT.

    Confirmé : **aucune** config de fuseau dans les deux apps ni dans le déploiement → chaque hôte impose son `date.timezone` (local = UTC, prod ≠ UTC). C'est exactement la cause de l'écart. Je mesure le fuseau du FT pour être complet.

    Les deux apps sont en **UTC en local**, aucune config de fuseau nulle part → chaque hôte impose son `date.timezone`. Diagnostic complet et mesuré. J'applique le correctif au niveau racine (fixer le fuseau applicatif à UTC dans les deux Kernels, indépendamment du php.ini de l'hôte). Je lis le Kernel du FT.

    Les deux Kernels sont identiques. Je fixe le fuseau applicatif à **UTC** dans chacun, via le constructeur (couvre web + CLI, s'exécute avant toute hydratation).

    Le pin est en place. Maintenant je **prouve** la prédiction : je sérialise le même voyage sous UTC (le pin) puis sous `Europe/Paris` (simulation prod) pour démontrer le décalage de −2h exact que l'utilisateur observe.

    **Preuve faite.** Même valeur naïve `2026-07-23 15:50:00` en base :
    - Serveur **UTC** → JSON `15:50+00:00` → affiché **15:50** ✓
    - Serveur **Europe/Paris** → JSON `15:50+02:00` → affiché **13:50** (−2h) ✗

    C'est exactement ton symptôme (15:00 → 13:00). Je supprime la commande de diagnostic.

    Je scanne rapidement les autres endroits où des datetime « prévus » sont affichés, pour répondre précisément à ton « est-ce que ça se fait ailleurs ».

    Voilà le diagnostic complet et **prouvé par mesure**, puis j'enchaîne sur l'exposition mobile.

    ## Bug fuseau horaire — pourquoi 15:00 → 13:00 en prod

    **Cause racine : aucune des deux apps ne fixe de fuseau horaire.** Elles héritent du `date.timezone` du php.ini de l'hôte — **UTC en local, mais un fuseau UTC+2 en prod** (typiquement `Europe/Paris`, ce qui colle avec le −2h en juillet = heure d'été CEST).

    Le trajet mesuré d'une date de départ prévue :

    1. Tu saisis `15:00` (heure murale). Le FT la poste avec `format('Y-m-d\TH:i:s.v\Z')` — le `\Z` est un **littéral** : il colle un « Z » sans convertir → l'API reçoit `15:00Z`.
    2. Doctrine stocke un `DATETIME` **naïf** : `2026-07-23 15:00:00` (sans fuseau). ✅ **Identique en local et en prod.**
    3. À la **lecture**, Doctrine réhydrate cette valeur naïve dans le fuseau PHP **du serveur**, et l'API la sérialise **avec cet offset**. C'est là que tout se joue — je l'ai reproduit sur ton voyage réel (valeur stockée `15:50`) :

    | Serveur | JSON sérialisé | Affiché (navigateur UTC+0) |
    |---|---|---|
    | **UTC** (ton local) | `15:50:00+00:00` | **15:50** ✓ |
    | **Europe/Paris** (ta prod) | `15:50:00+02:00` | **13:50** ✗ (−2h) |

    Le navigateur (`new Date(...).toLocaleDateString(...)` dans `formatDate`) rend l'**instant absolu** dans le fuseau de la machine — et ta machine est à **UTC+0 (Abidjan)**. La même valeur stockée, étiquetée `+02:00` par le serveur prod, devient un instant 2 h plus tôt → −2h à l'écran.

    ## Est-ce que ça se fait ailleurs ? — Oui

    C'est un **bug de fuseau serveur**, donc il touche **partout où une date « prévue » saisie est renvoyée au navigateur puis rendue en JavaScript** : `VoyageTable`, `VoyagesCollectionTable`, `TicketTable`, les formulaires React (`ReservationForm`, `DesistementForm`, `ReservationWeb`), les heures de passage calculées, **et les apps mobiles**. En revanche :
    - Les rendus **Twig côté serveur** (`|date`) ne bronchent pas : ils réhydratent ET affichent dans le même fuseau serveur, qui s'annule.
    - Les horodatages **générés serveur** (`createdAt`, départ/arrivée réels, passages) restent corrects : ils préservent un instant réel de bout en bout.

    ## Correctif appliqué (racine, une seule cause)

    J'ai épinglé le fuseau applicatif à **UTC** dans les deux Kernels ([Backend](Backend-Transport/src/Kernel.php), [Frontend](Frontend-Transport/src/Kernel.php)), indépendamment du php.ini de l'hôte. La compagnie opère en UTC+0 (Abidjan) et ton local est déjà en UTC et correct → la prod se comportera désormais **exactement comme le local**, et **toutes** les surfaces ci-dessus (dont le mobile) sont corrigées d'un coup.

    ⚠️ **Une nuance à connaître pour la prod** : les horodatages « temps réel » déjà écrits en base **sous Europe/Paris** (départs/arrivées réels passés) seront désormais relus en UTC et s'afficheront +2h. Sur des données de test jetables c'est sans conséquence ; les nouvelles écritures seront cohérentes. Dis-moi si ta prod contient des données réelles à préserver — sinon on est bon.

    ---

    Je passe à l'**exposition mobile** (position du car + retard estimé dans le suivi). Je lis d'abord la façade publique pour voir ce qui est déjà exposé.

    Le suivi renvoie un `ReservationPubliqueDto` via le mapper. Je lis le DTO et le mapper pour voir où greffer la position du car + le retard estimé.

    Je vois la structure. Il me faut exposer la **position du car** (`garecourante`) + un **retard estimé courant**. Le retard se calcule comme dans les bordereaux : `arriveeReelle(gare courante) − heurePassage prévue`. Je regarde `ReservationEcheanceService` et comment le retard est déjà calculé, pour centraliser proprement.

    Je comprends le domaine. J'ajoute au suivi public : **position du car**, **voyage démarré**, **retard estimé courant**, et une **heure de passage estimée** (révisée du retard) chez le client. Je vérifie d'abord les getters du Voyage dont j'ai besoin.

    Tous les getters existent. J'ajoute `retardCourantMinutes` au service, juste après `heurePassage`.

    Maintenant les champs du DTO public :

    Et je peuple le mapper :

    Je factorise `heurepassage` pour réutiliser `$heurePrevue` déjà calculé :

    Je vérifie qu'aucune autre construction de `ReservationPubliqueDto` ne casse avec les nouveaux paramètres requis.

    Tout passe par `versDto` — mon changement couvre suivi, historique et création. Avant de tester, je vérifie le contexte de sérialisation du suivi (les `null` sont-ils retirés ? ça détermine l'optionalité côté mobile).

    Le suivi n'a pas de `normalizationContext` explicite → défaut API Platform. Je vérifie le défaut `skip_null_values` (il conditionne si les nouveaux champs `null` sont absents du JSON, donc leur optionalité côté mobile).

    Pas de `skip_null_values` explicite → défaut API Platform 3 = **les `null` sont retirés du JSON**. Donc côté mobile, `retardMinutes` / `positionActuelle` / `heurepassageEstimee` seront **optionnels** (absents si null), `voyageDemarre` (bool) toujours présent. Je mesure la forme JSON réelle sur une vraie réservation.

    Mesuré et correct. Voyage #26 démarré, car à Bouaké :
    - `voyageDemarre: true`, `positionActuelle: "Gare de bouaké"`, `retardMinutes: -198` (le jeu de test a un départ réel très en avance — l'arithmétique est juste : réel − prévu), `heurepassageEstimee` = heure prévue + retard.
    - `billetEmis`/`paiement` absents → confirme que les `null` sont retirés (donc champs optionnels côté mobile).
    - Sérialisé en `+00:00` → le pin UTC fonctionne.

    Je supprime le diagnostic et passe aux apps mobiles.

    Les deux demandes sont traitées et validées.

    ## 1. Bug fuseau horaire — résolu à la racine

    **Pourquoi 15:00 → 13:00 en prod :** aucune des deux apps ne fixait de fuseau, elles héritaient du `date.timezone` de l'hôte (**UTC en local, UTC+2 en prod**). Les dates « prévues » sont stockées en `DATETIME` **naïf** ; à la lecture, l'API leur colle l'offset du serveur. Le même `15:00` stocké ressort `15:00+00:00` en local (affiché 15:00 sur ton navigateur à UTC+0) mais `15:00+02:00` en prod (= instant 13:00 → affiché **13:00**). Prouvé par mesure sur ton voyage réel.

    **Où d'autre :** partout où une date prévue est rendue en JS/mobile (toutes les tables React, les formulaires, **et les apps mobiles**). Les rendus Twig et les horodatages temps-réel générés serveur n'étaient pas touchés.

    **Correctif :** `date_default_timezone_set('UTC')` épinglé dans les deux [Kernel backend](Backend-Transport/src/Kernel.php) et [Kernel FT](Frontend-Transport/src/Kernel.php) → prod = local partout, une seule cause corrigée. Documenté dans le [README](Backend-Transport/README.md) (section Production).

    ⚠️ Rappel : les horodatages temps-réel déjà écrits en prod **sous Europe/Paris** s'afficheront +2h après bascule. Sans conséquence sur des données de test ; dis-moi si ta prod a des données réelles à préserver.

    ## 2. Exposition « où est mon car » — les deux apps

    **Backend** ([suivi public](Backend-Transport/src/State/Public/ReservationPubliqueMapper.php)) — 4 champs ajoutés au DTO du suivi :
    - `voyageDemarre`, `positionActuelle` (gare courante), `retardMinutes` (retard courant signé = réel − prévu à la position, nouveau [`retardCourantMinutes`](Backend-Transport/src/Domain/Service/ReservationEcheanceService.php)), `heurepassageEstimee` (heure prévue chez le client + retard).

    **Flutter** ([resaflutter](resaflutter/lib/features/reservation/presentation/widgets/reservation_details.dart)) — carte « Suivi du car » (position · état coloré · passage estimé), modèle freezed régénéré, affichée **uniquement en suivi**.

    **React Native** ([resanative](resanative/src/features/reservation/components/ReservationDetails.tsx)) — même carte, mêmes règles, types + helpers ajoutés.

    Les trois champs optionnels sont **omis quand null** (skip_null_values) → les clients les traitent comme optionnels, le bloc n'apparaît qu'une fois le car parti et jamais sur l'historique.


# Exploitation : durée par tronçon, horaires réels de passage, bordereaux & ponctualité

## Contexte

Le modèle décrit la ligne comme une suite d'arrêts et ne connaît, en temps réel, que **deux** horodatages : le départ réel de l'origine (`Voyage.datedepartreelle`) et l'arrivée réelle au terminus (`Voyage.datearriveereelle`). La position du car (`Voyage.garecourante`) avance à la réception / à l'avance commercial, mais **sans être horodatée par gare**. Résultat : impossible de connaître l'heure réelle de passage à une gare intermédiaire, le retard à chaque étape, le temps d'arrêt, ni de produire des statistiques de ponctualité — et les bordereaux d'une gare intermédiaire ne peuvent afficher que des horaires prévus globaux.

Ce chantier ajoute trois notions décidées avec l'utilisateur :
1. **Durée par TRONÇON** (remplace la durée cumulée `Arret.dureeDepuisOrigineMinutes`).
2. **Horaires RÉELS par gare** : arrivée horodatée automatiquement aux points existants + **départ marqué explicitement**.
3. **Bordereaux** enrichis + **statistiques de ponctualité**.

Périmètre validé : **tout, y compris les stats de ponctualité**.

---

## Notion 1 — Durée par tronçon (refonte du champ cumulé)

Aujourd'hui `Arret.dureeDepuisOrigineMinutes` porte le **cumul** depuis l'origine (0, 240, 420…). On passe à une durée **par tronçon** (durée depuis l'arrêt précédent : origine = 0/null, puis 240, 180…). Le cumul redevient un calcul.

- **Entité** [`Arret.php`](Backend-Transport/src/Entity/Arret.php) : renommer `dureeDepuisOrigineMinutes` → `dureeTronconMinutes` (durée depuis l'arrêt précédent ; null/0 à l'origine). Adapter getter/setter et groupes.
- **Saisie** [`LigneInput.php`](Backend-Transport/src/Entity/Dto/LigneInput.php) + [`LigneProcessor::handleArrets`](Backend-Transport/src/State/LigneProcessor.php:88) : le champ d'entrée devient `dureeTronconMinutes`. Nouvelle validation : origine = 0/null ; chaque tronçon suivant **> 0** (au lieu de « strictement croissant »). Tout-ou-rien conservé.
- **Calcul central** [`ReservationEcheanceService::heurePassage`](Backend-Transport/src/Domain/Service/ReservationEcheanceService.php:55) : au lieu de lire un cumul, **sommer les `dureeTronconMinutes`** de l'origine effective jusqu'à la gare visée (repli sur `datedepartprevue` si un tronçon manque). C'est le SEUL point de calcul à changer ; tous les consommateurs de `heurePassage` (échéances, DTO publics `heurepassage`, `VoyagesReservablesProvider`, `ReportsPossiblesProvider`, `DepartsPubliquesProvider`…) restent inchangés.
- **Front** [`LigneForm.tsx`](Frontend-Transport/assets/react/controllers/Exploitation/LigneForm.tsx) + [`ligne/show.html.twig`](Frontend-Transport/templates/ligne/show.html.twig) : saisie/affichage « durée du tronçon » par arrêt (l'origine n'en a pas), avec calcul indicatif du cumul et de l'heure de passage.
- **Migration de données** : pour chaque ligne, trier les arrêts par ordre et convertir le cumul en différences consécutives (`troncon[i] = cumul[i] − cumul[i-1]`) ; conserver `null` pour les lignes non renseignées.

---

## Notion 2 — Horaires réels par gare (nouvelle entité + capture)

### Nouvelle entité `Passage`
Un enregistrement d'exploitation par **(voyage, gare)** — sur le modèle d'`Inventaire` (donnée d'exploitation, **pas** de soft-delete) :
- `voyage` ManyToOne, `gare` ManyToOne, `identreprise`, `createdAt` ; contrainte **unique (voyage, gare)**.
- `arriveeReelle` (datetime nullable), `departReelle` (datetime nullable).
- Getters dérivés (non stockés) : `retardArriveeMinutes` (= `arriveeReelle − heurePassage prévue`), `tempsArretMinutes` (= `departReelle − arriveeReelle`).
- Repository `PassageRepository` : `findParVoyage`, upsert `pour(voyage, gare)`.

### Points de capture (réutilisent l'existant)
Un service **`PassageService`** centralise l'écriture (find-or-create du `Passage`, pose l'horodatage sans flush — les processors appelants flushent) :
- **Départ origine** — [`VoyageDepartService::marquerDepart`](Backend-Transport/src/Domain/Service/VoyageDepartService.php:35) : pose `Passage(origine).departReelle = datedepartreelle`.
- **Arrivée intermédiaire** — [`ReceptionnerVoyageProcessor`](Backend-Transport/src/State/ReceptionnerVoyageProcessor.php) et [`AvancerCommercialProcessor`](Backend-Transport/src/State/AvancerCommercialProcessor.php) (les deux avancent déjà `garecourante`) : pose `Passage(gare).arriveeReelle = now`.
- **Départ intermédiaire** — **nouvelle action** `PATCH /voyages/{id}/repartir` (processor dédié `RepartirVoyageProcessor` + route ApiPlatform sur `Voyage`) : pose `Passage(garecourante).departReelle = now`. Autorisation : agent de la gare courante **ou** commercial du voyage (miroir de `assertPeutReceptionner` / logique commerciale). Refuse si l'arrivée à cette gare n'est pas encore marquée, ou si déjà reparti.
- **Arrivée terminus** — [`CloturerVoyageProcessor`](Backend-Transport/src/State/CloturerVoyageProcessor.php) : pose `Passage(terminus).arriveeReelle = datearriveereelle`.

### Cohérence avec l'existant
`Voyage.datedepartreelle` / `datearriveereelle` restent la **source de vérité** pour l'origine et le terminus (position, `VoyageGuard::monteeDepassee`, recettes, filtres — inchangés). `Passage` les **reflète** pour ces deux gares et **ajoute** les intermédiaires + une vue unifiée. Le fallback commercial (avance sans passer par « repartir ») pose le départ de la gare quittée à défaut, en le signalant comme déduit.

---

## Notion 3 — Bordereaux enrichis

Consomment `Passage` + `heurePassage` (prévu) pour afficher, **par gare** : heure de passage **prévue**, **arrivée réelle**, **départ réel**, **retard** et **temps d'arrêt**.
- [`BordereauProvider`](Backend-Transport/src/State/BordereauProvider.php) + `BordereauVoyageDto`/`BordereauGareDto` : ajouter ces champs pour la gare du bordereau (aujourd'hui seul `datedepartprevue` global est exposé).
- [`VoyageManifesteController`](Backend-Transport/src/Controller/Api/VoyageManifesteController.php) (feuille de route) : ajouter par gare l'heure prévue/réelle, le retard et son **évolution** le long du trajet.
- [`BordereauChauffeurProvider`](Backend-Transport/src/State/BordereauChauffeurProvider.php) : heures de passage prévues/réelles sur le document chauffeur.
- Front : templates PDF/vues bordereau + feuille de route (`voyage/manifeste.html.twig`, templates bordereau) affichent les nouvelles colonnes.

---

## Notion 4 — Statistiques de ponctualité

Nouvelle surface admin (dédiée ou greffée sur [`ExploitationStatsProvider`](Backend-Transport/src/State/ExploitationStatsProvider.php)) alimentée par des requêtes `PassageRepository` :
- retard **moyen** par ligne et par gare, **taux à l'heure** (retard ≤ seuil), **évolution du retard** le long du trajet, temps d'arrêt moyen par gare.
- DTO de sortie + rendu front (page stats exploitation).

---

## Exposition clients (apps mobiles / suivi)

Le suivi public (`/api/reservation/suivi`, `DepartsPubliquesProvider`) peut exposer, en plus de `heurepassage` (prévu), la **position courante** et le **retard estimé** — « savoir où se trouve le voyage ». À cadrer en fin de chantier (DTO publics + modèles Flutter/React Native). Optionnel, listé pour mémoire.

---

## Ordre d'implémentation

1. **Notion 1** (durée par tronçon) : entité + `heurePassage` + saisie + migration de données. Indépendante, se vérifie seule.
2. **Notion 2** (entité `Passage` + `PassageService` + capture + action `repartir`) : fondation des suivantes.
3. **Notion 3** (bordereaux / feuille de route).
4. **Notion 4** (stats ponctualité).
5. **Exposition clients** (si retenue).

Chaque étape passe par une **migration** (schéma) soumise avant exécution — la base est un banc d'essai actif (demander avant toute écriture).

---

## Vérification (mesurer, ne pas conclure sur lecture)

- **Notion 1** : commande temporaire `TmpVerif…` (lecture seule) comparant, sur les lignes réelles, `heurePassage(gare)` AVANT/APRÈS refonte pour chaque arrêt → doit être **identique** (la migration préserve les heures de passage). Vérifier la validation de saisie (origine=0, tronçons>0).
- **Notion 2** : simulation en transaction annulée d'un cycle départ → réception → repartir → clôture sur un voyage réel à ≥3 arrêts ; vérifier que `Passage` porte arrivée/départ cohérents, que `datedepartreelle`/`datearriveereelle` restent synchronisés, et l'unicité (voyage, gare). Vérifier l'instanciation réelle des processors dont le constructeur change (`php bin/console debug:container`).
- **Notion 3 & 4** : requête HTTP réelle (noyau, JWT forgé, `MAIN_REQUEST`) sur un bordereau et sur les stats d'un voyage ayant des passages réels ; comparer retard/temps d'arrêt aux valeurs attendues.
- Nettoyer toute commande `Tmp*` après usage ; ne laisser que `ExpirerReservationsCommand`.

## Fichiers clés

- **Notion 1** : `Arret.php`, `Dto/LigneInput.php`, `State/LigneProcessor.php`, `Domain/Service/ReservationEcheanceService.php`, `LigneForm.tsx`, `ligne/show.html.twig`, migration.
- **Notion 2** : nouvelle entité `Entity/Passage.php` + `Repository/PassageRepository.php` + `Domain/Service/PassageService.php` + `State/RepartirVoyageProcessor.php` ; modifs `VoyageDepartService.php`, `ReceptionnerVoyageProcessor.php`, `AvancerCommercialProcessor.php`, `CloturerVoyageProcessor.php`, route sur `Voyage.php` ; migration.
- **Notion 3** : `BordereauProvider.php`, `BordereauChauffeurProvider.php`, `VoyageManifesteController.php`, DTO bordereau, templates.
- **Notion 4** : `ExploitationStatsProvider.php` (ou nouveau provider), DTO stats, `PassageRepository`, front stats.
- **Doc** : mettre à jour `Backend-Transport/README.md` (module Exploitation) au fil des étapes.


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



















On vas y revenir.

Je viens d'installer un projet Flutter dans le dossier. Je veux que tu développes l'application mobile pour la réservation et je veux une application propre, évolutive et maintenable, en appliquant les bonnes pratiques de développement Flutter







Excellente question — c'est une brique classique du transport, et notre version a quelques particularités qui valent une explication posée. Je te fais ça en trois temps : le principe **classique**, **notre** version, puis le **parcours client**.

Laisse-moi d'abord poser un schéma du cycle de vie pour ancrer le vocabulaire.

Voici l'explication complète, en partant de zéro.

## 1) La réservation « classique » — le principe général

**Le problème de fond.** Dans le transport, ton stock est **limité** (N places dans un car) et **périssable** (une fois le car parti, les places invendues sont perdues pour toujours). La simple vente au comptoir (« je viens, je paie, je pars ») ne suffit pas : un client qui voyage dans 3 jours veut être **sûr** d'avoir sa place dès aujourd'hui, sans forcément se déplacer ni payer tout de suite.

**La réservation = tenir une place à l'avance.** C'est un mécanisme qui met une place « de côté » pour un client **avant** le départ, pour qu'elle ne soit pas vendue à quelqu'un d'autre. C'est un engagement réciproque : la compagnie garde la place, le client s'engage (en payant) à la prendre.

**Le cycle classique** (avion, train, Booking…) :
1. **Recherche** d'un trajet + une date.
2. **Sélection** d'un départ → le système vérifie qu'il reste de la place.
3. **Blocage temporaire** (*hold*) : la place est gelée le temps de payer.
4. **Paiement**.
5. **Confirmation** : la réservation devient ferme, avec un **code de dossier** (le « PNR » en avion).
6. **Émission du billet / enregistrement** : souvent **séparé** — le siège précis et le billet définitif arrivent plus tard.
7. **Embarquement**.

**Les 4 notions à retenir absolument :**
- **Capacité / inventaire** : le nombre de places, qui diminue à chaque réservation.
- **Blocage + expiration** : une place tenue mais **non payée** est **libérée** au bout d'un délai — sinon les gens bloqueraient tout sans jamais payer. C'est vital.
- **Réservation ≠ billet** : réserver, c'est s'assurer une place ; le billet (avec le siège) vient après.
- **No-show** : réservé/payé mais absent → la place est perdue (souvent non remboursée).

## 2) Ce qu'on a construit — et ses particularités

On a repris ce principe, mais adapté à **ton** métier (car, multi-gares, tronçons). Quatre choix le rendent un peu spécial :

**a) On réserve une PLACE, pas un SIÈGE.** C'est LA grande idée. Au guichet, le client choisit son siège sur le plan du car. Mais pour une réservation à l'avance, **le car n'est souvent pas encore affecté**. Donc impossible de choisir un siège. → La réservation tient une place « abstraite » sur le tronçon (montée → descente) ; le **siège est attribué tout à la fin**, à la gare, quand le car est connu. *(Comme un cinéma en placement libre : tu paies ton entrée, tu choisis ton fauteuil en entrant.)*

**b) Le « bon » ≠ le « billet de gare » (le découplage que tu as bien flairé).** Payer et retirer son siège sont **deux étapes distinctes** :
- Le client **paie en ligne**, à l'avance, **sans qu'un car soit affecté** → il obtient un **BON** (justificatif payé, sans siège).
- Plus tard, **à la gare**, il présente son bon → on lui **émet le billet** avec un siège concret.

C'est exactement ce qui rend la réservation **mobile possible** : on ne peut pas exiger un car affecté 3 jours avant.

**c) La capacité se compte PAR TRONÇON.** Un car fait Abidjan → Bouaké → Korhogo. Une place occupée sur *Abidjan→Bouaké* se **libère** ensuite pour *Bouaké→Korhogo*. Donc « il reste X places » dépend du **segment** regardé. Notre `CapaciteService` calcule, segment par segment, les places tenues (billets + réservations actives) et prend le pire cas → on ne survend jamais.

**d) L'expiration a deux visages** (schéma ci-dessus) :
- **Non payé** → à la deadline (départ − délai configurable), la place est **libérée** (revendable).
- **Payé mais non retiré** (no-show) → le bon est **périmé**, la place libérée, et — selon ton choix — **l'argent est forfait** (non remboursable).

Et deux détails d'architecture : **guichet et web/mobile partagent le même moteur** (`ReservationCreationService` → mêmes règles/prix/capacité), et le **paiement se fait par lien + webhook** (le prestataire encaisse sur sa page, puis nous « rappelle » pour confirmer — simulé pour l'instant).

## 3) Comment le client réserve (le parcours concret)

Sur le web (identique sur le futur mobile — **même API**) :

| Étape | Ce qu'il voit | Ce qui se passe derrière |
|---|---|---|
| **1. Accueil** | Le lien de ta compagnie (`/reserver/{compagnie}`), ton nom/logo | Branding chargé depuis l'API publique |
| **2. Ville** | Les villes desservies | Villes ayant au moins une gare active |
| **3. Gare de départ** | Les gares de la ville → sa **provenance** | — |
| **4. Destination + prix** | Les destinations desservies **avec le tarif** | Issu de ta **grille tarifaire** |
| **5. Choix du départ** | Les prochains **voyages datés** + **places restantes** | Calcul de capacité en temps réel |
| **6. Ses infos** | Nom + téléphone (**pas de compte**) | — |
| **7. Paiement** | Redirection vers la page Mobile Money | La place est **déjà tenue** (`EN_ATTENTE`), l'horloge tourne |
| **8. Confirmation** | « Réservation confirmée » + **code** + **bon PDF** | Webhook du prestataire → `CONFIRMÉE / PAYÉ` |
| **9. À la gare** | Il présente son **code / bon** | L'agent clique « Émettre le billet » → **siège attribué** → billet imprimé → embarque |

**Côté agent** (guichet), c'est plus court : l'agent crée la réservation pour le client, encaisse (espèces), puis émet le billet — souvent dans la foulée si le car est déjà là.

---

En une phrase : **réserver, c'est acheter le *droit* à une place à l'avance (le bon) ; ce droit se transforme en *siège concret* (le billet) au moment d'embarquer.** C'est cette séparation qui permet de vendre en ligne sans connaître le car, tout en garantissant qu'on ne survend jamais.

Veux-tu que je détaille un aspect précis (le calcul de capacité par tronçon, la mécanique d'expiration, ou le branchement du vrai paiement) ?







Avant de coder, je veux être honnête avec toi comme tu le demandes : pour te dire des choses **vraies** sur ton code (pas des généralités), je dois d'abord le lire. Je vais donc commencer par cette tâche concrète (l'autocomplete React) qui va justement me faire traverser beaucoup de tes formulaires et listings — et je te remonterai au fur et à mesure ce que je vois de bien et de moins bien, avec franchise.

Commençons par comprendre l'existant : ton système `tom-select-remote` + `SearchController`, et comment les selects React sont faits aujourd'hui.

Bien — `SearchController` expose `/search?resource=X&q=Y` → `[{value,label,raw}]` pour : fournisseurs, pieces, cars, voyages, gares, personnels, lignes, users, tickets. **Réutilisable directement** côté React. Regardons maintenant le JS existant, les primitives UI React dispo, un form cible, et le mécanisme de filtres des tables serveur.

Tu as déjà `command.tsx` + `popover.tsx` côté React → je peux bâtir un **combobox distant** (le vrai équivalent React de tom-select) qui tape le même endpoint `/search`. Regardons ces primitives + le mécanisme de filtres des tables serveur.

I have the full picture now. Your `/search` endpoint is well-designed and **reusable as-is** for React. You already have `command.tsx` + `popover.tsx`, so the clean React equivalent of tom-select is a **remote combobox** (Popover + Command + debounced fetch to `/search`).

Let me read those two primitives and a first target form to build it correctly.

I have everything. Let me build the reusable **`RemoteCombobox`** — the React equivalent of `tom-select-remote`, hitting the same `/search` endpoint.

Composant créé. Maintenant, câblons-le. Regardons le premier formulaire cible — `ApprovisionnementForm` (select des pièces).

Les formulaires de création/modif (appro, dépannage, voyage) ne sont pas des `*Form.tsx`. Vérifions s'ils sont en Twig ou React, et où sont les selects concernés.

Découverte importante à te dire franchement : **la plupart de ces formulaires (appro, dépannage, voyage, bordereau, affectations) sont en Twig, pas en React.** Ton système `tom-select-remote` les couvre donc déjà — il n'a simplement pas été *branché* sur ces selects. Seuls les **listings** (tables React) ont besoin du nouveau composant. Vérifions le markup actuel de ces selects Twig.

Ça révèle des choses importantes — laisse-moi vérifier un point (un doublon suspect) avant de te faire le bilan honnête.

Vérifié : le "doublon" fournisseur est en fait l'ancien select **commenté** (`{# … #}`) — donc pas de bug, tu as bien appliqué `data-remote-select` sur le fournisseur. 👍

Voici mon **bilan honnête** après avoir traversé ces fichiers :

**Ce qui est bien :** ton système `tom-select-remote` + `SearchController` est **propre et bien pensé** (normalisation `{value,label,raw}`, préchargement, débounce). Tu l'as correctement appliqué par endroits (le fournisseur en appro).

**Le vrai constat :** la plupart des selects de ta liste sont dans des **formulaires Twig** (appro, dépannage, voyage, bordereau, affectations) — pas React. Et beaucoup chargent **toutes** les options d'un coup (`{% for p in pieces %}`, tous les users, etc.) : c'est exactement le problème de scalabilité que tu pressens. La bonne nouvelle : **ton tom-select les couvre déjà**, il suffit de le brancher (`data-remote-select`). Seuls les **listings** (tables React) ont besoin du nouveau composant.

Donc **deux chantiers** :
- **A — Twig** : brancher `data-remote-select` sur les selects non convertis (pièces, car, ligne, commercial, personnel, bordereau) + gérer les lignes ajoutées dynamiquement.
- **B — React** : le combobox distant (que je viens de créer) pour les filtres des listings.

Je commence par **B** (il valide mon nouveau composant), puis **A**. D'abord, étendons le système de filtres des tables serveur.

Maintenant, ajoutons le rendu du filtre `remote` dans `server-data-table.tsx`.

Câblons les 4 filtres. Lisons leurs définitions de filtres actuelles + les extras des contrôleurs.

Je convertis ces filtres `select` en `remote`. Lisons les blocs exacts pour éditer précisément.

Les 4 filtres sont convertis en `remote`. Buildons pour voir ce qui devient inutilisé (props d'options à nettoyer) — c'est justement l'optimisation voulue.

Build **OK** (0 erreur). Track B (filtres React) est câblé. Vérifions maintenant si les props d'options (`fournisseurs`/`cars`/`lignes`/`pieces`) sont devenues inutilisées — pour compléter l'optimisation (supprimer le chargement complet côté contrôleur).

Ces props sont maintenant **des données mortes** (chargées mais plus utilisées) — c'est exactement ce qu'il faut optimiser. Retirons le chargement complet côté contrôleur + template + composant. Regardons les contrôleurs.

Ces `extras` chargent toutes les options pour rien maintenant. Retirons-les proprement (contrôleur + template + composant). Lisons les blocs exacts.

Maintenant les templates (retirer la prop) et les composants (Props + destructure). Lisons les blocs `react_component` des 4 index.

Maintenant les composants : retirons la prop d'options (Props + destructure). Lisons les zones concernées.

`pieceOptions` est dans un bloc **commenté** — donc `pieces` n'est plus utilisé. Retirons import + prop + destructure + deps.

Track B complet (composant + filtres + optimisation contrôleurs). Vérifions : lint PHP contrôleurs, twig, et build FT.

Tout passe : **PHP lint OK, twig OK, build FT OK** (0 erreur, plus aucune donnée morte). 

Avant de conclure ce lot et d'attaquer les selects Twig, vérifions que `initRemoteSelects()` est bien appelé globalement (sinon les nouveaux `data-remote-select` ne s'initialiseraient pas).

`initRemoteSelects()` tourne sur `turbo:load` → pour les selects Twig **statiques**, ajouter l'attribut suffira. Les lignes **dynamiques** (pièces appro/dépannage) demandent d'appeler `initRemoteSelect` sur la nouvelle ligne **et** d'adapter le calcul de prix (qui lit `data-prix` des options) — c'est le cas délicat.

Enregistrons ce nouveau système en mémoire, puis je te fais le point.

Point d'étape — **Track B terminé et vérifié** (build + lint OK).

## Ce que j'ai livré (React)
1. **`RemoteCombobox`** ([RemoteCombobox.tsx](FT-Transport/assets/react/components/RemoteCombobox.tsx)) — le vrai équivalent React de ton `tom-select-remote` : Popover + Command, recherche serveur débouncée sur le **même** `/search?resource=`, préchargement, pas de liste chargée en mémoire.
2. **Filtre de table `remote`** — nouveau type dans `ServerTableFilter`, rendu dans `server-data-table.tsx`.
3. **4 listings convertis** : Approvisionnements (fournisseur), Dépannages (véhicule), Voyages (ligne + véhicule), Inventaires (pièce).
4. **Optimisation réelle** : j'ai supprimé le chargement de **toutes** les options côté contrôleur (`collection('/api/fournisseurs')`, `/api/cars`, `/api/lignes`, `/api/pieces`) + les props/templates devenus morts. Ces pages ne tapent plus l'API pour rien.

## Mon avis franc, à ce stade
- Ton architecture BFF (FT proxy → BK API Platform) est **cohérente et propre**, et le `SearchController` est bien fait — je l'ai réutilisé sans le toucher.
- Le point faible récurrent que je vois : le réflexe **« charger toute la liste dans un `<select>` »** (fournisseurs, users, pièces, cars…). Ça ne tient pas à l'échelle et c'est répété à plusieurs endroits. C'est exactement ce qu'on est en train de corriger. Ce n'est pas « mal », c'est un choix de début de projet qui devient un coût maintenant.

## Limite de vérification (honnête)
Ces listings sont derrière le **login agent**, et `/search` exige une session authentifiée. Je n'ai **pas tes identifiants** et je ne les devinerai pas — donc j'ai tout validé ce qui est validable (build TypeScript, lint PHP/Twig, forme de l'endpoint), mais le clic réel (taper, voir les résultats, filtrer) est **à confirmer par toi**. Ouvre un listing (ex. Voyages) et teste le filtre ligne/véhicule.

## Reste — Track A (selects Twig)
Les formulaires (appro, dépannage, voyage new/edit, bordereau, affectations commercial/personnel/car) sont en **Twig** : j'y brancherai `data-remote-select` (ton système existant). **Un cas délicat** : le select **pièces** (appro/dépannage) est en lignes ajoutées dynamiquement **et** déclenche l'auto-remplissage du prix (lecture de `data-prix` sur les `<option>`). En autocomplete distant, il faudra lire le prix depuis le `raw.prixunitaire` de l'item — je le ferai proprement, mais c'est le seul endroit qui change un peu de comportement.

Je peux enchaîner sur Track A tout de suite. Veux-tu que je fasse **tout Track A d'un coup**, ou **d'abord les selects simples** (commercial, affectations, voyage, bordereau) puis le cas pièces à part pour que tu valides le comportement du prix ?








Parfait — je fais **d'abord les selects Twig simples** (commercial, affectations, voyage new/edit, bordereau), puis le cas « pièces » à part pour que tu valides le comportement du prix.

Lisons le markup actuel de ces selects.

`voyage/new|edit|car|personnel` n'ont pas de `<select>` littéral — sûrement des `form_widget` Symfony (EntityType) ou un autre widget. Regardons.

Distinction importante : `voyage/commercial` et `bordereau` sont des **`<select>` bruts** (le contrôleur lit `$request` manuellement) → conversion directe facile. Mais `voyage new/edit car+ligne` et les affectations car/personnel sont des **champs Symfony `EntityType`** (`form_row(form.car)`) — là, tom-select se heurte à la validation des choix d'`EntityType`, c'est plus délicat.

Vérifions comment ces FormTypes définissent leurs champs, et si tu as déjà résolu ce pattern quelque part.

Bonne nouvelle : ce sont des `ChoiceType` (pas `EntityType`), avec des `choices` passées par le contrôleur. Pour les rendre distants, il faut : rendre le `<select>` vide + `data-remote-select`, **et** gérer la validation `ChoiceType` (qui refuse une valeur hors `choices`) via un listener `PRE_SUBMIT`. Lisons `VoyageFormType`.

Compris. Les **vraiment simples** sont `commercial` et `bordereau` (selects bruts). Les voyage/affectation sont des `ChoiceType` → je ferai un mécanisme réutilisable propre juste après. Faisons d'abord les deux bruts. Lisons-les précisément.

Convertissons le select commercial (resource `users`) avec préselection edit.

Regardons l'action `commercial` du contrôleur pour retirer le chargement des users (avec la garde de permission).

Il y a une subtilité de sécurité : le `collection('/api/users')` sert aussi de **sonde de permission** (USER_VOIR) pour afficher/masquer le formulaire. Regardons la garde du template pour ne pas la casser.

La garde est `{% if users is empty %}`. Je garde la sonde de permission mais **allégée** (`itemsPerPage=1`) — le select est distant, plus besoin de tout charger.

Maintenant le select **ligne** du bordereau. Lisons-le.

Le bordereau est un filtre GET : je convertis le select **et** j'ajuste le contrôleur pour ne récupérer que le libellé de la ligne sélectionnée (au lieu de toutes). Lisons le contrôleur.

Maintenant le template bordereau (select ligne → distant).

Vérifions que `lignes` n'est plus référencé ailleurs dans le bordereau, puis lint PHP + Twig.

Deux selects simples **faits et vérifiés** (lint PHP + Twig OK).

## Fait dans ce lot (Twig, ton système tom-select)
- **Commercial** ([voyage/commercial.html.twig](FT-Transport/templates/voyage/commercial.html.twig)) → `data-remote-select="users"` + préselection. Contrôleur : le `collection('/api/users')` (qui chargeait **tous** les utilisateurs) est réduit à une simple **sonde de permission** (`itemsPerPage=1`) — la garde « permission requise » est préservée.
- **Bordereau — ligne** ([bordereau/index.html.twig](FT-Transport/templates/bordereau/index.html.twig)) → `data-remote-select="lignes"`. Contrôleur : plus de `collection('/api/lignes')` ; on ne récupère plus que le **libellé de la ligne filtrée** (via `/api/lignes/{id}`) pour l'afficher.

## Correction honnête sur le classement
En les ouvrant, j'ai vu que **« voyage » et « affectations » ne sont PAS des selects simples** : ce sont des champs **Symfony `ChoiceType`** (`form_row(form.car)`, `form.ligne`, `form.personnel`), avec les choix chargés par le contrôleur. On ne peut pas juste ajouter `data-remote-select` : `ChoiceType` **rejette à la validation** toute valeur absente de sa liste `choices`. Il faut un **mécanisme réutilisable** (un `RemoteChoiceType` avec un `choice_loader` qui accepte l'id soumis + l'attribut `data-remote-select` + la préselection edit). C'est plus d'ingénierie, et surtout ça touche un **chemin critique** (création/édition de voyage, affectation car/personnel).

## Ma recommandation (sincère)
Je te propose de traiter ce lot `ChoiceType` **à part et proprement** :
1. Je construis un `RemoteChoiceType` réutilisable.
2. Je l'applique à voyage new/edit (car+ligne), affectation car, affectation personnel, dépannage.
3. J'allège les contrôleurs (plus de chargement complet).

⚠️ **Mais je ne peux pas cliquer-tester ces formulaires** (ils sont derrière le login agent, pas de credentials) — or ce sont des **soumissions critiques**. Donc après implémentation, **il faudra que tu testes** une création de voyage + une affectation pour valider que la soumission passe.

Puis on finit par le cas **pièces** (appro/dépannage) avec l'auto-remplissage du prix.

Je lance le `RemoteChoiceType` + le lot voyage/affectation/dépannage ? (c'est parti si tu confirmes)









Oui je souhaite enrichir le label de la ressource..

Aussi :
- Vu notre logique de multi-gare est ce qu'une gare doit pouvoir voir toutes les lignes qui le concerne lors de la création d'un voyage ou doit t'il voir que les lignes dans lesquelles il est l'origine en prenant en compte qu'une gare intermédiaire peut créer un voyage s'il a vendu plus de tickets que nécessaire alors il devient l'origine du voyage. (du genre une gare ne devrait pouvoir créer un voyage que si elle est l'origine de la ligne.)
- Est ce qu'une gare intermédiaire doit pouvoir modifier ou changer le commercial d'un voyage dont il n'est pas l'origine !







Enrichissons le label des pièces (ajout du stock, comme l'ancien select le montrait).

Label enrichi. Lint + investiguons la logique multi-gare pour te répondre de façon **ancrée dans le code** (scope des lignes, création de voyage, affectation commercial).

Le scope actuel est clair : un `LigneGareScopedInterface` (Ligne, Voyage) est visible si la gare de l'agent est **un arrêt quelconque** (origine, intermédiaire OU terminus) — donc aujourd'hui une gare intermédiaire voit et peut créer des voyages. Regardons la création de voyage + l'affectation commercial (permissions/logique).

Regardons `AffectCommercialProcessor` en entier (la restriction de gare sur l'affectation commercial).

`assertPeutGerer` (VoyageGuard) est la garde clé pour l'affectation commercial. Lisons-la.

Label des pièces enrichi (le stock réapparaît : `libellé (stock : X)`), et ça vaut aussi pour l'appro — lint OK.

Maintenant tes deux questions. J'ai lu la logique réelle pour te répondre du concret, pas au feeling.

## Ce que fait ton code aujourd'hui
La doctrine multi-gare est centralisée dans [VoyageGuard](BK-Transport/src/Security/VoyageGuard.php) :
- **Préparation** (créer, modifier, affecter car/personnel/**commercial**, supprimer) → **tout le monde SAUF le terminus**. Donc **une gare intermédiaire peut aujourd'hui préparer/créer un voyage et gérer le commercial.**
- **Réception** → gare **intermédiaire** uniquement.
- **Clôture** → **terminus** uniquement.

Et à la création, [VoyageProcessor](BK-Transport/src/State/VoyageProcessor.php) fixe `provenance = origine de la ligne` **quelle que soit** la gare qui crée. Donc une gare intermédiaire qui crée un voyage crée en fait un départ… depuis l'origine de la ligne (une autre gare qu'elle-même). C'est incohérent.

## Mon avis (répond à tes 2 questions)

**Q1 — visibilité des lignes à la création :** je recommande **origine uniquement**. Un voyage = un départ *depuis l'origine de la ligne*. Qu'une gare intermédiaire planifie un départ partant d'une autre gare n'a pas de sens. Ton cas « la gare intermédiaire a survendu → elle devient l'origine » est un besoin **distinct** : ce n'est pas « créer un voyage sur une ligne où je suis intermédiaire », c'est **créer un départ DEPUIS ma gare** (donc `provenance = ma gare`, pas l'origine de la ligne). À modéliser explicitement (un « départ partiel »), pas en assouplissant la règle générale.

**Q2 — commercial par une gare intermédiaire :** je recommande **non**. Le commercial roule de l'origine au terminus ; c'est un réglage **au niveau du voyage**, qui appartient à la gare qui **lance** le voyage (l'origine). L'intermédiaire, lui, **réceptionne** — il ne prépare pas.

## La doctrine cohérente que ça donne
**L'origine prépare · l'intermédiaire réceptionne · le terminus clôture.**
Concrètement : resserrer `VoyageGuard::assertPeutGerer` de « pas le terminus » → « origine (ou admin/central) », + enforcement à la création (VoyageProcessor) + filtrer le select ligne du form de création aux lignes dont la gare est l'origine.

C'est un changement de l'autorisation cœur, donc je te laisse trancher la direction :

Tes deux points sont excellents — ils affinent la doctrine. Laisse-moi répondre à la panne, puis t'expliquer le départ partiel.

## La panne en cours de route (ton objection à Q1)
Tu as raison : « origine prépare tout » casse si le car tombe en panne à une gare intermédiaire — c'est elle qui doit affecter un car de remplacement, pas l'origine (loin, pas au courant).

La bonne façon de trancher, c'est de **distinguer deux natures d'action** sur un voyage :

| Action | Nature | Qui |
|---|---|---|
| Créer, modifier (dates/ligne/places), **commercial** | **Propriété / planification** | **Origine** (+ admin) |
| Affecter / **changer le car**, chauffeur/personnel | **Exploitation / incident** | Toute gare de la ligne **sauf terminus** (comme aujourd'hui) |
| Réceptionner | — | Intermédiaire |
| Clôturer | — | Terminus |

Ainsi : **Q1 = oui** (créer = origine), **Q2 = non** (commercial = origine), **et la panne est couverte** (la gare où est le car peut swapper le car / le chauffeur). Le commercial, lui, est un choix de départ qui appartient à l'origine ; il n'a pas de raison de changer en route.

*(On pourrait être plus fin en autorisant le swap car uniquement à la gare où le car se trouve — `garecourante` — mais c'est plus fragile ; « toute gare sauf terminus » pour le car est plus simple et couvre la panne.)*

## Le principe du « départ partiel » (ton choix 2)
Aujourd'hui un voyage = un départ **de l'origine de la ligne** : `provenance = origine`, et il dessert origine → terminus.

Le départ partiel : prends la ligne **Abidjan → Bouaké → Korhogo**. Bouaké (intermédiaire) a vendu assez de billets pour lancer **son propre car** Bouaké → Korhogo, sans attendre celui d'Abidjan. Bouaké **devient l'origine de CE voyage précis** :
- `provenance = Bouaké` (pas Abidjan),
- il ne dessert que **Bouaké → … → Korhogo** (les arrêts en aval),
- aucun billet vendable avant Bouaké, capacité/manifeste calculés sur cette fenêtre.

**Ce que ça implique techniquement** : le `Voyage` doit connaître sa **vraie gare de provenance** (un FK `gareprovenance`), au lieu de déduire systématiquement l'origine depuis la ligne. Par défaut = origine de la ligne (voyages normaux) ; pour un départ partiel = la gare intermédiaire. Puis **tout ce qui itère la ligne** (manifeste, `CapaciteService`, gares de vente autorisées, réception, clôture) doit borner la fenêtre à `[gareprovenance → terminus]` au lieu de `[origine → terminus]`. C'est un vrai chantier (entité + plusieurs services), donc à faire **en 2ᵉ temps**, après le resserrement de doctrine qui, lui, est petit et autonome.

## Ma proposition de séquencement
1. **D'abord** : le resserrement de doctrine ci-dessus (petit, ciblé : `VoyageGuard` + enforcement création + filtre du select ligne). Ça répond à tes 2 questions.
2. **Ensuite** : le départ partiel (`Voyage.gareprovenance` + adaptation des services), une fois le 1 validé.

Parfait, c'est validé : **planification + commercial → origine ; car + chauffeur → toute gare sauf terminus ; réception → intermédiaire ; clôture → terminus.** Et on fait **le resserrement de doctrine d'abord**.

Repérons tous les points à toucher : les appelants de `assertPeutGerer`, le processeur de modification, et les affectations car/personnel.

Le mapping est clair :
- **Car (AffectcarProcessor) + Personnel (AffectpersonnelProcessor)** → gardent `assertPeutGerer` (exploitation, couvre la panne). **Inchangés.**
- **Création + modif (VoyageProcessor) + Commercial + suppression voyage** → passent en **origine-only**.

Vérifions les 2 branches ambiguës : `VoyageProcessor:128` et `SoftDeleteProcessor:44/67`.

Tout est clair. Mapping final :
- **`assertPeutGerer` (not-terminus = exploitation)** → gardé pour **car + personnel** (dont la désaffectation personnel). Inchangé → couvre la panne.
- **Nouveau `assertPeutPlanifier` (origine-only)** → **création + modif voyage + commercial + suppression voyage**.
- **Select ligne création** → nouvelle ressource `lignes_origine` (lignes dont ma gare est l'origine).

Implémentons. D'abord `VoyageGuard`.

Mettons à jour les appelants. `VoyageProcessor` (création + modif). Lisons-le pour éditer précisément.

`AffectCommercialProcessor` → origine-only.

`SoftDeleteProcessor` : suppression d'un voyage → planification (origine). Lisons + éditons.

UI : ressource `lignes_origine`. D'abord ajoutons le filtre `gareorigine` sur l'entité `Ligne`.

Ajustons selon la convention du projet (relation filtrée par `.id` ?). Vérifions `Tarif`.

Convention = `.id`. Corrigeons le filtre Ligne + trouvons `getGare()` du User FT.

`ApiUser::getGare()` renvoie un array (`['id'=>...]`). Ajoutons `lignes_origine` au `SearchController`. Lisons la zone du branchement `tickets`.

Ajoutons la méthode `searchLignesOrigine` (après `searchTickets`). Lisons la fin du fichier.

Enfin, pointons le select ligne de la création vers `lignes_origine`.

Tout est câblé. Vérifions : lint PHP (BK + FT) + `cache:clear` BK (compile le nouveau `ApiFilter` Ligne + les gardes).

Lint tout OK. Confirmons que le `cache:clear` BK a bien réussi (compile la nouvelle métadonnée `ApiFilter` Ligne sans erreur).

Les deux `cache:clear` réussissent → la nouvelle métadonnée (filtre `gareorigine.id`) et les gardes compilent proprement. La doctrine est en place. Notons-la en mémoire.

Tout est fait pour ce lot. Récapitulatif.

## 1. Label des pièces enrichi
Le stock réapparaît dans l'autocomplete : `« Filtre à huile (stock : 12) »`, dans le `SearchController` (donc appro **et** dépannage). Lint OK.

## 2. Doctrine voyage resserrée (validée par toi)
« **L'origine planifie · l'intermédiaire réceptionne · le terminus clôture** », avec l'exception panne pour le car.

Concrètement, dans [VoyageGuard](BK-Transport/src/Security/VoyageGuard.php) j'ai séparé deux natures d'action :

| Action | Garde | Qui |
|---|---|---|
| Créer / modifier voyage, **commercial**, supprimer | **`assertPeutPlanifier` (nouveau)** | **Origine** (+ admin/central) |
| Affecter / changer **car**, **chauffeur** | `assertPeutGerer` (inchangé) | Toute gare **sauf terminus** → couvre la **panne** |
| Réceptionner | `assertPeutReceptionner` | Intermédiaire |
| Clôturer | `assertPeutCloturer` | Terminus |

Rebranchements : `VoyageProcessor` (création **et** modification), `AffectCommercialProcessor`, suppression voyage (`SoftDeleteProcessor`) → **origine-only**. `AffectcarProcessor` + `AffectpersonnelProcessor` → **inchangés** (exploitation, pour la panne).

Et l'UI suit : le select **ligne** de la création de voyage pointe désormais sur une ressource **`lignes_origine`** (nouveau cas dans `SearchController`) qui ne renvoie que les lignes dont **ta gare est l'origine** (admin/central → toutes). J'ai ajouté le filtre `gareorigine.id` sur l'entité `Ligne`. ⚠️ L'UI n'est que du confort — **le vrai verrou est côté API** (`assertPeutPlanifier`).

**Vérifié** : lint de tous les fichiers, `cache:clear` BK + FT OK (la nouvelle métadonnée de filtre + les gardes compilent). ⚠️ **Non cliqué au navigateur** (login agent) — à valider par toi : depuis une gare **intermédiaire**, tu ne dois plus pouvoir créer/modifier un voyage ni changer le commercial, **mais** tu dois toujours pouvoir affecter/changer le car (scénario panne).

## 3. Reste pour plus tard
Le **départ partiel** (une gare intermédiaire qui survend devient l'origine d'un nouveau départ, `provenance = sa gare`) est validé pour un 2ᵉ temps : il faudra un `Voyage.gareprovenance` + adapter manifeste/capacité/vente/réception/clôture à la fenêtre `[provenance → terminus]`. On l'attaque quand tu veux.

Dis-moi quand tu as pu tester la doctrine, ou si on enchaîne sur le départ partiel.










 J'ai l'erreur suivante quand je vais sur le listing des tickets, voyages, bagages, courriers, réservations en tant que administrateur "The total number of joined relations has exceeded the specified maximum. Raise the limit if necessary with the "api_platform.eager_loading.max_joins" configuration key (https://api-platform.com/docs/core/performance/#eager-loading), or limit the maximum serialization depth using the "enable_max_depth" option of the Symfony serializer (https://symfony.com/doc/current/components/serializer.html#handling-serialization-depth)."

 #9 — Listing admin cassé (« max joined relations ») : Voyage porte maintenant 4 relations Gare (origine/terminus/provenance/courante) → la sérialisation imbriquée dépassait la limite de 30 jointures. J'ai relevé eager_loading.max_joins: 50 (api_platform.yaml) + réduit gareprovenance au seul groupe read:Voyage. Confirmé max_joins: 50 appliqué.







Pour les notions de (voyages, bagage, tickets, courriers), on vas se mettre dans un scénario avec 3 gares(la gare de provenance, la gare intermédiaire et la gare de destination) dans les 2 cas de voyages(départ complet puis départ partiel) en tenant compte des gardes de gare, prend en compte le fait que les règles sont à appliquer côté backend et frontend et aussi sache que certaines règles sont déjà en place :

```
- Le départ complet
    > On prend en exemple la ligne : Abidjan(gare 1 : origine) -> Bouaké(gare 2 : intermédiaire) -> Korhogo(gare 3 : terminus)
    > Abidjan crée un voyage sur la ligne
        > Abidjan peut (modifier, supprimer, affecter un car, affecter un personnel, affecter un commercial, démarrer) le voyage
        > Abidjan peut (vendre des tickets, enregistrer des bagages et courriers) sur le voyage
            > Pour les bagages il peut modifier, supprimer ou imprimer que ceux qu'il a crée
                > On ne doit pouvoir imprimer le pdf d'un bagage que s'il a un billet lié et non s'il est embarqué
            > Pour les tickets il peut modifier, supprimer, désister ou imprimer que ceux qu'il a crée
            > Pour les courriers il peut modifier, supprimer, annuler, imprimer que ceux qu'il a crée
                > On ne doit pouvoir imprimer le pdf d'un courrier que si la 'garedepart' et 'garearrivee' est saisi et non s'il est en transit
    > Au niveau de Bouaké
        > Bouaké peut (réceptionner, changer le car, affecter du personnel) le voyage
        > Bouaké peut (vendre des tickets, enregistrer des bagages et courriers) sur le voyage
            > Pour les bagages, il peut déclarer comme perdu les bagages qui ont pour destination Bouaké aussi ne doit pas pouvoir les imprimés vu que ce n'est pas lui qui a crée, il peut (modifier, supprimer ou imprimer) que ceux qu'il a crée
            > Pour les tickets il peut modifier, supprimer, désister ou imprimer que ceux qu'il a crée
            > Pour les courriers, il peut (déclarer comme perdu, confirmer la livraison..) les courriers qui ont pour destination Bouaké aussi ne doit pas pouvoir les imprimés, il peut (modifier, supprimer, annuler, imprimer) que ceux qu'il a crée
    > Arrivée à Korhogo
        > Korhogo peut clôturer le voyage et ne vend pas sur le voyage..
        > Pour les bagages, il peut déclarer comme perdu les bagages qui ont pour destination Korhogo aussi ne doit pas pouvoir les imprimés
        > Pour les courriers, il peut (déclarer comme perdu, confirmer la livraison..) les courriers qui ont pour destination Korhogo aussi ne doit pas pouvoir les imprimés

- Le départ partiel
    > On prend en exemple la même ligne : Abidjan(gare 1 : origine) -> Bouaké(gare 2 : intermédiaire) -> Korhogo(gare 3 : terminus)
    > Bouaké crée un voyage sur la ligne ce qui a pour effet du changer la 'gareprovenance' du voyage
        > Bouaké peut (modifier, supprimer, affecter un car, affecter un personnel, affecter un commercial, démarrer) le voyage
        > Bouaké peut (vendre des tickets, enregistrer des bagages et courriers) sur le voyage
            > Pour les bagages il peut modifier, supprimer ou imprimer que ceux qu'il a crée
            > Pour les tickets il peut modifier, supprimer, désister ou imprimer que ceux qu'il a crée
            > Pour les courriers il peut modifier, supprimer, annuler, imprimer que ceux qu'il a crée
        > En gros ce que la gare d'origine peut faire
    > S'il y'a des gares intermédiaires ils peuvent faire ce qu'une gare intermédiaire peut
    > Arrivée à Korhogo
        > Korhogo peut clôturer le voyage et ne vend pas sur le voyage..
        > Pour les bagages, il peut déclarer comme perdu les bagages qui ont pour destination Korhogo aussi ne doit pas pouvoir les imprimés
        > Pour les courriers, il peut (déclarer comme perdu, confirmer la livraison..) les courriers qui ont pour destination Korhogo aussi ne doit pas pouvoir les imprimés
        > En gros ce que la gare de terminus peut faire
    > Abidjan n'a pas d'action à faire sur ce voyage
```







Je propose que nous réorganisions les statistiques liées aux recettes. Actuellement, elles sont trop dispersées dans l'interface, ce qui nuit à la lisibilité et entraîne des répétitions.

L'objectif est de regrouper toutes les informations relatives aux recettes dans une même section afin d'offrir une présentation plus claire, plus cohérente et plus agréable pour l'administrateur, tout en évitant les doublons.








Bonne idée de repartir du concret. Je vais lire le code réel de la réservation (entité, services, processors, endpoints, front) pour t'en donner une explication fidèle et à jour, pas juste de mémoire.

Le module a beaucoup grossi depuis mes notes (phase 1 backend). Je lis les fichiers cœur pour comprendre le flux complet.

Maintenant le frontend (guichet agent + site public invité) :

J'ai maintenant une vue complète du module (il a beaucoup grandi depuis la phase 1 : paiement, webhook, régularisation no-show, expiration cron, site public invité). Voici le principe et le fonctionnement, du concept au code réel.

## Le concept en une phrase

On réserve **une PLACE** (pas un siège précis) sur un **tronçon** `montée → descente` d'un voyage, **à l'avance** — éventuellement **avant même qu'un car soit affecté**. Le siège concret n'est attribué qu'au tout dernier moment, à **l'émission du billet**. Deux canaux partagent exactement le même moteur : le **guichet** (agent connecté) et le **mobile/web invité** (client sans compte).

## Les deux entités

**`Reservation`** — le « bon » de réservation :
- `code` (`RES-2026-N`), `client` (identité durable dédupliquée par téléphone) + `nomclient`/`contactclient` (snapshot)
- `voyage`, `gare` (montée), `garedescente`, `prix` **verrouillé** à la création depuis la grille tarifaire
- `statut`, `dateexpiration`, `source` (`GUICHET` | `MOBILE`), `ticket` (rattaché à l'émission)
- Paiement : `etatpaiement` (`EN_ATTENTE_PAIEMENT` | `PAYE` | `ECHEC`), `referencepaiement`, `datepaiement`
- Régularisation : `penalitemontant`, `montantcomplement`
- **Périmètre gare = `['gare', 'garedescente']`** (périmètre B, 2 gares) : la réservation ne concerne que sa montée et sa descente. La montée **agit** (confirme/émet), la descente ne fait que la « voir arriver ».

**`ParametreReservation`** — un singleton par entreprise, auto-créé avec les défauts :
- `delaiExpirationMinutes` (défaut **120**) : on peut réserver jusqu'à N min avant le départ
- `penaliteType` (`AUCUNE`|`FIXE`|`POURCENTAGE`) + `penaliteValeur` : pénalité de régularisation
- `fenetreRegularisationJours` (défaut **7**) : combien de jours un no-show payé reste récupérable

## Les 5 statuts (`ReservationStatus`)

| Statut | Sens |
|---|---|
| `EN_ATTENTE` | Créée, en attente de paiement |
| `CONFIRMEE` | Payée ; billet émis ou à émettre |
| `A_REGULARISER` | Payée mais **no-show** à l'échéance → récupérable (report + pénalité) |
| `EXPIREE` | Perdue (impayée échue, ou fenêtre de régularisation dépassée) |
| `ANNULEE` | Annulée par client/agent → place libérée |

## Le cycle de vie complet

```
                         ┌──────────── création ────────────┐
        GUICHET (agent)  │   ReservationCreationService      │  MOBILE (invité, /reserver/{slug})
        POST /reservations│  (moteur PARTAGÉ, sous verrou)   │  POST /reservation/reservations
                         └───────────────┬───────────────────┘   + initier paiement (simulé)
                                         ▼
                                   [ EN_ATTENTE ]  ── place seulement INDICATIVE ──┐
                                         │                                          │
              paiement guichet           │            paiement mobile              │ expiration
      PATCH /{id}/confirmer  ◄────────────┼────────────►  webhook /paiement/webhook │ (impayée échue)
      (ConfirmerReservation)             │            (PaiementWebhookProcessor)    ▼
                                         ▼                                    [ EXPIREE ]
                                   [ CONFIRMEE + PAYE ]  (aucun siège encore)
                                         │
              PATCH /{id}/emettre-billet │  car affecté requis, avant la deadline
              (EmissionBilletService)    │  → attribue 1er siège LIBRE du tronçon
                                         ▼
                                 Ticket VALIDE émis  (canal RÉSERVATION)
                                 reservation.ticket = billet
                                         
   ── no-show (cron app:reservations:expirer) ──►  [ A_REGULARISER ]
        PATCH /{id}/regulariser?voyage=  → report + pénalité (+ complément si tarif↑) → billet émis
        fenêtre dépassée ───────────────────────►  [ EXPIREE ]
```

**Détails clés de chaque étape :**

1. **Création** (`ReservationCreationService::creer`, partagé guichet + public) : refuse un voyage clôturé / sans ligne / sans date ; calcule la fenêtre `départ − délai` ; un agent est **borné à sa gare** (sauf canal MOBILE) ; descente par défaut = terminus ; contrôle `ordreMontée < ordreDescente` et le **départ partiel** (rien avant la provenance effective du voyage) ; verrouille le `prix` depuis la grille tarifaire ; find-or-create du `Client` ; **vérifie la capacité sous verrou pessimiste** ; persiste en `EN_ATTENTE`. Le public y ajoute : résolution de l'entreprise par `slug`, **rate-limit par IP**, et initiation du paiement.

2. **Confirmation = encaissement** (le paiement **ne dépend pas du car**) : au guichet `payer()` réussit immédiatement (simulé) ; en mobile, le front simule puis appelle le **webhook idempotent**. → `CONFIRMEE` + `PAYE`, **toujours aucun siège**.

3. **Émission du billet** (« bon payé → billet de gare ») : exige `CONFIRMEE`+`PAYE`, un **car affecté**, avant la deadline. `EmissionBilletService` attribue le **premier siège libre sur tout le tronçon** et crée un `Ticket VALIDE` marqué **canal réservation** (donc exclu de la recette de gare — reconnu au paiement, cf. le rework 3 canaux). La réservation reste `CONFIRMEE` et pointe vers son billet.

4. **No-show & régularisation** : le cron `app:reservations:expirer` fait 3 transitions de masse : `EN_ATTENTE` échue → `EXPIREE` ; `CONFIRMEE` payée non émise et échue → `A_REGULARISER` ; `A_REGULARISER` hors fenêtre → `EXPIREE`. La régularisation propose un **aperçu** (`GET /{id}/regularisation?voyage=`, ne persiste rien) puis **reporte** sur un nouveau départ : encaisse **pénalité** (politique compagnie) + **complément** si le tarif a monté (jamais de remboursement si moins cher), émet le billet, journalise `RESERVATION_REGULARISEE`.

## ⚠️ La subtilité capacité (elle a évolué — et une doc est périmée)

C'est le point le plus important à ré-intégrer. Dans `CapaciteService` **aujourd'hui** :
- La **capacité effective** = sièges du car si affecté, sinon `voyage.placesprevues` (prévisionnel), sinon `null` → 0 dispo.
- **Seuls les billets `VALIDE` (émis) consomment réellement une place.** Les réservations non encore émises **ne bloquent pas** la capacité : elles sont **purement indicatives** (`placesReservees()` sert juste à afficher « X places réservées sur ce tronçon » à l'agent).

Autrement dit, le modèle est désormais **tolérant à l'overbooking** : une place n'est physiquement consommée qu'à l'émission du billet, pas à la réservation.

**Incohérence à noter** : le docblock de l'entité `Reservation` (lignes 30-37) affirme encore *« une réservation EN_ATTENTE tient une place : elle compte dans la capacité… côté vente de billets »* — c'est **contredit** par le code réel de `CapaciteService` (lignes 63-66). Un commentaire à redresser quand on repassera dessus.

## Le paiement est simulé

`PaiementSimuleProvider` implémente `PaiementProviderInterface` : guichet = succès immédiat, en ligne = pas de page hébergée (`estSimule=true`, `url=null`), c'est le front qui simule puis tape le webhook. Pour brancher un vrai **Mobile Money** : nouvelle implémentation de l'interface + **vérification de signature** du webhook (déjà signalé en `⚠️` dans `PaiementWebhookProcessor`).

## Côté frontend

- **Guichet** : `/reservation` (liste), `/reservation/nouveau`, `/reservation/{id}` (show avec les actions confirmer / émettre / régulariser / annuler), `/reservation/config` (paramètres, admin).
- **Site public invité** : `/reserver/{slug}` — sélection villes/gares/destinations/départs, création + webhook simulé, `/suivi`, `/historique`, et `/bon` (bon de réservation imprimable).

---

En résumé, la réservation est un **flux en 3 temps découplés** — *tenir une place → encaisser → matérialiser en billet+siège* — avec un filet de sécurité no-show (régularisation) et une expiration automatique, le tout partagé entre guichet et mobile par un moteur unique.

Tu veux qu'on reprenne le travail sur un point précis ? Je pense notamment à : corriger l'incohérence du docblock capacité, brancher un vrai prestataire de paiement, planifier réellement le cron d'expiration, ou revoir la tolérance à l'overbooking (faut-il vraiment que les réservations ne réservent rien physiquement ?).















Transport :

    Point important : previous_data est un clone — persister celui-là créerait un doublon. C'est read_data qui porte l'entité gérée.

    Cause trouvée : sans variable d'URI et en méthode non sûre, API Platform décide de ne pas lire — le provider ne tournait jamais.
        new Patch(
            uriTemplate: '/me/motdepasse',
            security: "is_granted('ROLE_USER')",
            read: true, /*
                - EXPLICITE, ET INDISPENSABLE. `ReadListener` decide de lire
                    quand l'operation a des variables d'URI OU que la methode est
                    sure : `/me/motdepasse` n'a ni l'un ni l'autre, donc le
                    provider ne tournait pas et le processor recevait `read_data`
                    a null -- une 500 sur un chemin nominal. Mesure le 11/09/2026.
            */
            inp

Dans la partie Frontend.., pour le Dashbord je veux qu'il soit au style réel de Shadcn (→ ui.shadcn.com) en (HTML, Css, etc...), responsive, moderne, etc...
Tu n'a pas mis de sidebar dans le Dashbord ! Quand je dis que je veux que le tableau de bord soit au style réel de shadcn/ui (→ ui.shadcn.com) je faisais allusion au sidebar-07 de shadcn/ui (→ ui.shadcn.com) !

Deux pièges d'API Platform trouvés en route, documentés en §8.10 et §8.11 : **sans variable d'URI, une opération non sûre ne lit pas** (`/me/motdepasse` levait une 500 sur son chemin nominal — corrigé par un `read: true` explicite), et **`previous_data` est un clone** alors que `read_data` est l'entité gérée — persister le premier aurait tenté d'attacher un détaché portant le même identifiant.











## New

Salut Claude Code,

J'ai développé une application de compagnie de transport multi-entreprises et multi-gares, en architecture séparée (backend/frontend) :

## Stack technique
- **Backend** : Symfony, API Platform, LexikJWTAuthenticationBundle (lexik/jwt-authentication-bundle), RefreshTokenBundle (gesdinet/jwt-refresh-token-bundle)
- **Frontend** : Symfony, Twig, React, Ux Symfony (symfony/ux-react, symfony/ux-toolkit, etc...), Shadcn/ui, Tailwind v4

## Contexte
- Le backend dispose d'un `README.md` (dans `Backend-Transport`) dans lequel j'ai listé quelques informations sur les modules de l'application — à consulter en priorité pour comprendre la structure.
- Il existe aussi des applications mobiles (réservation client + commercial) à prendre en compte dans l'analyse globale.
- **Ignore tous les fichiers `brl.md`** : ce sont juste des brouillons.

## Ce que j'attends
Analyse les projets (backend et frontend) afin de bien comprendre :
- l'architecture générale et les modules existants,
- les liens/flux entre backend, frontend et applications mobiles,
- les points clés à connaître avant que je te donne la suite des instructions,
- etc...






I'll start by exploring the project structure to understand what we're working with.

Four projects. Let me read the backend README first, as you suggested.

Dense README — that's a great map. Now let me explore the actual code structure.

J'ai parcouru les quatre projets. Voici ma lecture.

## Cartographie

| Projet | Rôle | Stack |
|---|---|---|
| `Backend-Transport` | API REST, **seule** source de vérité (métier + données) | Symfony, API Platform, LexikJWT + Gesdinet refresh, MySQL |
| `Frontend-Transport` | Back-office web interne — **BFF sans base de données** | Symfony/Twig + UX React, Shadcn, Tailwind v4 |
| `commercialflutter` | Vendeur à bord (authentifié JWT) | Flutter, Riverpod 3, Dio, freezed, go_router |
| `resaflutter` | Client invité, réservation + suivi (anonyme) | idem |

---

## Backend — le pipeline API Platform comme colonne vertébrale

Tout passe par API Platform, jamais à côté. Le découpage est constant :

- **`src/Entity/`** (~48 entités) porte les `#[ApiResource]`, la `security:` par opération et les groupes de sérialisation. [User.php](Backend-Transport/src/Entity/User.php) et `Typepiece` servent de documentation vivante des options.
- **`src/State/`** (~115 providers/processors) = toute l'écriture et les lectures composées. Un endpoint métier = un `uriTemplate` + un processor (`/voyages/{id}/receptionner` → `ReceptionnerVoyageProcessor`).
- **`src/Doctrine/`** = 5 extensions qui s'appliquent **automatiquement** à toutes les collections/items.
- **`src/Domain/Service/`** = le métier réutilisable (`CapaciteService`, `ReservationEcheanceService`, `RecetteGareService`…), appelé par plusieurs processors.
- **`src/Entity/Data/`** = ressources **virtuelles non persistées** (`Corbeille`, `ReservationPublique`, `Statistique`) : elles donnent une URL propre à des choses qui ne sont pas des tables.

Le principe que je retiens le plus : **se brancher au pipeline plutôt que le remplacer** (`InventaireProvider`, `TicketProvider` décorent le provider natif pour garder pagination/filtres/tri).

## Les trois couches de sécurité, superposées

Elles se cumulent, et il faut penser aux trois à chaque nouvel endpoint :

1. **Périmètre entreprise** — [EntrepriseScopeExtension](Backend-Transport/src/Doctrine/EntrepriseScopeExtension.php) ajoute `identreprise = X AND deletedAt IS NULL` dès que la ressource implémente `EntrepriseOwnedInterface`. Le super admin y échappe entièrement — d'où le piège documenté : **un service ne doit jamais dépendre de l'entreprise de l'acteur** (le super admin n'en a pas), `CorbeilleService` en tire les conséquences.
2. **Périmètre gare** — [GareScopeExtension](Backend-Transport/src/Doctrine/GareScopeExtension.php) avec 3 régimes selon l'interface : `GareOwnedInterface` (1 champ), `MultiGareScopedInterface` (départ **ou** arrivée), `LigneGareScopedInterface` (la ligne dessert ma gare, via `EXISTS` — et non un `JOIN`, sinon l'eager-loading tronque la collection `arrets`). Inactif pour les admins et les centraux sans gare.
3. **Permissions RBAC** — [PermissionVoter](Backend-Transport/src/Security/Voter/PermissionVoter.php), attributs `VOIR/CREER/MODIFIER/SUPPRIMER/IMPRIMER/IMPORTER/EXPORTER` résolus en une requête. `ROLE_ADMIN` bypasse tout ; `ROLE_ADMIN_GARE` bypasse **uniquement** les entités de `GareScopedEntities::ENTITIES`.

À quoi s'ajoutent des gardes métier explicites : `VoyageGuard` (origine prépare / intermédiaire réceptionne / terminus clôture), `UserManagementGuard` (hiérarchie de gestion des comptes), `UserChecker` + `JWTSubscriber` (le checker ne suffit pas — un suspendu avec un JWT encore valide continuerait à passer), `MaintenanceSubscriber` (503 avec whitelist, **fail-open**).

## Frontend — un BFF, pas une SPA

Point structurant : **le FT n'a pas de base de données**. `ApiUser` n'est pas une entité Doctrine, il est hydraté depuis `/api/me` et injecté dans le token Symfony via `ApiUserProvider`.

Le flux d'auth est astucieux et vaut la peine d'être gardé en tête :

```
ApiAuthenticator → login_check → {token, refresh_token} en session
                 → RememberMeBadge + refresh_token dans un cookie persistant
RefreshTokenCookieSubscriber → met à jour le cookie à chaque refresh réussi
AuthenticationExceptionListener → toute 401 API redirige vers /login
```

Sans le subscriber le cookie ne serait jamais renouvelé (`ApiClientService` n'a pas accès à la `Response`) — le « remember me » mourrait silencieusement à l'expiration du premier refresh token.

Côté rendu : Twig fait le layout et les permissions (`is_granted('TICKET_CREER')` → [PermissionVoter FT](Frontend-Transport/src/Security/Voter/PermissionVoter.php) qui découpe `ENTITÉ_ACTION`), puis délègue les tableaux/formulaires à React via `react_component(...)` en passant **les données déjà chargées et les droits déjà résolus** en props. Le filtrage/tri/pagination est serveur (`TableHelper` + `TableQueryBuilder` + `useServerTable`). Les selects lourds passent par `SearchController` + `RemoteCombobox`.

## Flux entre les quatre applications

```
                    ┌──────────────────────────────────────┐
                    │   Backend-Transport (API Platform)    │
                    └───┬──────────────┬───────────────┬────┘
      JWT en session    │              │ JWT Bearer    │  PUBLIC_ACCESS + ?slug=
      + cookie remember │              │ + refresh 401 │  (anonyme)
                        │              │               │
              Frontend-Transport   commercialflutter   resaflutter
              (back-office web)    (vendeur à bord)    (client invité)
```

Trois surfaces d'API bien distinctes, à ne pas confondre :

- **Interne authentifiée** — `/api/*` (ressources + endpoints métier), consommée par le FT et le commercial.
- **Espace commercial** — endpoints dédiés `/api/voyages/me/commercial`, `/api/voyages/{id}/me/ventes`, `/api/voyages/{id}/me/manifeste`. Ils existent **précisément parce que** les collections standard sont filtrées par `GareScopeExtension`, ce qui masquerait les ventes faites en route hors de la gare d'attache.
- **Publique** — [ReservationPublique.php](Backend-Transport/src/Entity/Data/ReservationPublique.php), 9 opérations `/api/reservation/*` en `PUBLIC_ACCESS`, multi-tenant par `?slug=`. C'est la seule surface de `resaflutter`. La façade web équivalente (`WebClientController`) est commentée.

Règle transverse claire : **le serveur décide, le client ne recalcule rien**. Les DTO publics exposent `heurepassage`, `retardMinutes`, `heurepassageEstimee` déjà calculés ; la réservabilité vient de `/api/voyages/reservables`, jamais reconstituée côté client.

## Concepts métier qui gouvernent tout le reste

- **Ligne = suite ordonnée d'`Arret`**, chacun portant `dureeTronconMinutes`. L'heure de passage est la **somme des tronçons depuis l'origine effective** — recalculée, jamais stockée. C'est la base des échéances de réservation, des retards et de la ponctualité. `RecalageService` propose (sans appliquer) une correction par médiane observée.
- **Priorité absolue à la gare amont** : la disponibilité se juge **au point de montée**, on compte des **sièges** et non des passagers. Le surbooking est assumé, l'**éviction** en est le pendant — dérivée par `CapaciteService::billetsEvinces()`, jamais stockée, sauf `desistementImputableCompagnie` (non re-dérivable une fois le billet passé `REPORTE`).
- **Départ partiel** : tout se calcule par rapport à l'origine **effective** du voyage, pas à celle de la ligne.
- **Dérivé plutôt que stocké**, partout : fidélité (aucun compteur), retards, éviction, corbeille (27 types découverts automatiquement), alertes (balayeur idempotent qui réconcilie via une clé de dédup). Conséquence pratique : **relancer un traitement n'a jamais d'effet de bord**, et il n'y a pas de compteur à re-synchroniser.

## Points d'attention avant d'attaquer la suite

1. **Le build FT est actuellement cassé pour la prod.** `git status` montre les fichiers hashés supprimés (`app.9d72e751.js`…) et remplacés par des fichiers non hashés (`app.js`, `app.css`) — c'est une sortie `npm run dev`. Le `public/build/` étant versionné et le README prévoyant `npm run build` avant `git pull` en prod, un commit en l'état déploierait un build de développement.
2. Le `.env` du FT garde un `DATABASE_URL` PostgreSQL par défaut et un dossier `migrations/` vide, alors que Doctrine n'y sert à rien. Résidu du squelette Symfony, sans conséquence, mais trompeur.
3. Le README backend mentionne des apps mobiles « Flutter + React Native » ; je ne trouve que les deux projets Flutter dans l'espace de travail. L'app React Native est ailleurs, ou la mention est obsolète ?
4. Le paiement Mobile Money est **simulé** (`PaiementSimuleProvider`, webhook déclenché par l'app elle-même). Toute évolution de la réservation devra composer avec ce point d'extension.
5. Le fuseau est épinglé à **UTC** dans les deux `Kernel.php` — à ne pas toucher, c'est une correction de bug de production documentée.

Deux différences de nommage à garder en tête : `Trajet` → remplacé par `Ligne`/`Arret`, et `TarifLigne` → remplacé par la grille `Tarif` globale par entreprise.

J'ai le modèle en tête. Dis-moi la suite.









## 1. Docblock de `heurePassage()`

Il illustrait le calcul avec des durées **cumulées** (`Bouaké(240) → Korhogo(420)`), vestige du champ `dureeDepuisOrigineMinutes` renommé par la migration `Version20260722100000`. Il décrit maintenant le modèle réel — un tronçon par arrêt — et met les deux cas côte à côte, ce qui rend le décalage du départ partiel évident :

```
Ligne Abidjan(—) → Bouaké(+240) → Korhogo(+180)

Départ NORMAL d'Abidjan à 08:00        Départ PARTIEL de Bouaké à 08:00
  Bouaké  : 08:00 + 240      = 12:00     Bouaké  : 08:00 + 0   = 08:00
  Korhogo : 08:00 + 240 + 180 = 15:00    Korhogo : 08:00 + 180 = 11:00
```










Je remets le correctif dans les composants et je retire les règles CSS, pour ne pas laisser deux mécanismes concurrents.

C'est revenu à l'approche par les composants. **162 tests verts**, build et types propres.

Concrètement :

- `dropdown-menu.tsx` (contenu et sous-menu), `select.tsx`, `popover.tsx` → `z-[200]`
- `dialog.tsx`, `alert-dialog.tsx` (voile et contenu) → `z-[300]`, au-dessus des menus puisqu'on ouvre un dialogue *depuis* un menu

J'ai retiré les règles CSS que j'avais mises entre-temps (`[data-radix-popper-content-wrapper]` et les `data-slot` des dialogues) : garder les deux mécanismes en parallèle aurait fait deux endroits à corriger le jour où l'échelle bouge, avec un `!important` qui aurait masqué toute valeur posée dans un composant.

Il reste dans `header.css` la **note** documentant l'échelle — topbar 100 · sidebar 149/150 · menus 200 · dialogues 300 · notifications 3000 — avec une précision utile maintenant que le rang vit dans les composants : en reprenant un composant depuis shadcn/ui, il faudra penser à remonter son `z-index`, sinon il repassera sous la barre.

Le correctif du point 10 n'a pas bougé : le menu TomSelect reste ancré à son champ, et c'est le conteneur qui cesse de rogner pendant l'ouverture. :: Pour le datable pour ne pas la bulle s'affiche en dessous du header










Le serveur applique deux bornes différentes selon qui agit, et l'écran n'en connaissait qu'une :

gare émettrice — le car ne doit pas avoir quitté la gare de montée (monteedepassee)
commercial embarqué — le car doit être encore positionné sur cette gare

Ce n'est pas un détail : garecourante n'avance qu'à la réception. Entre le départ de Bouaké et l'arrivée à l'escale suivante, la gare est fermée mais le commercial, lui, est toujours à bord. Et comme il encaisse après le départ, ses propres billets naissent déjà monteedepassee — je lui masquais « Modifier » sur ce qu'il venait d'émettre, alors que l'API l'acceptait.








## Un blocage trouvé en route

La nouvelle permission était **inassignable** : le formulaire des rôles n'offrait que `['VOIR','CREER','MODIFIER','SUPPRIMER']` en liste plate. Au passage, cela veut dire que **`IMPRIMER` et `EXPORTER` ne sont pas assignables non plus** aujourd'hui, alors que le backend les gère et que tes fixtures s'en servent — un rôle créé dans l'application ne peut pas donner le droit d'imprimer.

J'ai introduit des **actions spécifiques par entité** (`ACTIONS_SPECIFIQUES`), rendues comme une colonne qui n'a de case que sur sa propre ligne. Vérifié dans le navigateur sur les trois écrans :

| Écran | Constat |
|---|---|
| Nouveau rôle | Colonne `DESISTER` présente ; **1 seule case** sur 30 entités, celle de Ticket |
| Modifier (Commercial à bord) | Case présente et **cochée** — la reprise de migration, visible pour décochage |
| Fiche du rôle | Colonne rendue (une liste figée dans le gabarit la masquait) |

Un `{% set %}` dans une boucle Twig ne survivant pas à la boucle, les colonnes sont calculées côté PHP.

**Il te reste une action** : décocher `DESISTER` sur le rôle « Commercial à bord » dans `/admin/roles/79/modifier`. Sans effet sur la sécurité — le processor le refuse — mais la configuration doit dire la vérité. Je peux le faire si tu veux.

## L'inventaire des cas du même genre

J'ai extrait les 90 opérations personnalisées avec leur garde. Trois groupes.

### Confusions réelles — `MODIFIER` gouverne un mouvement de stock

Ce sont les jumeaux exacts du cas Ticket : la même permission sert à corriger un libellé et à écrire en stock. Vérifié dans les processors.

| Opération | Ce que `MODIFIER` autorise en plus |
|---|---|
| `/pieces/{id}/ajuster` | **Ajustement d'inventaire** — écriture de stock arbitraire |
| `/approvisionnements/{id}/annuler` | Mouvements **SORTIE** : retire du stock déjà entré |
| `/depannages/{id}/annuler` | Mouvements **ENTREE** : restaure les pièces consommées |

C'est le groupe que je traiterais en premier, sur le modèle de `DESISTER`.

### Confusions réelles — `MODIFIER` gouverne la caisse ou un engagement

| Opération | Effet |
|---|---|
| `/reservations/{id}/confirmer` | **Encaissement du paiement** |
| `/reservations/{id}/regulariser` | Régularisation financière |
| `/reservations/{id}/annuler` | Annulation d'une réservation payée |
| `/courriers/{id}/perdu`, `/bagages/{id}/perdu`, `/detailcourriers/{id}/perdu` | Déclaration de perte |
| `/courriers/{id}/livrer` | Remise au destinataire (décharge) |
| `/voyages/{id}/demarrer`, `/cloturer` · `/depannages/{id}/cloturer` | Bascules de cycle irréversibles |
| `/personnels/{id}/suspendre` | Suspension d'un agent |

Précision honnête : les trois opérations « perdu » et l'annulation de courrier ne déplacent **aucun montant** dans le code actuel — elles posent un statut. L'enjeu est l'engagement commercial, pas la caisse. Je ne les mets pas au même rang que le stock.

Un point à part sur les réservations : la garde est `is_granted('MODIFIER', object) **or** is_granted('CREER', 'Ticket')`. Le `or` fait qu'un profil n'ayant que le droit de vendre des billets peut encaisser et annuler des réservations, sans aucune permission sur `Reservation`. C'est peut-être voulu — le vendeur prend le paiement — mais ça mérite d'être tranché explicitement.

### Ce qui n'est *pas* un problème, contrairement aux apparences

`/voyages/{id}/receptionner` et `/repartir` sont en `ROLE_USER`, `/avancer` en `IS_AUTHENTICATED_FULLY`. J'ai lu les processors avant de conclure : chacun porte sa propre garde d'identité — position du car, gare où il se trouve, identité du commercial. Ce ne sont pas des trous, mais des droits **positionnels** volontairement hors du RBAC. La conséquence à connaître : un super admin ne peut ni les accorder ni les retirer par rôle.

Enfin, une incohérence mineure : la plupart des `/remove` utilisent `SUPPRIMER`, mais `Bagage`, `Courrier`, `Approvisionnement`, `Depannage` et `Ticket` exigent `ROLE_ADMIN`. Pour `Ticket` c'est commenté et assumé (document comptable) ; pour les quatre autres, rien ne l'explique.

Dis-moi par quel groupe tu veux continuer — je partirais du stock, c'est le plus proche de ce qu'on vient de faire et le plus quantifiable.














Analyse l’application dans son ensemble et propose des fonctionnalités pertinentes en tenant compte :

* des fonctionnalités déjà existantes ;
* des besoins métier d’une entreprise de transport ;
* de l’expérience utilisateur (UX/UI) ;
* de la sécurité ;
* des performances ;
* de la maintenance et de l’évolutivité ;
* de l’automatisation des tâches ;
* des besoins d’administration et de reporting.

Pour chaque fonctionnalité proposée, explique :

1. **Le problème ou besoin auquel elle répond** ;
2. **Son fonctionnement général** ;
3. **Sa valeur ajoutée pour l’application** ;
4. **Sa priorité** : faible, moyenne, haute ou critique ;
5. **Les modules concernés** ;
6. **Les éventuelles dépendances ou impacts techniques**.

Évite de proposer des fonctionnalités génériques ou inutiles. Concentre-toi sur des améliorations réellement pertinentes pour cette application et son contexte métier.

Enfin, classe les propositions par ordre de priorité et distingue :

* les améliorations des fonctionnalités existantes ;
* les nouvelles fonctionnalités ;
* les améliorations UX/UI ;
* les améliorations techniques et de sécurité ;
* les fonctionnalités avancées pouvant être ajoutées à long terme.










On vas mettre en place la vente hors-ligne pour le commercial


Vente hors-ligne pour le commercial — c'est le levier B3 de la feuille de route, mais cadré sur le vendeur à bord, ce qui change beaucoup de choses. J'explore avant de proposer quoi que ce soit.

Pendant que l'exploration tourne, deux décisions de fond qui orienteront toute la conception — et qui t'appartiennent.


Deux vendeurs peuvent viser le même siège pendant que le commercial est hors ligne (lui à bord, une gare en aval qui ignore sa vente). Comment veux-tu traiter ce conflit ?

Optimiste + éviction existante (Recommandé)

Que doit pouvoir faire le commercial sans réseau ?

Vendre un billet et l'imprimer, Faire avancer la position du car, Enregistrer un bagage, Consulter le manifeste des passagers


Les trois explorations sont revenues, et elles changent la conception sur un point majeur.

**Le bloqueur principal n'est pas le siège, c'est le `codeticket`.** Il est généré serveur par `COUNT(*) + 1` sur le voyage — et il n'existe **aucun index unique dessus**. Deux appareils émettraient le même code sans que rien ne proteste : duplication silencieuse, pas rejet. Le reçu PDF est pourtant imprimé hors ligne avec ce code en QR.

Deux bonnes nouvelles en revanche : le prix est **entièrement calculable hors ligne** (grille plate `gare→gare` + un plafond de remise en entier), et il n'y a **qu'un seul commercial par voyage** — sa série de codes ne peut donc pas entrer en collision avec celle du guichet.

Une dernière décision avant de rédiger.

Le téléphone calcule le prix depuis sa grille téléchargée et le commercial encaisse en espèces. À la synchronisation, si la grille a changé entre-temps, qui fait foi ?

Quand tu dis "si la grille a changé entre-temps", est ce que tu fais allusion à la modification de tarif par l'admin ou à l'avancement du car qui change la grille et le tarif ?

Faut-il borner la vente hors ligne, pour limiter la casse si un appareil reste déconnecté longtemps ?

Borner au voyage en cours (Recommandé)



Bonne question, et la réponse réduit beaucoup l'enjeu.

**L'avancement du car ne change pas la grille.** Elle est indexée par *couple de gares*, indépendamment de la ligne, du voyage et de la date — `TarifRepository::findMontant` ne regarde que `(garedepart, garearrivee, entreprise)`. Quand le car avance, c'est le *couple applicable* qui change, pas le tarif ; et comme le commercial pilote lui-même la position, il connaît le couple hors ligne. Aucun écart de ce côté.

Je parlais donc du seul autre cas : **un admin modifie un tarif pendant que le car roule.** C'est rare.

La question n'est donc pas de fréquence mais de principe — le prix peut-il, une fois, venir de l'appareil ?


Seule cause d'écart possible : un admin modifie un tarif pendant le trajet. Rare — mais qui tranche le prix d'un billet vendu hors ligne ?

Le serveur recalcule, l'écart est signalé (Recommandé)



Toutes les décisions sont prises. Je vérifie deux points avant de rédiger — l'état réel des codes en base, et la structure exacte du processor à factoriser.

Aucun doublon aujourd'hui (261/261) — l'index unique passera. Je regarde la structure du processor à factoriser.

Tout est clair. Je rédige le plan.

Plan approuvé. Je commence par le backend — le contrat d'API conditionne tout le reste côté Flutter.

Je commence par l'entité. Regardons où poser les deux champs.

Maintenant la migration, avec l'index unique manquant sur `codeticket`.

Migration passée, index en place. Maintenant le mode « vente différée » — un seul pipeline, un indicateur, et une stratégie d'écriture injectée.

La base de **test** n'a pas la migration. Je regarde comment son schéma est créé.

La base de test a été créée par `schema:create`, pas par les migrations. Je la synchronise sur le mapping.

Le schéma de test diverge du mien : l'index sur `codeticket` n'existe que dans la migration, pas dans le mapping. Je déclare les deux dans l'entité pour qu'ils s'accordent.

Le schéma de test correspond maintenant exactement à la migration. Je relance.

Le refactor ne casse rien. Maintenant le service de synchronisation — je regarde les patrons à suivre.

J'ajoute le type d'événement pour l'écart de tarif.

Maintenant le service de synchronisation.

Maintenant le point d'entrée — le premier endpoint de l'API à accepter un lot.

Route en place. J'écris maintenant les tests qui verrouillent le comportement.

Les huit passent du premier coup. Je poursuis avec l'avance de position, qui doit pouvoir être rejouée.

La règle est auto-portante. Je l'extrais pour que le rejeu et la voie en ligne partagent exactement le même code.

L'avance est factorisée. J'ajoute maintenant le rejeu de position au lot de synchronisation.

Je complète les tests avec le rejeu de position.

Neuf tests au vert. Reste l'instantané — ce que le téléphone emporte pour vendre seul.

Deux corrections : `findMontant` rend un `Tarif`, pas un entier, et le nom de la raison sociale diffère.

Je couvre l'instantané par un test, puis je vérifie l'ensemble du backend.

Backend terminé — 176 tests au vert. Je passe au Flutter. D'abord, vérifions l'outillage disponible.

Flutter 3.47 disponible — je pourrai analyser et tester. Je lis les fichiers clés pour coller au style.

Maintenant les dépendances, puis la couche de persistance locale — la première du projet.

`intl` est épinglé à 0.20.2 alors que le SDK en réclame 0.20.3. C'est un montée de patch exigée par `flutter_localizations`, pas un choix que je contourne.

Les paquets sont résolus — l'avertissement porte sur la *compilation* d'une plateforme, pas sur la résolution, et `printing` était déjà un plugin. Je vérifie que l'analyse tourne.

L'analyse tourne et le projet est propre. Je crée la couche hors-ligne.

La couche est propre. Je la branche maintenant dans l'application — d'abord les providers.

Maintenant la génération locale du code de billet — la pièce qui rend le reçu imprimable hors ligne.

La couche est en place. Maintenant le cœur : brancher la vente hors ligne dans le tunnel existant.

Il faut aussi que le plan de sièges et le tarif viennent de l'instantané. Je regarde le repository, qui est le point d'injection.

`TicketVendu.id` est déjà nullable — une vente hors ligne peut donc produire un billet complet sans identifiant serveur. Je regarde le modèle de siège.

Je crée la source locale qui rejoue, depuis l'instantané, exactement les mêmes règles que le serveur.

Je fais maintenant basculer le repository — c'est lui qui décide, à l'échec réel, entre distant et local.

L'application analyse proprement. Il manque l'armement — sans instantané, rien de tout cela ne sert.

Il reste la visibilité — le vendeur doit savoir ce qui attend. Puis les tests.

Maintenant les tests Dart — la file est la pièce qui doit être irréprochable.

Les sept passent. Vérification complète des deux côtés.

Je fais maintenant la vérification bout en bout prévue au plan, sur l'API réelle.

L'instantané est complet. Je simule maintenant la remontée d'une file, comme le ferait le téléphone.

La vente hors ligne du commercial est en place, vérifiée de bout en bout sur l'API réelle.

## Le verrou, et comment il est levé

Le blocage n'était pas le siège, c'était le **`codeticket`**. Il est généré serveur par `COUNT(*) + 1`, sans aucun index unique : deux appareils auraient émis le même code **sans la moindre erreur**. Or le reçu part de l'imprimante avec ce code en QR.

Deux réponses. Le téléphone génère désormais une **série dédiée au bord** — `…-TCK-2026-B3` — sûre parce qu'un voyage n'a qu'un seul commercial ; et l'**index unique manquant** est posé. Ce défaut existait déjà : une mise en corbeille faisait réutiliser un code émis, en silence. Base saine avant migration (261 billets, 261 codes distincts) — à revérifier en production, c'est écrit dans la migration.

## Ce qui a guidé le reste

**On ne défait pas une vente encaissée.** Le passager est assis, l'argent est dans la sacoche : refuser un billet à la synchronisation ne l'annulerait pas, cela le ferait disparaître du système. Les conflits de siège sont donc **acceptés** et l'éviction tranche — c'est exactement le rôle pour lequel elle existe.

**Le prix ne vient jamais de l'appareil.** Le téléphone calcule pour encaisser, le serveur recalcule depuis la grille. L'écart est consigné, pas appliqué : le contrôle anti-fraude reste entier.

J'ai évité de dupliquer la logique de vente — c'est le chemin le plus chargé en gardes de l'application. `TicketProcessor` a gagné un mode différé qui relâche **exactement trois** contrôles, chacun documenté à son point de relâchement. Même chose pour l'avance de position, extraite en service partagé entre la voie en ligne et le rejeu.

## Vérification bout en bout

Sur l'API lancée, avec un vrai commercial : instantané complet (4 arrêts, 70 sièges, 6 couples tarifaires, plafond 20 %), puis remontée d'une file de trois opérations.

| | |
|---|---|
| Premier envoi | 3 acceptés |
| **Rejeu du même lot** | **0 accepté, 3 déjà synchronisés** |
| Billet vendu 9 500 à bord | enregistré à **8 000** (grille), écart de 1 500 tracé au journal |
| Position | avancée à Korhogo, horodatée **07:55** — l'heure du téléphone, pas celle de l'envoi |

Données de vérification supprimées ensuite.

**176 tests backend** (dont 11 nouveaux sur le hors ligne) et **28 tests Flutter** (dont 7 sur la file), analyse Dart sans avertissement.

## Deux choses à savoir

**Une montée de `intl` en 0.20.3** a été nécessaire : la version épinglée 0.20.2 était incompatible avec le `flutter_localizations` du SDK courant, et bloquait toute résolution de dépendance.

**Le mode développeur Windows n'est pas activé** sur cette machine, donc `flutter build`/`run` échoue sur les liens symboliques. C'est préexistant — `printing` était déjà un plugin — et sans effet sur l'analyse et les tests. Je n'ai donc **pas pu lancer l'application sur un appareil** : le tunnel de vente hors ligne est vérifié par ses tests et par l'API, pas par un essai à l'écran.

Il reste deux éléments du plan que je n'ai pas faits : le **bagage hors ligne** et l'écran listant les opérations en attente avec leur sort. Le bandeau d'état signale déjà ce qui reste à remonter. Dis-moi si tu veux que j'enchaîne.










Quand je build l'application commercial et que je l'install sur mon `Android` via la commande `flutter build apk --release --dart-define=API_BASE_URL=https://proud-gauntlet-elongated.ngrok-free.dev` (`https://proud-gauntlet-elongated.ngrok-free.dev` vient de ngrok ou même quand c'est en ligne) et que j'essai de me connecté en que `commercial@ira-transport.ci` je tombe sur l'erreur `Impossible de joindre le serveur. Vérifiez votre connexion` !






La permission Internet n'est déclarée que pour le **debug**. Je vérifie.

Voilà la cause. Je corrige les deux applications.

Je reconstruis l'APK release et je vérifie la permission dans le paquet produit.

Le tunnel ngrok est éteint en ce moment (`ERR_NGROK_3200`), mais c'est justement ce qu'il me faut pour prouver la correction : je pose l'APK release sur l'émulateur.

La preuve est nette. Je vérifie aussi `resaflutter`, qui avait le même manque.

Trouvé, et ce n'est ni ngrok ni l'URL.

## La cause

La permission Internet n'était déclarée que dans les manifestes `debug` et `profile` — les fichiers du gabarit Flutter, où l'outil la pose pour **son** usage (hot reload, points d'arrêt). Le variant `release` ne les inclut pas :

```
debug/AndroidManifest.xml     → INTERNET ✓
profile/AndroidManifest.xml   → INTERNET ✓
main/AndroidManifest.xml      → rien
```

L'APK release ne pouvait donc **ouvrir aucune connexion**. Chaque appel échouait en `connectionError`, ce que l'application traduit exactement par « Impossible de joindre le serveur. Vérifiez votre connexion ». D'où le fait que ça se produise aussi bien avec ngrok qu'en ligne : l'adresse visée n'entrait jamais en jeu.

Détail révélateur : `connectivity_plus` apportait `ACCESS_NETWORK_STATE` au manifeste principal. L'application pouvait donc **observer** le réseau sans jamais s'en **servir** — c'est pour ça que le bandeau hors ligne ne se déclenchait pas non plus.

## La correction

`<uses-permission android:name="android.permission.INTERNET"/>` déclaré dans `main/AndroidManifest.xml`, là où tous les variants le voient.

## Vérifié

APK release reconstruit avec votre commande, puis inspecté et installé sur l'émulateur :

```
uses-permission: name='android.permission.INTERNET'          ← présent
cleartext                                                     ← absent (le release refuse le HTTP simple, comme voulu)
```

À la connexion, l'écran n'affiche plus « Impossible de joindre le serveur » mais **le message de ngrok lui-même** : « The endpoint proud-gauntlet-elongated.ngrok-free.dev is offline — ERR_NGROK_3200 ». La requête part donc du téléphone, traverse, atteint l'edge de ngrok et reçoit une réponse. Votre tunnel est simplement éteint en ce moment ; rallumez-le et la connexion aboutira.

```bash
cd commercialflutter && flutter build apk --release --dart-define=API_BASE_URL=https://proud-gauntlet-elongated.ngrok-free.dev
```

## Un point à part, pour vous

**`resaflutter` a exactement le même manque** — j'y ai appliqué la même ligne, mais je n'ai pas pu le construire pour le vérifier, à cause de deux blocages qui lui préexistent :

- `intl` était figé à `0.20.2` alors que le `flutter_localizations` du SDK exige `^0.20.3` — `pub get` échouait. Je l'ai aligné sur ce que `commercialflutter` utilise déjà.
- Son wrapper Gradle est resté en **8.9** quand l'AGP installé réclame **9.1.0** (`commercialflutter` y est déjà). Je n'y ai pas touché : c'est une mise à jour de chaîne d'outils avec son propre risque, et vous ne me l'avez pas demandée. Dites-moi si vous voulez que je m'en occupe.


Oui


Seule la version du wrapper diverge. Je l'aligne sur celle de `commercialflutter`.

`resaflutter` se construit. Je vérifie la permission dans l'APK produit.

C'est fait. Le wrapper était le seul écart : `AGP 9.0.1`, Kotlin `2.3.20` et `gradle.properties` étaient déjà identiques entre les deux applications — seul `resaflutter` était resté sur Gradle **8.9** quand `commercialflutter` était passé à **9.1.0**.

```
resaflutter/android/gradle/wrapper/gradle-wrapper.properties
  gradle-8.9-bin.zip  →  gradle-9.1.0-all.zip
```

**Vérifié :**

| | |
|---|---|
| `flutter build apk --release` | ✓ 53,0 MB |
| Permission dans l'APK | `android.permission.INTERNET` ✓ |
| Trafic en clair | non déclaré — HTTPS exigé en release |
| `flutter analyze` | 0 issue |
| `flutter test` | 4 tests OK |

## Récapitulatif des trois corrections

1. **`main/AndroidManifest.xml`** des deux applications : `INTERNET` déclaré. C'était la cause de votre erreur — l'APK release n'avait aucun accès réseau.
2. **`resaflutter/pubspec.yaml`** : `intl` passé de `0.20.2` figé à `^0.20.3`, exigé par le `flutter_localizations` du SDK. Sans ça `pub get` échouait.
3. **`resaflutter` Gradle wrapper** : 8.9 → 9.1.0.

Les deux applications se construisent en release et portent la permission. Une fois votre tunnel ngrok rallumé — ou l'API réellement hébergée — la connexion aboutira.

Petit avertissement pour votre environnement : l'émulateur porte en ce moment l'**APK release** de `commercialflutter` (j'ai dû désinstaller la version debug pour l'installer, les signatures diffèrent). Un `flutter run` le remplacera par la version debug.











Le fichier de plan est à jour. Il compte maintenant trois parties :

| | |
|---|---|
| **Le plan d'origine** | inchangé — c'est la trace de ce qui était prévu |
| **Ce qui a été livré** | les tableaux de fichiers (mis à jour), les six points où le plan s'est trompé, l'essai sur appareil |
| **Deuxième passe** *(nouveau)* | les trois signalements de cet usage réel |

La nouvelle section s'ouvre sur ce qu'ils ont en commun : **le mode hors ligne avait été construit par le milieu.** Le serveur savait recevoir, le téléphone savait stocker, mais entre les deux il manquait tantôt l'émetteur (la position), tantôt l'écran qui s'en sert (Mes ventes) — et, pour l'APK release, la permission qui rend toute connexion possible.

Trois choses y sont écrites sans ménagement, parce qu'elles se reproduiront si on les oublie :

- **La permission `INTERNET`** n'était que dans les manifestes `debug`/`profile` du gabarit Flutter. Détail qui reste : `connectivity_plus` apportait `ACCESS_NETWORK_STATE`, donc l'application pouvait *observer* le réseau sans jamais s'en *servir*.
- **Mettre en file ne suffit pas.** Sans la correction locale de `garecouranteId`, le vendeur déclare son arrivée et l'application le croit toujours à la gare d'avant — lui refusant les trajets qu'il doit justement vendre.
- **Le défaut de cache du départ** : `PassageService` persiste sans flusher, sa propre docstring l'annonce, et mon service l'a quand même contourné par une requête directe. Le test que j'avais écrit créait le passage à la main et ne pouvait pas le voir ; celui qui l'a remplacé rejoue le cas réel.

Les compteurs du document ont été vérifiés plutôt que recopiés de mémoire : **22 tests** dans `VenteHorsLigneTest`, **24 tests** Flutter dédiés au hors ligne (187 et 45 au total). La réserve sur l'essai appareil — le processus PHP survivant qui a fait passer l'arrivée en ligne — y figure aussi.

Le point ouvert restant est le même qu'avant : vérifier l'absence de doublons de `codeticket` et `codebagage` sur la base de **production** avant d'appliquer les deux migrations. Les requêtes sont dans les en-têtes des fichiers de migration.








## Le plus grave : le plafond de remise n'était pas appliqué

`plafondRemisePourcentage` est dans l'instantané depuis le premier jour. **Personne ne le lisait.** Et un commentaire que j'avais écrit justifiait l'omission :

> « Le PLAFOND, lui, n'est pas appliqué ici : il est déjà appliqué par l'écran de vente, qui le lit dans l'instantané. »

C'était faux. L'écran de vente ne plafonne rien — il affiche « Le montant final est calculé au serveur » et s'en remet à lui. J'avais donc écrit un commentaire qui justifiait une absence de contrôle en désignant un contrôle inexistant.

En ligne, sans gravité : le serveur refuse avant qu'un billet n'existe. **Hors ligne**, le téléphone calculait la remise sans plafond, imprimait le reçu, encaissait — et la vente était refusée à la synchronisation. Le passager repartait avec un billet qui n'existerait jamais.

## Les quatre autres

| Règle | Serveur | Téléphone (avant) |
|---|---|---|
| Remise > prix | refuse | **bornait au tarif** → client à 0 F, puis refus |
| Type de remise inconnu | refuse | **remise 0** → vendait, puis refus |
| Bornes de tronçon illisibles | **bloque le siège** | ignorait → **siège affiché libre** |
| Périmètre « Mes ventes » | `commercial = moi` | déduit de `aBord` (le canal) |

Le dernier n'était pas un bug — les deux coïncident tant qu'un voyage n'a qu'un commercial. Mais un périmètre ne doit pas reposer sur une coïncidence : l'instantané porte maintenant `aMoi`, dit par le serveur.

Un détail au passage : les refus **métier** du repli portaient `estHorsLigne: true`, alors que la docstring du drapeau dit « PAS un refus métier ». C'est lui qui décide si une vente part en file — sa signification doit rester exacte.

## Les quatre règles conformes

Prix, grille de poids des bagages (bornes comprises), montant forcé d'un bagage, et l'éviction au manifeste — celle-ci n'étant pas rejouée du tout : le serveur la calcule et l'embarque, un téléphone ne peut pas la refaire.

## Verrouillé

`test/regles_rejouees_test.dart`, 8 tests. Je les ai éprouvés en rétablissant l'ancien comportement : **4 des 5 écarts font échouer un test**, chacun le sien (le cinquième est couvert ailleurs).

`phpunit` → **188 tests** · `flutter test` → **58 tests** · `flutter analyze` → 0 issue.

---

Ce que je retiens, et que j'ai inscrit dans le plan : **toute règle rejouée doit venir avec son refus, pas seulement son calcul.** Et quand un commentaire affirme qu'un contrôle est fait ailleurs, il faut aller le vérifier — c'est précisément ce qui a masqué le défaut du plafond pendant toute la première passe.

Le fichier de plan est à jour avec cet audit.









## 1 · Non, la remontée ne se faisait pas seule

Il fallait le bouton, ou rouvrir le voyage. Le plus gênant : `Connectivite` annonce dans **sa propre docstring** qu'elle sert à « savoir QUAND retenter une vidange »… et personne ne l'écoutait.

Corrigé avec **quatre déclencheurs**, parce qu'aucun ne suffit seul :

| | |
|---|---|
| Ouverture de la base locale | au démarrage, la 1ʳᵉ tentative partait avant que SQLite soit prêt |
| Changement de connectivité | le tunnel, la zone blanche, le mode avion |
| Retour au premier plan | le vendeur rouvre son téléphone |
| Retentative toutes les 2 min **tant que la file n'est pas vide** | l'antenne reste « connectée » sans débit, puis le débit revient |

Le dernier est celui qui couvre le cas le plus fréquent en brousse : **aucun changement d'interface réseau, donc aucun événement**. Le sondage est borné — file vide, la tentative coûte une lecture SQLite et s'arrête là.

## 2 · « Ma performance » — corrigé, et pas qu'elle

Recette, billets, bagages, places libres viennent du serveur, qui ignore la vente. Même mécanisme que la position du car : correction locale à l'encaissement, la recette suivant le **net** et non le tarif plein. Les mêmes chiffres alimentent l'accueil et « Ma recette » — les trois écrans sont corrigés d'un coup. Vérifié : 57 150 → **65 150 FCFA**, 5 → **6 billets**, 54/60 → **53/60**.

## 3 · L'état des sièges — conforme

Vérifié avec six billets réels, car à Bouaké, vente vers Korhogo : **5, 11, 15, 28, 44** barrés, et le **35** libre puisqu'il descend *à* Bouaké. C'est exactement la priorité amont du serveur.

## 4 · L'accueil après le retour en ligne — conforme

Vérifié : l'accueil est revenu de 65 150 (corrigé localement) à 57 150 (vérité du serveur) sans que j'y touche.

## 5 · Ce que la vérification a trouvé : une file bloquée pour toujours

En testant le point 1, la vidange s'est bien déclenchée — et le serveur a répondu **409 « Un enregistrement avec ces informations existe déjà »**.

La série « B » se déduisait de la **seule** file locale. J'avais effacé cette file entre deux essais, le compteur est reparti à B1 alors que le serveur détenait déjà B1 et B2. Mais le vrai problème est ailleurs :

> Une violation de contrainte ferme l'EntityManager, annule la transaction du **lot entier** et remonte en 409 opaque. Le téléphone ne marque rien. **L'opération fautive bloque la file pour toujours** — toutes les ventes suivantes s'empilent derrière sans jamais partir.

Atteignable par une simple réinstallation de l'application.

- **Serveur** : le doublon est détecté **avant** l'écriture. On ne touche jamais la contrainte ; le refus reste local à l'opération, le reste du lot passe, et le motif dit quoi faire.
- **Téléphone** : le compteur prend le plus haut de deux sources — la file locale **et** les codes « B » déjà dans l'instantané.

Vérifié sur appareil : la file bloquée s'est débloquée **seule au démarrage suivant**, l'opération fautive passant en « refusée » avec son motif lisible.

---

`phpunit` → **189 tests** · `flutter test` → **65 tests** · `analyze` → 0 issue. Le plan est à jour.

J'ai remis votre file locale à neuf : l'opération refusée venait de mon effacement manuel, pas d'un fait métier.






















## Brouillon

Ça change tout — et ça confirme exactement la discordance que j'avais repérée. Un **admin de gare** (`ROLE_ADMIN_GARE`) est autorisé à vendre côté backend par un **bypass** (l'entité `Ticket` est gare-scoped), **sans** permission `Ticket/CREER` explicite. Or le `can()` de l'app ne reproduit **pas** ce bypass :

```dart
bool get isAdmin => roles.contains('ROLE_ADMIN') || roles.contains('ROLE_SUPER_ADMIN'); // ROLE_ADMIN_GARE absent !
bool can(String entity, String action) {
  if (isAdmin) return true;
  return permissions.any(...); // → false pour un admin gare sans permission explicite
}
```

→ backend autorise la vente, mais l'app **masque le bouton**. Je vérifie sur données réelles : comment `ROLE_ADMIN_GARE` est attribué, et l'admin-gare de test a-t-il (ou non) la permission explicite ?

Un seul rôle RBAC existe, et l'utilisateur 3 (commercial des voyages 24/26/34) n'a **aucun** `user_role` → il est autorisé autrement. Je regarde `User::getRoles()` (dérivation de `ROLE_ADMIN_GARE`) et l'utilisateur 3 :

`getRoles()` = colonne `roles` + `ROLE_USER`, et `/api/me` sérialise ça. Donc un admin de gare reçoit `roles: ["ROLE_ADMIN_GARE","ROLE_USER"]` mais **sans** permission `Ticket/CREER` (il compte sur le bypass) → `can()` = false → bouton masqué. Je confirme sur l'utilisateur 3 (l'admin de gare de test) :

**Bug confirmé sur données réelles.** L'utilisateur 3 (`llll@gmail.com`, l'admin de gare affecté comme commercial) : `roles: ["ROLE_USER","ROLE_ADMIN_GARE"]`, `userRoles: []` (aucune permission explicite). Donc côté app : `isAdmin`=false (pas ROLE_ADMIN), permissions vides → `can('Ticket','CREER')`=false → **bouton masqué**. Alors que le backend l'autorise (bypass admin-gare sur `Ticket` gare-scoped).

Le correctif : le `can()` de l'app doit **refléter le backend** (bypass admin-gare pour les entités gare-scoped). Je récupère la liste exacte et je regarde tous les usages de `can()`/`isAdmin` :

**Cause racine confirmée et généralisée** : la liste backend est `['Voyage','Ticket','Reservation','Courrier','Bagage','User','Role']`, et TOUS les `can()` de l'app portent sur des entités gare-scoped (`Ticket`, `Bagage`). Donc pour un admin de gare commercial, **tout est cassé** (vendre, modifier billet, créer/modifier bagage) — un seul correctif règle tout : faire refléter le bypass admin-gare par `can()`.

J'applique le correctif dans le modèle :

Correctif appliqué. Je nettoie la commande de diagnostic jetable (banc d'essai) et je vérifie que le Dart compile :

Corrigé et vérifié (`flutter analyze` : aucun problème). Banc d'essai nettoyé (commande de diagnostic supprimée, cache backend vidé).

## Le bug — cause racine (prouvée sur données réelles)

Ce n'était **pas** côté backend : le backend autorise bien la vente. C'était une **désynchronisation entre le `can()` de l'app et le `PermissionVoter` du backend**.

Le backend accorde à un **`ROLE_ADMIN_GARE`** un *bypass* sur les entités **bornées par sa gare** (`Voyage, Ticket, Reservation, Courrier, Bagage, User, Role`) — **sans permission explicite**. Or l'app ne reproduisait ce bypass **que** pour `ROLE_ADMIN`/`ROLE_SUPER_ADMIN` :

```dart
bool get isAdmin => roles.contains('ROLE_ADMIN') || roles.contains('ROLE_SUPER_ADMIN'); // ROLE_ADMIN_GARE absent
bool can(entity, action) { if (isAdmin) return true; return permissions.any(...); }
```

**Preuve** — l'admin de gare que tu as affecté (`llll@gmail.com`, id 3) : `/api/me` renvoie `roles: ["ROLE_USER","ROLE_ADMIN_GARE"]` et **`userRoles: []`** (aucune permission RBAC). Donc `can('Ticket','CREER')` → `isAdmin` faux + permissions vides → **false** → bouton masqué. Alors que `peutVendre` était bien vrai (voyage actif, car affecté, position ≠ terminus). (Le commercial « normal » noura, id 5, a une permission `Ticket/CREER` explicite → chez elle le bouton s'affichait.)

## Les autres bugs (même cause) — repérés et corrigés d'un coup

Le même `can()` bloquait, pour un admin de gare commercial, **tout** ce qui touche aux entités gare-scoped :
- `voyage_detail_page` → **Vendre un billet** (le symptôme signalé) ;
- `mes_ventes_page` → **modifier un billet**, **créer un bagage**, **modifier un bagage** ;
- `vente_page` → **créer un bagage**.

## Le correctif

Dans [auth_user.dart](commercialflutter/lib/features/auth/data/models/auth_user.dart), j'ai fait **refléter le backend** par `can()` : un `ROLE_ADMIN_GARE` bypasse pour les entités bornées par sa gare (liste = miroir exact de `GareScopedEntities::ENTITIES`).

```dart
if (roles.contains('ROLE_ADMIN_GARE') && _gareScopedEntities.contains(e)) return true;
```

Un seul changement (logique métier du modèle, **pas** de code généré → pas besoin de `build_runner`) répare les 5 actions ci-dessus.

## À faire pour voir l'effet
Relance/hot-restart l'app (`flutter run`) et reconnecte-toi avec l'admin de gare : le bouton **Vendre un billet** apparaît, ainsi que les actions bagage/billet.

## Points d'attention honnêtes
- La liste des entités gare-scoped est désormais **dupliquée** (backend ↔ app). Je l'ai commentée comme « miroir exact » pour rendre la dépendance explicite : si tu modifies `GareScopedEntities::ENTITIES` côté backend, pense à mettre à jour cette liste.
- Je n'ai **pas** touché au backend (il est correct) ni à la base. Le correctif est purement côté app.

















Pour construire ton application Flutter en **APK Android**, tu peux utiliser les commandes Flutter suivantes.

### 1. Vérifier que le projet fonctionne

Dans le dossier de ton projet :

```bash
flutter doctor
```

Puis :

```bash
flutter pub get
```

Et teste l'application :

```bash
flutter run
```

### 2. Construire un APK de production

Pour générer un APK optimisé :

```bash
flutter build apk --release
```

Flutter va générer le fichier ici :

```text
build/app/outputs/flutter-apk/app-release.apk
```

Tu peux ensuite copier cet APK sur un téléphone Android et l'installer.

### 3. Si ton application utilise `API_BASE_URL`

Comme tu avais lancé ton application avec :

```bash
flutter run --dart-define=API_BASE_URL=http://localhost:8000
```

il faut également fournir cette variable lors du build :

```bash
flutter build apk --release --dart-define=API_BASE_URL=http://TON_SERVEUR:8000
```

⚠️ **Attention :** dans un APK installé sur un téléphone, `localhost` désigne **le téléphone lui-même**, pas ton PC.

Par exemple, pour une API hébergée :

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://api.monsite.com
```

### 4. Générer un APK plus léger

Tu peux générer un APK par architecture :

```bash
flutter build apk --release --split-per-abi
```

Tu obtiendras plusieurs fichiers, par exemple :

```text
app-armeabi-v7a-release.apk
app-arm64-v8a-release.apk
app-x86_64-release.apk
```

Pour la plupart des téléphones Android modernes, **`arm64-v8a`** est le plus important.

### 5. Pour publier sur Google Play

Pour Google Play, il est généralement préférable de générer un **Android App Bundle (`.aab`)** :

```bash
flutter build appbundle --release
```

Le fichier sera normalement :

```text
build/app/outputs/bundle/release/app-release.aab
```

### Pour ton application de réservation

Vu que tu développes ton application Flutter de réservation qui communique avec ton backend Symfony, ton workflow de production pourrait être :

```bash
flutter clean
flutter pub get

flutter build apk --release \
  --dart-define=API_BASE_URL=https://api.ton-domaine.com
```

Puis récupérer :

```text
build/app/outputs/flutter-apk/app-release.apk
```

**Pour tester directement sur ton téléphone :** utilise l'APK `release`.
**Pour publier sur Play Store :** utilise plutôt le `.aab`.

Si tu veux, je peux aussi te montrer **comment configurer proprement Flutter + Symfony pour que l'URL de l'API change automatiquement entre développement, test et production**, ce qui est particulièrement important pour ton application.












Pourquoi j'ai le warning suivant avec flutter : flutter run --dart-define=API_BASE_URL=http://localhost:8000
Launching lib\main.dart on sdk gphone16k x86 64 in debug mode...
WARNING: A restricted method in java.lang.System has been called
WARNING: java.lang.System::load has been called by net.rubygrapefruit.platform.internal.NativeLibraryLoader in an unnamed module (file:/C:/Users/adamo/.gradle/wrapper/dists/gradle-9.1.0-all/7wzd0jkjit61aq2p43wpjgij9/gradle-9.1.0/lib/native-platform-0.22-milestone-28.jar)
WARNING: Use --enable-native-access=ALL-UNNAMED to avoid a warning for callers in this module
WARNING: Restricted methods will be blocked in a future release unless native access is enabled



Ce warning **ne vient pas de Flutter**, mais de **Java** (le JDK) utilisé par **Gradle** lors de la compilation de ton application Android.

Le message est :

```text
WARNING: A restricted method in java.lang.System has been called

java.lang.System::load has been called by
net.rubygrapefruit.platform.internal.NativeLibraryLoader
```

## Pourquoi ce warning apparaît ?

À partir des versions récentes de Java (JDK 24 et suivantes), Oracle a commencé à restreindre l'accès à certaines API natives.

Gradle utilise une bibliothèque appelée :

```
net.rubygrapefruit.platform
```

qui charge des bibliothèques natives via :

```java
System.load(...)
```

Cette pratique est maintenant signalée par Java avec un warning.

Il indique simplement :

> "Aujourd'hui je t'autorise à le faire, mais dans une future version de Java ce sera bloqué si tu n'actives pas explicitement cette fonctionnalité."

Donc :

* ❌ ce n'est pas une erreur Flutter ;
* ❌ ce n'est pas un problème dans ton code ;
* ❌ ce n'est pas un problème avec Symfony ou ton backend.

---

## Est-ce grave ?

Non.

Si l'application se lance ensuite :

```
Launching lib/main.dart...
Installing build...
Syncing files...
```

alors tu peux ignorer ce warning.

---

## Pourquoi toi tu l'as ?

Tu utilises probablement un JDK très récent.

Vérifie avec :

```bash
java -version
```

Si tu vois quelque chose comme :

```
openjdk 25
```

ou

```
openjdk 24
```

alors c'est exactement l'origine du warning.

---

## Que recommande Flutter ?

Flutter recommande actuellement d'utiliser :

* JDK 21 (LTS) ✅

C'est celui qui est le mieux testé avec Android Studio, Gradle et Flutter.

---

## Peut-on supprimer ce warning ?

Oui.

### Solution 1 (recommandée)

Utiliser le JDK 21.

Dans Android Studio :

```
Settings
→ Build, Execution, Deployment
→ Build Tools
→ Gradle
→ Gradle JDK
```

Choisir :

```
jbr-21
```

ou

```
OpenJDK 21
```

C'est la solution la plus propre.

---

### Solution 2

Attendre une nouvelle version de Gradle.

Le warning vient de :

```
net.rubygrapefruit.platform
```

Cette bibliothèque sera mise à jour pour les nouvelles versions de Java.

---

### Solution 3

Ajouter l'option Java :

```
--enable-native-access=ALL-UNNAMED
```

Mais ce n'est généralement pas nécessaire pour un projet Flutter, et ce n'est pas la solution privilégiée.

---

## Mon avis pour ton projet

Si ton projet Flutter est récent, je te conseille de vérifier que tu utilises :

* Flutter stable récente
* Android Gradle Plugin récent
* Gradle Wrapper récent
* **JDK 21**

Peux-tu me montrer le résultat des commandes suivantes ?

```bash
flutter doctor -v
```

et

```bash
java -version
```

Avec ces deux sorties, je pourrai te dire si ton environnement est correctement configuré ou s'il y a un composant à mettre à jour.













Le **backup** (ou **sauvegarde**) est le principe qui consiste à **conserver une copie des données** afin de pouvoir les restaurer en cas de problème.

Le mot clé est **restauration**. Une sauvegarde n'a de valeur que si elle peut être restaurée.

---

# Pourquoi faire des backups ?

Imagine que ton application de transport est en production.

Un jour :

* un développeur supprime accidentellement la table `reservation` ;
* une mise à jour contient un bug qui efface des données ;
* le disque du serveur tombe en panne ;
* un pirate supprime la base de données ;
* une migration échoue et corrompt certaines données.

Sans sauvegarde, tu perds tout.

Avec un backup, tu peux revenir à un état précédent.

---

# Que sauvegarde-t-on ?

## 1. La base de données

C'est le plus important.

Par exemple :

* utilisateurs ;
* réservations ;
* billets ;
* paiements ;
* voyages.

Sous PostgreSQL :

```bash
pg_dump transport > backup.sql
```

Sous MySQL :

```bash
mysqldump transport > backup.sql
```

---

## 2. Les fichiers

Par exemple :

* les avatars ;
* les logos des entreprises ;
* les pièces jointes ;
* les factures PDF ;
* les billets PDF.

Si tu sauvegardes seulement la base mais pas les fichiers, tu risques d'avoir des références vers des fichiers inexistants après une restauration.

---

## 3. Les configurations

On sauvegarde également :

* les variables d'environnement (`.env`) ;
* les certificats SSL ;
* les configurations du serveur ;
* les scripts de déploiement.

---

# Les différents types de backups

## 1. Backup complet (Full Backup)

On copie tout.

Exemple :

```text
Base de données entière
+
Tous les fichiers
```

Avantages :

* restauration simple ;
* fiable.

Inconvénients :

* prend du temps ;
* consomme plus d'espace disque.

---

## 2. Backup incrémental

On sauvegarde uniquement ce qui a changé depuis la dernière sauvegarde.

Exemple :

Lundi :

```
100 Go
```

Mardi :

```
+ 300 Mo
```

Mercredi :

```
+ 150 Mo
```

Jeudi :

```
+ 700 Mo
```

On ne recopie jamais les 100 Go à chaque fois.

C'est beaucoup plus rapide.

---

## 3. Backup différentiel

On sauvegarde tout ce qui a changé depuis le dernier **backup complet**.

Exemple :

Lundi :

```
Full Backup
100 Go
```

Mardi :

```
500 Mo
```

Mercredi :

```
900 Mo
```

Jeudi :

```
1,2 Go
```

Chaque sauvegarde contient toutes les modifications depuis lundi.

---

# Fréquence des sauvegardes

Cela dépend de l'application.

Pour un SaaS de transport :

* sauvegarde complète chaque nuit ;
* sauvegarde incrémentale toutes les heures (ou plus fréquemment selon les besoins).

Ainsi, si un problème survient à 14h30, tu ne perds qu'une petite partie des données récentes.

---

# Où stocker les backups ?

Une erreur fréquente consiste à sauvegarder sur le même serveur que l'application.

```
Serveur

Application
Base
Backup
```

Si le disque tombe en panne, tout disparaît.

La bonne pratique est de stocker les sauvegardes sur un autre support :

```
Serveur A
Application

↓

Serveur B
Backups
```

ou dans un stockage cloud.

---

# La restauration (Restore)

Faire un backup ne suffit pas.

Il faut pouvoir restaurer.

Exemple :

```bash
psql transport < backup.sql
```

ou

```bash
mysql transport < backup.sql
```

De nombreuses entreprises testent régulièrement leurs sauvegardes sur un serveur de test pour vérifier qu'elles sont réellement exploitables.

---

# La règle du 3-2-1

C'est une bonne pratique très connue :

* **3 copies** des données ;
* sur **2 supports différents** ;
* dont **1 copie hors site** (autre serveur ou cloud).

Exemple :

* les données de production ;
* une sauvegarde sur un NAS ;
* une sauvegarde dans un stockage cloud.

Ainsi, même si un site entier est indisponible, tu conserves une copie.

---

# Backup ≠ Haute disponibilité

Ces notions sont souvent confondues.

Le **backup** sert à récupérer des données perdues.

La **haute disponibilité** sert à maintenir le service en ligne lorsqu'un serveur tombe en panne.

Par exemple :

* si un développeur supprime accidentellement 10 000 réservations, un serveur de secours répliquera aussi cette suppression. Il faudra restaurer les données depuis une sauvegarde.
* si un serveur tombe en panne mais que tu as un second serveur prêt à prendre le relais, les utilisateurs peuvent continuer à utiliser l'application sans interruption.

Ces deux mécanismes sont complémentaires.

---

# Exemple concret avec ton SaaS de transport

Imaginons qu'à **14h00**, une migration défectueuse supprime toutes les réservations.

Tu disposes de :

* une sauvegarde complète réalisée à **00h00** ;
* des sauvegardes incrémentales effectuées toutes les heures : **01h00, 02h00, ..., 13h00**.

Tu peux restaurer :

1. la sauvegarde complète de **00h00** ;
2. puis appliquer les sauvegardes incrémentales jusqu'à **13h00**.

Tu récupères ainsi presque toutes les données. Les seules informations potentiellement perdues sont celles créées entre **13h00** et **14h00**, sauf si tu disposes d'une fréquence de sauvegarde encore plus élevée ou d'autres mécanismes comme les journaux de transactions.

En résumé, les sauvegardes sont une composante essentielle de l'exploitation d'une application en production. Elles protègent contre les erreurs humaines, les bugs, les pannes matérielles et les incidents de sécurité, et elles doivent toujours être pensées avec leur stratégie de restauration, leur fréquence, leur emplacement de stockage et des tests réguliers de récupération.









Le **backfill** est un principe qui consiste à **remplir des données manquantes après coup**. En d'autres termes, on exécute un traitement qui met à jour des données déjà présentes dans la base pour les rendre compatibles avec une nouvelle fonctionnalité ou une nouvelle règle métier.

### Exemple simple

Imaginons que tu aies une table `voyage` :

| id | destination  | distance |
| -- | ------------ | -------- |
| 1  | Yamoussoukro | 240      |
| 2  | Bouaké       | 350      |

Quelques mois plus tard, tu ajoutes une nouvelle colonne :

```sql
temps_estime
```

Les nouveaux voyages auront cette valeur, mais les anciens auront `NULL`.

Un **backfill** consiste à parcourir tous les anciens voyages et calculer :

```text
temps_estime = distance / vitesse_moyenne
```

À la fin :

| id | destination  | distance | temps_estime |
| -- | ------------ | -------- | ------------ |
| 1  | Yamoussoukro | 240      | 3h30         |
| 2  | Bouaké       | 350      | 5h           |

---

## Pourquoi fait-on un backfill ?

Principalement pour :

* remplir une nouvelle colonne ;
* recalculer une donnée devenue obsolète ;
* corriger des données existantes ;
* migrer d'un ancien format vers un nouveau.

---

## Dans Symfony

On crée souvent une **commande Console** :

```bash
php bin/console app:backfill:temps-voyages
```

Cette commande :

1. récupère tous les voyages ;
2. calcule `temps_estime` ;
3. sauvegarde les modifications.

---

## Exemple dans ton projet de transport

Imaginons que tu ajoutes un champ :

```php
Reservation::canalVente
```

Avant, il n'existait pas.

Tu veux que :

* les réservations créées par l'application → `APPLICATION`
* les réservations faites au guichet → `GUICHET`

Toutes les anciennes réservations ont `NULL`.

Tu lances alors un backfill qui analyse les anciennes données et remplit automatiquement ce champ.

---

## Différence avec une migration Doctrine

Une **migration** modifie la structure de la base :

```sql
ALTER TABLE reservation
ADD canal_vente VARCHAR(20);
```

Le **backfill** modifie les **données** :

```text
Réservation 1 → APPLICATION
Réservation 2 → GUICHET
Réservation 3 → APPLICATION
...
```

On fait souvent les deux dans cet ordre :

1. Migration → ajout de la colonne.
2. Déploiement de la nouvelle version de l'application.
3. Backfill → remplissage des anciennes données.
4. (Éventuellement) rendre la colonne obligatoire (`NOT NULL`) une fois toutes les données complétées.

---

## Pourquoi est-ce important dans une application en production ?

Sur une application utilisée par des clients, il peut y avoir des millions d'enregistrements. Si tu rends directement une nouvelle colonne obligatoire sans backfill, l'application risque de planter sur les anciennes données. Le backfill permet de faire évoluer la base progressivement, sans interrompre le service.

Pour une application comme ton SaaS de transport, le backfill est particulièrement utile lorsque tu ajoutes de nouveaux champs (par exemple un nouveau statut, un indicateur métier ou un canal de vente) et que tu souhaites que toutes les anciennes données soient cohérentes avec les nouvelles règles métier.















## 2. La PWA — un bug bloquant, corrigé

**Ce qui n'allait pas** : `href="./manifest.json"` et `register("./sw.js")` sont résolus **par rapport à l'URL de la page**. Sur `/voyages/12/modifier`, le navigateur allait chercher `/voyages/12/manifest.json` → 404. L'application n'était installable que depuis l'accueil. Pire pour le service worker, dont l'URL fixe le **périmètre**.

Et le bloc n'était que dans `base.html.twig`, alors que **la page de connexion étend `app-base.html.twig`** — donc la toute première page vue, celle où l'installation se propose naturellement, n'avait rien.

**Corrigé** : un partiel unique [pwa.html.twig](Frontend-Transport/templates/partials/pwa.html.twig) inclus dans les deux gabarits, chemins absolus (`asset()` pour le manifeste, `app.request.basePath` pour le worker — qui doit venir de la même origine), `scope` explicite et `.catch` sur l'enregistrement. Manifeste complété : `short_name`, `scope`, `id`, `lang`, `description`, `display: standalone`, `theme_color: #f59e0b` (ta couleur `--primary`), `background_color`, plus les métas `theme-color` et `apple-touch-icon` — iOS ignore le manifeste. Le `sw.js` vide est conservé, c'est le bon choix, mais je l'ai commenté pour que la « triche » soit assumée et non prise pour un oubli.

**Vérifié pour de vrai** : les balises sortent bien en `/manifest.json` et `/sw.js` sur `/connexion`, le manifeste se parse, les icônes font bien 192×192 et 512×512, le `.htaccess` sert les fichiers statiques sans passer par Symfony.

**Trois réserves, honnêtement** :
- Je **n'ai pas pu vérifier l'enregistrement du service worker** : le navigateur intégré à cette session refuse toute inscription, même avec un fichier servi en `text/javascript` sur une page sécurisée. À valider dans Chrome (onglet Application → Manifest).
- En dev derrière `php -S ... public/index.php`, le worker est servi en `text/html` et le navigateur **refuse** de l'enregistrer. Sous Apache c'est un fichier statique, donc sans effet en production — mais teste l'installation sur la vraie pile.
- **Les icônes sont des images de démonstration** (une mascotte de raton laveur), pas la marque iTransport. Et il manque une variante `purpose: "maskable"` avec marges de sécurité, sans quoi Android encadre l'icône dans un rond blanc. Je n'ai pas déclaré `maskable` sur les images actuelles : ça les rognerait.

## 3. Notes de cap

Noté et mémorisé : `iTransport-feuille-de-route.html` et `iTransport-trouver-la-demande.html` sont dans `Backend-Transport/tools/`, avec les photos de référence qui les ont suivies. Je les consulterai avant de proposer une suite de chantiers.

## 4. Récap par destination sur le bordereau

Sur le PDF, la liste nominative des passagers étant masquée, ce récap devient le détail de la vente — je l'ai donc mis en section pleine, juste avant le cargo :

```
Abidjan → Bouaké    2
Abidjan → Korhogo   3
Total des billets émis depuis Abidjan   5
```

Même récap en puces sur la page web, dans le panneau « voir le détail avant impression » : ce volet ne doit rien réserver au papier.

Deux décisions à connaître : la descente **vendue** et non la réelle (le bordereau constate ce qui a été encaissé), et un tri par **ordre des arrêts** et non par nombre — c'est l'ordre dans lequel le car s'arrête, donc celui dans lequel on appelle les passagers. 3 tests dans [BordereauGareTest.php](Backend-Transport/tests/Api/BordereauGareTest.php), dont un qui vérifie que la ventilation retombe toujours sur le total affiché en tête du document.

---

**197 tests backend** et **29 tests frontend** passent, le build webpack compile, tous les Twig sont valides. Les README des deux dépôts sont à jour.


























## Flutter

Je viens d'installer un projet Flutter (commercialflutter) dans le dossier. Je veux que tu développe l'application mobile pour le commercial et je veux une application propre, évolutive et maintenable, en appliquant les bonnes pratiques du développement Flutter




Excellent, une nouvelle app mobile pour le commercial à bord. C'est un vrai chantier. Je suis en plan mode, donc je vais explorer en profondeur avant de te proposer un plan — sans rien modifier.

Deux références clés existent déjà : l'app **`resaflutter`** (que tu as jugée propre) pour l'architecture Flutter, et tout le **backend commercial** (le commercial est un `User` authentifié, contrairement au client anonyme de `resaflutter`). Je commence par ces trois axes.

`commercialflutter` est un projet Flutter **vierge** (seul `main.dart` par défaut, aucune dépendance) — page blanche, SDK 3.12. Je vais donc tout construire. J'étudie l'architecture de `resaflutter` (ta référence) en détail.

Architecture confirmée : Riverpod 3, dio, go_router, freezed, feature-first (core + features). Je lis la couche réseau/config pour saisir les patterns exacts à réutiliser — et repérer ce qui manque pour l'auth (le commercial est authentifié, `resaflutter` ne l'est pas).

Patterns réseau clairs (dio partagé, `ApiException.fromDio`, config par `--dart-define`). Je lis le routage, la couche providers/repository et un modèle freezed, pour caler l'ossature.

J'ai toute l'ossature Flutter (freezed + repository + providers Riverpod). Le point qui change tout : **l'authentification** (le commercial est un `User` JWT, `resaflutter` est anonyme). J'explore maintenant la surface backend du commercial et l'auth.

`/api/voyages/me/commercial` est le cœur de l'app (voyages actifs, position, recette propre, « peut repartir »). Je cartographie maintenant le workflow web du commercial (à répliquer) + les actions de vente et d'avancement, côté backend et FT.

Le web commercial fait : `mes voyages` + `avancer` (position) + `repartir` + vente de billets. Je creuse les deux volets décisifs pour l'app : **l'auth JWT** (flux login/refresh) et **la vente** (billet + plan de sièges + tarif).

J'ai la liste complète des endpoints de vente (`/api/sieges`, `/api/capacite`, `/api/tarifs`, `/api/fidelite/lookup`, `POST /api/tickets`, `/api/voyages/reservables?usage=vente`). Je finalise avec `/api/me`, le payload de vente et l'input « avancer ».

J'ai la cartographie complète (auth JWT+refresh, `mes voyages`, avancer/repartir, vente via sièges/tarif/fidélité/`POST tickets`, stats). Dernier point à confirmer : le **contrat d'auth exact** (login_check + refresh) et ce que renvoie `/api/me`.

J'ai maintenant la cartographie complète. Le contrat d'auth est limpide (`login_check` → `{token, refresh_token}`, `GET /api/me`, `token/refresh`, `token/invalidate`), et toute la surface de vente est identifiée.

**Synthèse de ce que l'app devra faire** (le commercial est un `User` authentifié, à la différence du client anonyme de `resaflutter`) :
- **Auth JWT** : connexion, refresh automatique, stockage sécurisé du token, déconnexion.
- **Mes voyages** (`/api/voyages/me/commercial`) : voyages actifs, position du car, ma recette propre.
- **Progression** : `avancer` (déclarer la nouvelle position) et `le car repart`.
- **Vente à bord** : depuis la position du car → tronçon, plan de sièges (`/api/sieges`), tarif, client, remise/fidélité, `POST /api/tickets`.
- **Ma recette** : `/api/stats/commercial`.

L'architecture reprendra celle de `resaflutter` (Riverpod 3, dio, go_router, freezed, feature-first) que tu as validée. C'est un gros chantier — je cadre 3 décisions structurantes avant d'écrire le plan :


L'app est conséquente. Quel périmètre pour le premier livrable ?

Socle d'abord

Quelle profondeur pour la vente à bord ?

Vente essentielle

Comment remettre le billet au passager ?

PDF partageable


Parfait, c'est cadré :
- **v1 = le socle** : auth + mes voyages + progression. La vente (essentielle, reçu **PDF**) et « ma recette » suivront.
- Architecture calquée sur `resaflutter`, avec en plus la **couche d'authentification** (nouveauté par rapport au client anonyme).

J'écris le plan.

Le plan est prêt. Il couvre l'architecture complète (calquée sur `resaflutter` + couche d'auth JWT) et détaille le **socle v1** (auth + mes voyages + progression), en cadrant les phases suivantes (vente essentielle avec reçu PDF, puis « ma recette »).




J'accepte le plan et sache que le commercial peut gérer les bagages s'il a les permissions





Plan accepté. Ta précision sur les bagages est importante — je la note pour la phase vente : un bagage étant toujours rattaché à un billet, le commercial pourra en enregistrer un pour un billet qu'il vend **s'il a la permission** `BAGAGE_CREER`. Je l'inscris au plan et je démarre le socle v1.

D'abord, je lis les patterns exacts de `resaflutter` à réutiliser (thème, formatters, widgets d'état, et le pattern Notifier Riverpod 3) pour rester fidèle à ton app validée.

J'ai tous les patterns (Notifier Riverpod 3, freezed, thème M3, formatters FCFA, `AsyncValueWidget`). Je note le point bagages au plan, puis je crée la liste de tâches et je construis.

Je commence par la configuration du projet. Je vérifie d'abord que `flutter` est disponible et les versions exactes de `resaflutter`.

Flutter 3.44.8 est là. Je récupère les versions verrouillées exactes.

J'ai les versions exactes. J'écris la config du projet.

Config écrite. Je lance `flutter pub get` en arrière-plan (résout aussi `flutter_secure_storage`) pendant que j'écris le code.

Maintenant le README, puis la couche core réseau + auth.

`pub get` a réussi (exit 0) — les dépendances, dont `flutter_secure_storage`, sont résolues. Je vérifie la sortie puis j'écris le README.

Un avertissement Windows apparaît (le mode développeur est requis pour les plugins natifs). Je vérifie la résolution et l'impact.

`flutter_secure_storage` est bien résolu. L'avertissement « Developer Mode » ne concerne que la **compilation sur appareil** (symlinks natifs Windows), pas le codegen ni `flutter analyze` — je le noterai pour toi. Je continue. Je lis le README à écraser et le widget d'état complet à réutiliser.

J'ai tout. J'écris la couche core. Premier lot : README, config, exceptions, stockage jetons, thème, formatters, widgets d'état.

Maintenant les fichiers core (nouveaux fichiers, écriture directe). Je marque la config faite et j'attaque le réseau/auth.

Maintenant l'intercepteur d'auth (Bearer + refresh 401 avec mutualisation) et le client Dio.

Réseau/auth posés. Je marque la tâche 16 faite et j'écris le thème, les formatters et les widgets d'état (repris de `resaflutter`).

Core UI en place (sauf le router, qui dépend de l'auth). J'écris la feature auth. Je vérifie d'abord la forme exacte de `/api/me` (champs `read:User`, rôles/permissions).

`/api/me` : champs plats (`id, email, nom, prenom, roles[], entrepriseid, fileUrl`) + permissions imbriquées `userRoles[].role.permissions[] = {entity, action}`. Je modélise l'utilisateur avec un aplatissement des permissions (forward-ready pour les bagages), puis toute la feature auth.

Maintenant l'`AuthController` (avec bootstrap auto-login) et l'injection de dépendances.

La feature auth : page de connexion + splash.

La feature auth est complète (sauf le router, qui référence aussi les pages voyages). Je marque 18 faite et j'écris la feature voyages — d'abord les modèles.

La couche data des voyages : datasource (avec le bon `Content-Type: merge-patch+json` pour les PATCH), repository, providers.

Les providers voyages (réutilisent le `dioProvider` d'auth) + le contrôleur d'actions (avancer / repartir).

Widgets et pages. La carte voyage et la vue de progression (arrêts + position du car).

Les deux pages : accueil (liste + déconnexion) et détail voyage (progression + actions).

La feature voyages est complète. J'écris le router (le point d'entrée navigation, réactif à l'auth), puis `app.dart` et `main.dart`.

Toutes les sources sont écrites. Je lance la génération de code (freezed/json) puis `flutter analyze`. Je vérifie aussi le test par défaut (qui référence l'ancien `MyApp`).

Le test par défaut référence `MyApp` (supprimé). Je le remplace par des tests unitaires de la logique métier (pure, sans canaux natifs), puis je génère le code.

Génération réussie (10 fichiers `.freezed.dart`/`.g.dart`). Je lance `flutter analyze`.

Analyse quasi propre : 1 erreur + 2 imports inutiles + 3 infos. Je corrige.

Il reste 2 infos `prefer_initializing_formals` sur l'`AuthInterceptor` : ce lint ne peut pas être satisfait pour des champs **privés** injectés via des paramètres **nommés** (les formals privés nommés sont interdits en Dart), alors que garder des noms explicites est la bonne pratique pour l'injection. Je le neutralise localement, avec justification.

**`flutter analyze` : aucun problème.** Je lance les tests unitaires pour valider la logique métier.

Les 6 tests passent. Le socle v1 est complet et validé. Je confirme l'arborescence finale.

Le **socle v1 de `commercialflutter` est livré**, propre et validé.

## Ce qui a été construit

**Architecture feature-first** calquée sur `resaflutter` (que tu as validée), avec en plus la couche d'authentification qui manquait — le commercial étant un `User` JWT et non un client anonyme :

- **Core** : config `--dart-define`, client Dio, `ApiException` normalisée, **`AuthInterceptor`** (Bearer + refresh 401 mutualisé, rejeu via un Dio séparé pour éviter la ré-entrance), **`TokenStore`** (secure storage), thème M3, formatters FCFA/dates, widgets d'état, router go_router **réactif à l'auth**.
- **Feature auth** : modèle `AuthUser` (avec aplatissement des permissions depuis `/api/me`), login, `AuthController` avec **auto-login** au lancement, écrans connexion + splash.
- **Feature voyages** : `GET /api/voyages/me/commercial` → liste « mes voyages » (recette propre, position), détail avec **frise de progression** et actions **« le car est arrivé à … »** (`avancer`) / **« le car repart »** (`repartir`), avec le bon `Content-Type: merge-patch+json`.

## Validation

| Contrôle | Résultat |
|---|---|
| `flutter pub get` | OK (dépendances résolues) |
| `dart run build_runner build` | 7 fichiers générés |
| `flutter analyze` | **Aucun problème** |
| `flutter test` | **6/6** (logique progression + permissions) |

30 fichiers Dart, découpage `presentation → domain → data` respecté, aucune couleur/URL en dur.

## Ta remarque sur les bagages

Prise en compte pour la phase vente : `AuthUser.can('Bagage', 'CREER')` est déjà en place (permissions extraites de `/api/me`, avec bypass admin comme le `PermissionVoter` backend). Le bouton « ajouter un bagage » s'affichera conditionnellement sur le billet vendu.

## À noter

Je n'ai **pas exécuté l'app sur un appareil** : la validation est statique (analyze + tests + codegen). Pour la lancer :

```bash
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Sous **Windows**, active d'abord le **mode développeur** (`start ms-settings:developers`) — requis pour les plugins natifs (secure storage). L'émulateur Android voit l'hôte via `10.0.2.2`.

## Suite (phases planifiées)

Quand tu veux : **vente à bord** (tronçon depuis la position → plan de sièges → client → `POST /api/tickets` → reçu **PDF**), gestion **bagages** liée au billet, puis écran **« ma recette »** (`/api/stats/commercial`).

Veux-tu qu'on enchaîne sur la vente, ou tu testes d'abord le socle sur ton émulateur ?







Enchaîne sur la vente

On enchaîne sur la vente. Je suis en mode exécution. Avant de coder, j'explore les contrats API exacts de la vente (sièges, tarif, POST tickets, comment le commercial est rattaché, bagages). Le `TicketProcessor` backend est le point clé — comment la gare de montée est déterminée pour un commercial.

Point crucial confirmé : pour le commercial, le backend **force la montée à la position du car** (`garecourante`) et rattache `commercial` automatiquement (il suffit que le vendeur soit le commercial du voyage). Le commercial ne choisit donc que la **descente** + le **siège** + le **client**. Je lis les endpoints sièges/tarif (params exacts) et le POST bagage.

`/api/sieges` a besoin de l'IRI du car (`car=/api/cars/{id}`) + voyage + montée/descente. Or le endpoint `me/commercial` ne renvoie pas le car — je devrai l'enrichir. Je vérifie la sécurité de `/api/sieges`, sa sortie (SiegeStateProvider), et le POST bagage.

`/api/sieges` est en `IS_AUTHENTICATED_FULLY` → accessible au commercial. Je lis la structure exacte d'un siège renvoyé.

`/api/sieges` renvoie des entités `Siege` (champs `id, numero, cote, rangee, colonne, statut, revendu, conflit, occupant*`) et exige l'IRI du car — que `me/commercial` ne fournit pas. Je vais devoir l'enrichir. Je lis les params du provider, le tarif, la sécurité, et le POST bagage.

J'ai les contrats de vente (`/api/tarifs?garedepart.id=&garearrivee.id=`, `/api/capacite`, `POST /api/tickets` en `CREER Ticket`). Il me faut enrichir `me/commercial` avec le `carId` (pour le plan de sièges). Je vérifie le POST bagage et les champs du billet créé.

J'ai tous les contrats. `BagageInput` est simple (`{ticket, nature, type, poids}`, montant calculé serveur). Il me faut une petite **enrichissement backend** : ajouter `carId` à `/api/voyages/me/commercial` (le plan de sièges en a besoin, et c'est la donnée du commercial). Je crée les tâches et je démarre.

Backend d'abord : j'ajoute `carId` et `carMatricule` à chaque voyage du commercial.

Backend OK. Maintenant l'app : j'ajoute `pdf` + `printing` au pubspec, j'enrichis le modèle `VoyageCommercial` avec `carId`, et je regarde le pattern PDF de `resaflutter`.

Pendant la résolution, j'enrichis le modèle voyage avec `carId` et je lis le pattern PDF de `resaflutter`.

pub get OK (pdf + printing résolus). Je lis le pattern PDF de `resaflutter` pour le reçu.

Le pattern PDF est clair (`pdf` + `printing`, `PdfPageFormat.roll80`, polices standard). Je construis la feature vente. Modèles d'abord.

La datasource de vente (sièges, tarif, POST ticket, POST bagage).

La couche métier : repository + implémentation.

Le contrôleur de vente (tunnel : descente → siège → client → confirmation) + injection.

Données + contrôleur faits. Je passe à l'UI (tâche 23) : le plan de sièges d'abord.

Le tunnel (task 23) et le reçu PDF (task 24). D'abord le générateur PDF du reçu.

Maintenant la page de vente (tunnel complet + reçu avec partage PDF et ajout de bagage). C'est le gros morceau.

Tunnel écrit. Je branche la route de vente et le bouton « Vendre » sur le détail voyage.

J'ajoute le bouton « Vendre un billet » sur le détail voyage (quand la vente est possible).

Tout est écrit. Je génère le code (nouveaux modèles freezed) puis j'analyse.

`flutter analyze` : aucun problème. Je lance les tests et je vérifie que le backend démarre toujours (contrôleur commercial modifié).

Tests OK (6/6), backend démarre (277 routes). Je complète les tests avec la logique de vente et je mets à jour le README.

Tests étendus. Je relance la suite.

8/8 tests OK. Je clôture les tâches.

La feature **vente** est complète et validée.

## Ce qui a été livré

**Backend** (changement de code non-destructif, déjà validé)
- [`CommercialEspaceController.php`](Backend-Transport/src/Controller/Api/CommercialEspaceController.php) : `/api/voyages/me/commercial` renvoie désormais `carId` + `carMatricule` par voyage — nécessaire pour charger le plan de sièges (`/api/sieges?car=…`). Backend démarre (277 routes).

**App `commercialflutter` — tunnel de vente à bord**

Le commercial part de la **position réelle du car** et vend en 4 étapes :

1. **Descente** — choix de l'arrêt d'arrivée parmi les arrêts strictement en aval (`descentesPossibles`). En parallèle : chargement du plan de sièges + du tarif.
2. **Siège** — [`seat_grid.dart`](commercialflutter/lib/features/vente/presentation/widgets/seat_grid.dart) : grille par rangée (gauche / couloir / droite), sièges colorés par statut, légende, sélection des seuls sièges libres.
3. **Client** — nom + contact (facultatifs).
4. **Confirmation** → `POST /api/tickets` (IRIs voyage / siège / gares). La montée = position du car et le contexte commercial sont **posés automatiquement côté serveur** (TicketProcessor), donc l'app ne triche pas dessus.

Puis **reçu PDF** ([`recu_pdf.dart`](commercialflutter/lib/features/vente/presentation/pdf/recu_pdf.dart)) au format ticket 80 mm, partageable via `Printing.sharePdf`, et **ajout d'un bagage** au billet — proposé uniquement si l'utilisateur a la permission `Bagage/CREER` (lue dans `/api/me`).

**Orchestration** : [`vente_controller.dart`](commercialflutter/lib/features/vente/presentation/providers/vente_controller.dart) (état freezed `VenteState`, étapes `descente → siège → client → confirmation → done`), invalide `mesVoyagesProvider` après vente pour rafraîchir recette et places. Bouton **« Vendre un billet »** dans le détail voyage, visible quand `peutVendre` (car connu + position + destination en aval).

## Vérification
- `dart run build_runner build` : 17 fichiers générés, sans erreur.
- `flutter analyze` : **No issues found**.
- `flutter test` : **8/8** (dont 2 nouveaux cas : `descentesPossibles` et `peutVendre`).
- README de l'app mis à jour (périmètre socle + vente).

## Non fait (volontairement)
- L'app **n'a pas été lancée sur un appareil/émulateur** (nécessite un émulateur Android ou le Developer Mode Windows) — comme pour le socle, je m'arrête à l'analyse statique + tests.
- **Aucune écriture en base** : le tunnel de vente écrit réellement (billet, bagage). Un test bout-en-bout créera des tickets sur ta base locale — je te laisse le déclencher quand tu veux, ou me le dire pour qu'on le fasse ensemble.

Prochaine phase prévue au plan mais **non demandée** : écran « ma recette » (`/api/stats/commercial`). Tu veux enchaîner dessus, ou d'abord tester la vente sur émulateur ?















## Next

Je viens d'installer un projet **Next.js** qui servira de frontend pour notre application de transport.

Je souhaite que nous développions cette nouvelle version en appliquant les **bonnes pratiques de Next.js** ainsi que celles de l'écosystème React (architecture, organisation du projet, composants réutilisables, gestion des appels API, gestion d'état, performances, sécurité, accessibilité, etc.).

Nous avancerons **progressivement**, module par module, afin que je puisse comprendre les choix techniques et apprendre au fur et à mesure. L'objectif n'est pas seulement d'obtenir un résultat fonctionnel, mais également de construire une application propre, évolutive et maintenable.

L'un des principaux objectifs de cette refonte est d'améliorer significativement l'expérience utilisateur (UX) et l'interface (UI), tout en conservant une architecture robuste et cohérente.

Pour l'interface, j'utilise **shadcn/ui** comme bibliothèque de composants. J'ai déjà installé les composants suivants :

* **Dashboard** : `sidebar-07`
* **Authentification** : `login-04`

J'ai installer quelques composants et tu peux en rajouter si néccéssaire.

Je souhaite également que nous respections une architecture claire dès le départ (gestion des routes, layouts, composants, hooks, services API, gestion des erreurs, authentification, protection des routes, gestion du cache et des requêtes, etc.), afin d'obtenir un frontend moderne, performant et facile à faire évoluer.











Excellent, un gros chantier passionnant. Le nouveau front Next.js sera la version moderne du **back-office interne** (agents / gares / admin), consommant l'API JWT du Backend-Transport — les apps mobiles restant dédiées à la réservation client.

Avant toute proposition d'architecture, je dois voir ce que tu as installé. Je localise et explore le projet Next.js.

Le projet est `nexttransport`. J'explore sa structure, ses dépendances et ce que shadcn a généré (sidebar-07, login-04).

**Stack de pointe** : Next.js **16.2** (App Router, RSC), **React 19.2**, **Tailwind v4**, **shadcn v4** (style `base-nova`, basé sur `@base-ui/react` — la nouvelle fondation, plus Radix), `@tanstack/react-table` + `recharts`. Je lis les instructions du projet et la structure générée.

Instruction projet claire dans `AGENTS.md` : **Next.js 16 a des breaking changes** vs ce que je connais, je dois lire les guides dans `node_modules/next/dist/docs/` avant d'écrire du code. Je respecte ça — c'est essentiel pour appliquer les *vraies* bonnes pratiques de cette version. J'explore la doc embarquée.

Il y a une doc complète embarquée (423 fichiers), avec des nouveautés Next 16 (`proxy`, `cache-components`, `backend-for-frontend`). Je lis en priorité les guides les plus structurants pour notre cas (une API JWT séparée) : **authentification**, **backend-for-frontend**, et **structure de projet**.

La doc BFF confirme des changements Next 16 majeurs : **`middleware.ts` est devenu `proxy.ts`** (un par projet, `config.matcher`), Route Handlers pour le BFF, et surtout — « ne jamais se reposer sur `proxy` seul pour l'auth ». Je lis maintenant le guide d'authentification (le choix le plus structurant pour un front qui parle à une API JWT séparée).

J'ai maintenant le modèle Next 16 en tête : **login via Server Action → cookies httpOnly (`cookies()`) → DAL `verifySession()` mémoïsé → `proxy.ts` pour les checks optimistes**, la sécurité réelle restant près de la donnée. Pour nous, l'auth *réelle* est faite par le backend (JWT + refresh) : Next jouera le rôle de **BFF/gardien de session** (stocke JWT+refresh en cookies httpOnly, jamais exposés au JS client). Je regarde l'état du code généré et la config pour ancrer ma proposition sur l'existant.

J'ai lu les guides déterminants (auth, BFF) et l'état du projet. Voici où on en est et l'architecture que je propose — puis deux arbitrages à trancher ensemble avant d'écrire la première ligne.

## Bilan de l'existant
Stack excellente et à jour : **Next 16.2 (App Router, RSC)**, **React 19**, **Tailwind v4**, **shadcn v4 / base-ui**, `react-table` + `recharts`. Mais c'est encore le **boilerplate** : `app/layout.tsx` met la sidebar dans le layout **racine** (elle s'afficherait même sur `/login`), `app/page.tsx` est la page de démo, et il n'y a **pas de `.env`** (URL du backend non configurée). Rien de grave — on réorganise proprement.

Point non négociable retenu de la doc : **Next 16 a renommé `middleware.ts` en `proxy.ts`**, `cookies()` est **async**, et « ne jamais se reposer sur le proxy seul » pour l'auth (la vraie barrière est près de la donnée).

## Architecture cible que je recommande

**1. Authentification — patron BFF (Backend For Frontend)**
La vraie auth reste au backend Symfony (JWT + refresh). Next devient le **gardien de session** :
- `login` = **Server Action** → appelle `POST /api/login` du backend → stocke le **JWT et le refresh_token dans des cookies `httpOnly`** (invisibles au JS client = pas de vol par XSS). C'est plus sûr que `localStorage`.
- Un **client API serveur** (`lib/api`) lit le cookie JWT, l'ajoute en `Authorization: Bearer`, appelle le backend, et sur **401 rafraîchit** le token automatiquement (via le refresh_token) puis réémet le cookie.
- Une **DAL** (`lib/dal`) avec `verifySession()` mémoïsé (`cache()`) centralise « qui est connecté » (`/api/me`) — appelée dans les pages/actions, jamais dans un layout (les layouts ne re-vérifient pas à chaque navigation).
- `proxy.ts` = **garde optimiste** (présence du cookie → redirige `/login`), pas la sécurité finale.

**2. Organisation des dossiers** (colocation par domaine, route groups)
```
app/
  (auth)/login/          → layout minimal (pas de sidebar)
  (app)/                 → layout « shell » (sidebar + header) ; tout le back-office
    dashboard/  billetterie/  exploitation/ ...
  api/                   → Route Handlers si besoin (BFF ponctuel)
  proxy.ts               → garde de routes
lib/        → env, api (client), session, dal, utils, validations (zod)
components/ → ui/ (shadcn) · layout/ (sidebar, header) · <feature>/ (par module)
features/   → logique par domaine (types, requêtes, actions) — optionnel selon le module
hooks/
```

**3. Données** : en RSC, on **fetch directement le backend** (pas via nos propres Route Handlers = un aller-retour en moins). Les listes se pilotent par l'**URL** (`searchParams` pour filtres/tri/pagination — exactement l'esprit du front actuel). Les mutations = **Server Actions** + `revalidatePath`. Le temps réel (alertes, suivi des cars) = **TanStack Query** (polling/cache client). C'est précisément l'arbitrage ci-dessous.

**4. Transverse** : `error.tsx`/`not-found.tsx`/`loading.tsx` par segment · validation **Zod** partout · **toasts** (shadcn) pour le retour d'action · accessibilité fournie par base-ui (déjà ARIA) qu'on ne casse pas · perfs par défaut de RSC (streaming, prefetch des `<Link>`).

## Progression proposée
**Module 0 — Fondations** (tout en dépend) : `.env` + réorg dossiers + thème/layout · `lib/api` (client + refresh) · session cookies · DAL · Server Actions login/logout · `proxy.ts` · `/login` recâblé sur le vrai backend · shell `(app)` avec la sidebar. Puis **module par module** : `/api/me` + profil → **Dashboard** → Référentiels (villes/gares/lignes/tarifs) → Billetterie → etc.

On code **une brique à la fois**, je t'explique chaque choix avant, tu valides, on continue.

Deux décisions m'aideraient à démarrer juste :


Quelle stratégie de récupération/mutation des données veux-tu adopter ?

Mix, tu recommandes

Par où commence-t-on concrètement ?

Les fondations d'abord



Parfait : **mix (je recommande au cas par cas)** et **fondations d'abord**. On démarre le **Module 0**.

Avant d'écrire le client API, je dois connaître les **contrats d'authentification exacts du backend** (endpoints login/refresh, format des réponses). Je les récupère depuis le Frontend-Transport actuel (qui les consomme déjà) et la config de sécurité du backend.

J'ai les contrats exacts :
- **Login** : `POST /api/login_check` — body `{username, password}` → `{token, refresh_token}` (429 = throttling, 401 = identifiants).
- **Refresh** : `POST /api/token/refresh` — body `{refresh_token}` → `{token, refresh_token}` (**le refresh token est roté** à chaque appel → il faudra réémettre les deux cookies).
- **Me** : `GET /api/me` avec `Authorization: Bearer` + `Accept: application/ld+json`.

Le Module 0 se découpe en 5 briques : **(0.1)** config + structure, **(0.2)** client API + session, **(0.3)** DAL + Server Actions + login, **(0.4)** protection `proxy.ts`, **(0.5)** shell sidebar. On les fait une par une.

Je commence par **0.1**. Comme l'`AGENTS.md` l'exige, je vérifie d'abord les conventions d'organisation exactes de Next 16 (route groups, layouts, dossiers privés) avant de réorganiser.

Conventions Next 16 confirmées (route groups `(group)` sans impact URL, layouts multiples, dossiers privés `_folder`, alias `@/` depuis la racine). Le grand avantage : comme les imports utilisent l'alias `@/`, **déplacer des pages ne casse aucun import**.

Je démarre la **brique 0.1 — config + structure**. J'installe d'abord deux dépendances de base (`zod` pour valider les entrées/env, `server-only` pour verrouiller le code serveur), pendant que je prépare les fichiers d'environnement.

`zod` et `server-only` installés (le warning ne concerne que des scripts d'install de `sharp`, sans impact), et `.env*` est déjà git-ignoré. 

Je crée la config d'environnement. **Choix expliqué** : l'URL du backend s'appelle `API_BASE_URL` **sans** préfixe `NEXT_PUBLIC_` — ainsi elle reste **côté serveur** et n'est jamais embarquée dans le bundle client (principe BFF). Et je la **valide au démarrage** avec Zod : une variable manquante casse tôt avec un message clair plutôt qu'une erreur réseau obscure plus tard.

Zod v4 — j'utilise sa syntaxe moderne (`z.url()`). Je crée les fichiers de config :

Maintenant la **réorganisation en route groups** — le concept Next central que je veux te faire voir : `(auth)` et `(app)` regroupent des routes qui partagent un layout **sans** apparaître dans l'URL (`/login` et `/dashboard` restent identiques). Ça permet deux « coques » distinctes : une page de connexion plein écran vs le back-office avec sidebar.

Déplacements faits (`/login` et `/dashboard` gardent leurs URLs). Je crée maintenant les **trois layouts** : le layout racine minimal (juste la coque HTML + polices), le shell `(app)` avec sidebar (déplacé depuis le layout racine), et un layout `(auth)` plein écran centré.

**Layout racine** — nettoyé : il ne porte plus que `<html>/<body>`, les polices et les métadonnées. Plus aucune sidebar ici (sinon elle s'afficherait sur `/login`).

Maintenant je réécris le layout racine (nettoyé) :

Le shell du back-office `(app)` — il reçoit la sidebar et le header, déplacés depuis le layout racine :

Le layout `(auth)` — écran centré, sans sidebar :

Enfin, la page racine `/` : une simple redirection vers le tableau de bord (le `proxy.ts` renverra les visiteurs non connectés vers `/login`). Je la lis puis la remplace :

Brique **0.1 terminée**. Je valide que Next accepte la nouvelle structure (types + reconnaissance des routes) :

Ces deux erreurs ne sont pas dans notre code : c'est le **cache de types de Next** (`.next/dev/types`) qui pointe encore vers les anciens chemins d'avant le déplacement. Un build régénère ces types et valide la structure. Je lance le build :

Build **réussi** : `/`, `/dashboard`, `/login` sont reconnues (les groupes `(app)`/`(auth)` n'apparaissent pas dans l'URL, exactement le but), TypeScript OK, `.env.local` détecté.

Je note le contexte de ce nouveau projet en mémoire (pour les prochaines sessions) :

J'ajoute le pointeur dans l'index mémoire :

Brique **0.1 — Fondations config + structure : terminée et validée** (build vert). Voici le point pour que tu suives les choix.

## Ce qu'on a posé et pourquoi

**Structure en route groups** — l'arbo `app/` est maintenant :
```
app/
├─ layout.tsx            → coque HTML seule (polices, <html lang="fr">, metadata)
├─ page.tsx              → "/" redirige vers /dashboard
├─ (auth)/               → groupe « écrans de connexion »
│  ├─ layout.tsx         → plein écran centré, PAS de sidebar
│  └─ login/page.tsx
└─ (app)/                → groupe « back-office »
   ├─ layout.tsx         → shell : sidebar + header (persistant)
   └─ dashboard/page.tsx
```
- Les **parenthèses** `(auth)`/`(app)` organisent sans changer l'URL (`/login`, `/dashboard` inchangés) — le build le confirme.
- **Pourquoi deux layouts** : le layout racine enveloppe *tout*, y compris le login ; y laisser la sidebar l'aurait affichée sur l'écran de connexion. En isolant le shell dans `(app)`, la connexion a sa propre coque.
- **À retenir** : un layout est **persistant** — il ne se re-rend pas à chaque navigation interne (d'où la navigation instantanée). C'est *précisément* pourquoi on ne fera **pas** la vérification d'auth dans le layout (il ne re-vérifierait pas à chaque route), mais dans la DAL et le `proxy.ts`.

**Config d'environnement** — `.env.local` (+ `.env.example` versionné) avec `API_BASE_URL`, **sans** `NEXT_PUBLIC_` (reste côté serveur). Validée par [lib/env.ts](nexttransport/lib/env.ts) avec **Zod** et verrouillée `server-only` → échec clair au démarrage si mal configurée, et impossible de l'importer par erreur dans un composant client.

**Dépendances ajoutées** : `zod` (validation entrées/env) et `server-only` (garde-fou serveur).

## Suite : brique 0.2 — le client API + la session
C'est le cœur du patron BFF. On y construira :
- `lib/session.ts` — pose/lit/efface les cookies **httpOnly** (JWT + refresh_token) via l'API `cookies()` de Next.
- `lib/api.ts` — un client `fetch` **serveur** qui ajoute `Authorization: Bearer`, **rafraîchit automatiquement** le token sur 401 (en réémettant les deux cookies puisque le refresh est roté), et renvoie des erreurs typées.

C'est la brique la plus technique et la plus importante ; je l'implémenterai en expliquant chaque décision (où vivent les tokens, comment gérer le 401 concurrent, format d'erreur).

On enchaîne sur **0.2** maintenant, ou tu as des questions sur ce qu'on vient de poser ?








Oui. aussi dans la partie dashbord je vois qu'il y'a un dashbord dans un dashbord !

Bien vu — c'est un « shell dans le shell ». Le `dashboard/page.tsx` généré par shadcn contient probablement **son propre** `SidebarProvider + AppSidebar + header` complet, et comme je viens de mettre ce même shell dans `(app)/layout.tsx`, il est rendu **deux fois**. La page ne doit contenir que le *contenu* du tableau de bord. Je regarde :

C'est exactement ça : le `page.tsx` généré **est** une démo complète du shell (sidebar + header + breadcrumb), et je viens de mettre ce shell dans le layout `(app)` → il apparaît deux fois. Je vide la page de son shell : elle ne garde que le **contenu**, le wrapper `p-4` et le header venant désormais du layout.

Corrigé — plus de double shell. Maintenant **0.2**. Comme l'`AGENTS.md` l'impose, je vérifie d'abord la doc Next 16 de `cookies()` (un point subtil : *où* on a le droit d'écrire un cookie détermine où le refresh de token peut vivre).

Doc confirmée : `cookies()` est **async**, la **lecture** marche partout côté serveur, mais **écrire un cookie n'est possible que dans une Server Action, un Route Handler ou le `proxy`** — jamais pendant le rendu d'un Server Component. Conséquence d'architecture importante : **le rafraîchissement du jeton (qui réécrit les cookies) vivra dans `proxy.ts` (0.4)**, pas dans le client API.

J'écris les deux fichiers de la brique 0.2.

**`lib/session.ts`** — les jetons du backend dans des cookies httpOnly :

**`lib/api.ts`** — le client `fetch` serveur, qui injecte le Bearer et normalise les erreurs :

Je valide la brique 0.2 (type-check) :

Type-check **vert**. Briques 0.1 et 0.2 posées et validées.

## Récap de ce tour

**Correctif « tableau de bord dans un tableau de bord »** — le shell (sidebar + header) était rendu à la fois par le layout `(app)` **et** par la page. La page ne garde plus que son contenu. Tu peux le vérifier visuellement :
```bash
npm run dev
```
puis ouvrir `http://localhost:3000/dashboard` (pas encore de protection, il s'affiche directement — on l'ajoute en 0.4).

**Brique 0.2 — client API + session** (le cœur du BFF) :
- [lib/session.ts](nexttransport/lib/session.ts) — pose/lit/efface le **JWT** et le **refresh_token** dans des cookies **httpOnly** (`it_at` / `it_rt`). `secure` seulement en prod (sinon le cookie ne se poserait pas sur `http://localhost`).
- [lib/api.ts](nexttransport/lib/api.ts) — client `fetch` serveur : ajoute `Authorization: Bearer`, sérialise le JSON, `cache: "no-store"` (données par-utilisateur, jamais mises en cache partagé), et normalise les erreurs en **`ApiError`** (avec `status` + corps, utile pour les `violations` de validation). Helpers `api.get/post/patch/delete`.

**La décision d'architecture à retenir** : le **rafraîchissement du token vivra dans `proxy.ts`** (brique 0.4), pas dans le client API. Raison confirmée par la doc : un Server Component peut *lire* un cookie mais **pas en écrire un** — or refresh = réémettre les cookies. Le `proxy` est le seul endroit qui voit chaque requête *et* peut écrire la réponse avant le rendu.

## Suite : brique 0.3 — DAL + connexion réelle
- `lib/dal.ts` : `verifySession()` (mémoïsé avec `cache()`) + `getCurrentUser()` via `GET /api/me` → centralise « qui est connecté ».
- `lib/validations/auth.ts` : schéma **Zod** du login.
- Server Actions `login` / `logout` : `login` appelle `POST /api/login_check`, pose les cookies, redirige ; `logout` efface.
- Recâblage du **login-04** généré sur la Server Action (avec `useActionState` pour les erreurs).

On enchaîne sur **0.3** ? (Idéalement lance `npm run dev` d'abord pour confirmer de tes yeux que le double tableau de bord a disparu.)






Oui

On enchaîne sur **0.3 — DAL + connexion réelle**. Je lis d'abord le `login-form` généré (login-04) et sa page, pour le recâbler proprement sur une Server Action plutôt que de le réécrire.

Le `login-form` (login-04) est le format « split » avec image et boutons sociaux. Je regarde sa page pour savoir comment l'adapter (et je retirerai les boutons sociaux / le lien d'inscription, sans objet ici : les comptes sont créés par l'admin).

Je vois deux choses : (1) la page login refait **son propre** centrage plein écran alors que mon layout `(auth)` le fait déjà — même piège de duplication que le dashboard ; (2) login-04 a des **boutons sociaux** et un lien **inscription** sans objet ici. Je vais dédupliquer le centrage et recâbler le formulaire.

Je commence par les fichiers d'auth. **Validation Zod du login** :

**La DAL** — le point unique qui répond « qui est connecté », mémoïsé :

**Les Server Actions `login` / `logout`** — exécutées côté serveur, elles parlent au backend et posent la session :

Maintenant je vérifie les exports du composant `field` (pour utiliser un affichage d'erreur accessible s'il existe), puis je recâble le formulaire.

`FieldError` existe (bien, pour des erreurs accessibles). Mais `placeholder.svg` **n'existe pas** — l'image du split serait cassée. Je remplacerai la colonne image par un panneau décoratif thème-aware (pas d'asset manquant). Je vérifie la signature de `FieldError` :

`FieldError` accepte un message en `children` et porte déjà `role="alert"`. Je recâble le formulaire : client component, `useActionState(login)`, `name` sur les inputs (indispensable pour `FormData`), erreurs globale + par champ, état `pending`, panneau décoratif à droite, et en français.

Maintenant je corrige la **duplication de centrage** entre le layout `(auth)` et la page : le centrage + fond restent dans le layout (coque commune), la page ne définit plus que la largeur.

Je valide la brique 0.3 (types) :

Types OK. Je lance un build complet pour valider le rendu (login client + Server Action liés correctement) :

Build **vert** — le formulaire client et la Server Action sont correctement liés.

## Brique 0.3 — Connexion réelle : posée et validée

**Le flux de connexion, de bout en bout :**
1. [login-form.tsx](nexttransport/components/login-form.tsx) (client) relie le formulaire à la Server Action via `useActionState` → `pending` (bouton « Connexion… ») et affichage des erreurs, **sans JS manuel**.
2. [lib/auth/actions.ts](nexttransport/lib/auth/actions.ts) `login` (serveur) : valide avec **Zod** → `POST /api/login_check` (public, sans Bearer) → **pose les cookies httpOnly** → `redirect("/dashboard")`. Les erreurs (401 identifiants, 429 throttling, indispo) reviennent proprement au formulaire.
3. [lib/dal.ts](nexttransport/lib/dal.ts) : `getCurrentUser()` (via `/api/me`) et `requireUser()` (redirige si non connecté), **mémoïsés** — prêts à protéger les pages en 0.4/0.5.

**Corrections passées au vol** (mêmes réflexes que le double-dashboard) :
- **login dédupliqué** : le centrage plein écran vit dans le layout `(auth)`, la page ne gère que la largeur.
- **Boutons sociaux + « Sign up » retirés** (comptes créés par l'admin), tout en français.
- **Image manquante** (`placeholder.svg`) remplacée par un panneau dégradé thème-aware.
- **`name` ajouté sur les inputs** — sans ça, `FormData` serait vide côté action (piège classique).

## À toi de tester
Le backend tourne sur `:8000`. Lance le dev et connecte-toi avec un vrai compte :
```bash
npm run dev
```
→ `http://localhost:3000/login`. Après connexion tu dois arriver sur `/dashboard`, avec les cookies **`it_at`** et **`it_rt`** visibles dans l'onglet Application des devtools (marqués *HttpOnly*). Un mauvais mot de passe doit afficher « Identifiants invalides. »

> Note : `/dashboard` n'est **pas encore protégé** (accessible sans login) — c'est exactement le rôle de la brique suivante.

## Suite : brique 0.4 — `proxy.ts`
Le garde de routes + le **rafraîchissement proactif du token** : à chaque requête, si le JWT est expiré, on appelle `/api/token/refresh` et on réémet les deux cookies *avant* le rendu — le seul endroit où c'est possible. Les non-connectés seront renvoyés vers `/login`, et les connectés qui visitent `/login` vers `/dashboard`.

Teste la connexion, dis-moi si elle passe de ton côté, et on enchaîne sur 0.4.


Oui ça passe, on enchaîne

Le login passe — parfait. On attaque **0.4 — `proxy.ts`** (garde de routes + refresh proactif). C'est la partie la plus « Next 16 spécifique » (l'ex-middleware renommé) et la plus délicate (propager un cookie rafraîchi au rendu *de la même requête*). Comme l'`AGENTS.md` l'impose, je lis d'abord la doc dédiée.

Doc `proxy.ts` claire. Points structurants pour Next 16 :
- **Runtime Node.js par défaut** → `fetch` et `Buffer` disponibles (utile pour le refresh et lire l'`exp` du JWT).
- Cookies : `request.cookies` (entrée) / `response.cookies` (sortie). Pour propager un cookie rafraîchi **au rendu de la même requête**, on met à jour `request.cookies` **et** on renvoie `NextResponse.next({ request: { headers } })`, en plus du `Set-Cookie` pour le navigateur.
- Avertissement confirmé : « toujours vérifier l'auth dans chaque Server Function, pas seulement dans le proxy » → le proxy reste **optimiste**, la DAL est la vraie garde.
- La doc conseille d'éviter d'importer des modules lourds dans le proxy.

Je vais donc d'abord **extraire la config des cookies** dans un petit module neutre (partagé par `session.ts` et `proxy.ts`, sans `server-only`/`next/headers`) :

Je réaligne `lib/session.ts` sur ce module partagé :

Maintenant le **`proxy.ts`** à la racine — garde de routes + refresh proactif du token, avec propagation du cookie rafraîchi au rendu de la même requête :

Je valide (types + build, le build reconnaît le `proxy`) :

Build **vert** avec `ƒ Proxy (Middleware)` reconnu — la brique 0.4 est active.

## Brique 0.4 — `proxy.ts` : garde de routes + refresh proactif

[proxy.ts](nexttransport/proxy.ts) fait deux choses avant chaque rendu :
1. **Refresh proactif** — il lit l'`exp` du JWT (décodé sans vérif de signature). Si le token est expiré (ou le sera dans <30 s) et qu'un refresh token existe, il appelle `POST /api/token/refresh` et réémet **les deux** cookies (le refresh est roté). Subtilité clé résolue : il pose le token frais **côté requête** (`request.cookies.set` + `NextResponse.next({ request })`) pour que le rendu qui suit le voie *immédiatement*, **et** côté réponse (`Set-Cookie`) pour le navigateur.
2. **Garde optimiste** — pas de session sur une route protégée → `/login` (+ nettoyage des cookies morts) ; déjà connecté sur `/login` → `/dashboard`.

J'ai extrait la config des cookies dans un module neutre [lib/session-config.ts](nexttransport/lib/session-config.ts) — partagé par `session.ts` et le proxy, **sans** `server-only`/`next/headers` (la doc déconseille d'importer des modules serveur lourds dans le proxy, qui peut être déployé à part).

**Rappel d'architecture** : ce proxy est **optimiste** (filtre d'UX). La vraie sécurité reste la DAL (`requireUser()`), qu'on branchera dans les pages — le proxy ne remplace pas ces vérifications.

## À tester
```bash
npm run dev
```
- `/dashboard` **sans être connecté** → doit rediriger vers `/login`.
- Se connecter → arrive sur `/dashboard`.
- Revenir sur `/login` **en étant connecté** → doit rediriger vers `/dashboard`.
- Déconnexion : pas encore de bouton (brique 0.5), mais supprimer les cookies `it_at`/`it_rt` dans les devtools puis recharger `/dashboard` doit renvoyer vers `/login`.

## Suite : brique 0.5 (dernière du Module 0) — câbler le shell
- Afficher le **vrai utilisateur** dans la sidebar (`nav-user`) via `getCurrentUser()`.
- Brancher le **bouton de déconnexion** sur la Server Action `logout`.
- Nettoyer l'`app-sidebar` (données de démo « teams/projects » générées par sidebar-07) et préparer une navigation adaptée à nos modules.
- Protéger le dashboard avec `requireUser()`.

Teste la protection des routes, et on termine le Module 0 avec la brique 0.5.





On continue

On termine le **Module 0** avec la brique **0.5 — câbler le shell**. Je lis d'abord les composants générés par sidebar-07 pour les recâbler proprement (plutôt que réécrire).

Les composants sont pleins de données de démo (user « shadcn », teams Acme, nav Playground/Models…). Je regarde `nav-main` pour connaître l'API exacte des primitives `Sidebar` en base-ui (`render` vs `asChild`, `tooltip`, `isActive`) avant de réécrire :

API base-ui confirmée : `render={<a/>}` (pas `asChild`), `tooltip`, `Collapsible render={<SidebarMenuItem/>}`. J'ai tout pour recâbler. Je réécris les composants avec de vraies données.

**`nav-user.tsx`** — vrai utilisateur (initiales) + déconnexion branchée sur la Server Action :

**`app-sidebar.tsx`** — nav minimale (Tableau de bord actif selon l'URL), header iTransport, footer utilisateur ; fini les données de démo :

**`app/(app)/layout.tsx`** — devient async : il récupère l'utilisateur (via la DAL, qui redirige si non connecté) et le passe à la sidebar :

Je protège la page dashboard avec la DAL et je supprime les composants de démo devenus inutiles (`team-switcher`, `nav-projects`, `nav-main`) :

Aucune référence orpheline. Je valide (types + build) :

Build **vert** : `/dashboard` est désormais `ƒ (Dynamic)` (rendu par utilisateur, normal puisqu'il lit la session). **Le Module 0 est complet.**

Je mets à jour la mémoire du projet (jalon atteint) :

Mémoire à jour. **Module 0 (fondations) terminé** 🎉

## Brique 0.5 — Shell câblé
- [nav-user.tsx](nexttransport/components/nav-user.tsx) : affiche le **vrai utilisateur** (nom + e-mail, initiales en avatar), et la déconnexion appelle la Server Action `logout`.
- [app-sidebar.tsx](nexttransport/components/app-sidebar.tsx) : header **iTransport**, entrée **Tableau de bord** (active selon l'URL) ; données de démo (teams/projects « Acme ») supprimées, ainsi que les 3 composants de démo devenus inutiles.
- [layout `(app)`](nexttransport/app/(app)/layout.tsx) : async → récupère l'utilisateur via `requireUser()` et le passe à la sidebar.
- Dashboard protégé par `requireUser()`.

## Récapitulatif du Module 0 (les fondations)
`env` validé · **session** cookies httpOnly · **client API** (Bearer, erreurs typées) · **DAL** (`requireUser`) · **login** réel (Server Action + Zod) · **proxy** (garde + refresh proactif) · **shell** (sidebar, user, déconnexion, routes protégées). Toute la plomberie « moderne, sécurisée, évolutive » est en place.

## À tester
```bash
npm run dev
```
Connecte-toi → ton **nom et ton e-mail** doivent apparaître en bas de la sidebar. Clique dessus → **Se déconnecter** te ramène à `/login`.
> Si le nom n'apparaît pas (seulement l'e-mail), c'est que `/api/me` nomme ses champs autrement que `prenom`/`nom` — dis-le-moi et j'ajuste `SessionUser`. Et confirme que la déconnexion fonctionne (le clic sur l'item base-ui).

## Suite : Module 1 — premier module métier
Je recommande de commencer par un **référentiel CRUD simple : les Villes**. C'est le plus petit module « complet » (liste, création, édition, suppression) — il va nous servir à **poser les patterns réutilisables** pour tous les autres : liste en RSC pilotée par l'URL (recherche/tri/pagination en `searchParams`), formulaire + Server Action + validation Zod + gestion d'erreurs + toasts, et une `DataTable` (react-table) propre.

Alternatives si tu préfères : **Mon profil** (petit, personnel) ou directement le **vrai tableau de bord** (plus visuel mais plus complexe).

Tu confirmes qu'on part sur les **Villes**, ou tu préfères un autre module pour démarrer ?








Quand je clique sur mon nom et email j'obtient l'erreur "Base UI: MenuGroupContext is missing. Menu group parts must be used within <Menu.Group> or <Menu.RadioGroup>." , et oui commence par les villes

C'est un bug base-ui : `DropdownMenuLabel` (un « menu group part ») doit être **enveloppé dans un `DropdownMenuGroup`** — le composant sidebar-07 d'origine le faisait, et je l'ai retiré par erreur en simplifiant. Je corrige.

Corrigé. Maintenant on démarre le **module Villes**. J'explore le contrat backend et le pattern du frontend actuel pour m'en inspirer.

Contrat Villes clair :
- `GET /api/villes` — recherche `?nom=` (partial), tri `?order[nom]=`, pagination `?page=`.
- `POST /api/villes` `{nom}` · `PATCH /api/villes/{id}` `{nom}` · `PATCH /api/villes/{id}/remove` (soft-delete → corbeille).
- Champs : `id`, `nom` (min 2), + dates.

**Choix d'architecture pour les listes** (que je t'explique) : plutôt que `react-table` côté client, je fais la liste en **RSC pilotée par l'URL** — recherche/tri/pagination passent en `searchParams` et sont exécutés par le **backend**. Résultat : moins de JS, données toujours fraîches, liens partageables, et le bouton Précédent/Suivant est un simple `<Link>`. C'est le pattern « RSC-first » qu'on a retenu. Je colocalise tout le module dans `app/(app)/villes/` (dossiers privés `_lib`).

Je commence par les **types** et la **requête de liste** :

Deux composants **réutilisables** par tous les futurs modules — recherche (debounced, met à jour l'URL) et pagination (liens) :

La **page liste** (RSC, pilotée par l'URL) et son **skeleton** de chargement :

J'ajoute **Villes** à la navigation de la sidebar :

Je valide le tout (fix dropdown + module Villes) :

Build **vert** — `/villes` apparaît en `ƒ (Dynamic)`. Le fix du menu et la liste des villes compilent.

## Brique 1.1 — Liste des villes (lecture)
- [_lib/types.ts](nexttransport/app/(app)/villes/_lib/types.ts) + [_lib/queries.ts](nexttransport/app/(app)/villes/_lib/queries.ts) : `getVilles({ q, page })` appelle `/api/villes` (recherche `nom` partial, tri, pagination) et **normalise** la réponse hydra (`member`/`totalItems`, avec ou sans préfixe `hydra:`).
- [page.tsx](nexttransport/app/(app)/villes/page.tsx) : liste **RSC** — lit `searchParams` (une **Promise** en Next 16), affiche la table shadcn, gère l'état vide.
- Deux composants **réutilisables** posés pour tous les modules : [list-search.tsx](nexttransport/components/list-search.tsx) (recherche debouncée qui écrit `?q=` dans l'URL) et [list-pagination.tsx](nexttransport/components/list-pagination.tsx) (liens `?page=`).
- [loading.tsx](nexttransport/app/(app)/villes/loading.tsx) : skeleton affiché automatiquement pendant le chargement (Suspense).
- **Villes** ajouté à la sidebar.

**Le pattern est maintenant en place** : recherche/tri/pagination = état dans l'URL, exécutés par le backend, rendus en RSC. On le réutilisera pour chaque module (gares, lignes, tarifs, billets…).

## À tester
```bash
npm run dev
```
- Le menu **utilisateur** (clic sur ton nom) ne doit plus planter, et **Se déconnecter** doit marcher.
- Clique **Villes** dans la sidebar → la liste de tes villes s'affiche. Tape dans la recherche → la liste se filtre (côté serveur, après 300 ms). Au-delà de 30 villes, la pagination apparaît.

## Suite : brique 1.2 — Création d'une ville
- Schéma **Zod** (`nom` min 2) + Server Action `creerVille` (`POST /api/villes`, gestion des `violations` d'unicité renvoyées par le backend).
- Page `/villes/nouveau` + formulaire (`useActionState`), bouton **« Nouvelle ville »** sur la liste.
- **Toasts** de feedback (succès/erreur) + `revalidatePath` pour rafraîchir la liste après création.

Teste la liste et le menu, et on enchaîne sur la création (1.2).









Oui on enchaîne, aussi j'ai cette issue quand je fais une recherche "Base UI: A component is changing the default value state of an uncontrolled FieldControl after being initialized. To suppress this warning opt to use a controlled FieldControl."

Deux choses. D'abord ton warning : mon champ de recherche est **non-contrôlé** (`defaultValue`), or sa valeur par défaut change quand l'URL `?q=` change après une recherche → base-ui s'en plaint. Je le passe en **contrôlé** (state local), ce qui supprime le warning :

Corrigé. Maintenant **1.2 — création**. Je regarde d'abord l'API du composant `toast` installé (pour le feedback) et je vérifie le format des erreurs de validation renvoyées par le backend.

API toast comprise : un `<Toaster />` à monter une fois, puis `toast.add({ title, description, type })` pour émettre. J'implémente 1.2.

**Toaster** monté dans le layout racine (disponible partout) :

Le **schéma Zod** et la **Server Action** de création (avec extraction des `violations` d'unicité du backend) :

Le **formulaire** (client, `useActionState` + toast) et sa **page** :

J'ajoute le bouton **« Nouvelle ville »** sur la liste :

Je valide 1.2 (le point sensible étant l'API `toast.add`) :

Types OK (l'API `toast.add` est bonne). Build complet :

Build **vert** — `/villes/nouveau` en place. Brique **1.2 (création) terminée**.

## Ce qui a été posé
- **Toaster** monté une fois dans le layout racine → notifications disponibles partout.
- [_lib/schema.ts](nexttransport/app/(app)/villes/_lib/schema.ts) : validation Zod (miroir du backend).
- [_lib/actions.ts](nexttransport/app/(app)/villes/_lib/actions.ts) : Server Action `creerVille` → `POST /api/villes`, **traduit les `violations` ApiPlatform en erreurs de champ** (ex. nom déjà utilisé), et `revalidatePath("/villes")` pour rafraîchir la liste.
- [_components/ville-form.tsx](nexttransport/app/(app)/villes/_components/ville-form.tsx) : formulaire client (`useActionState`) → **toast** de succès + retour à la liste, ou erreur inline / toast.
- Page `/villes/nouveau` + bouton **« Nouvelle ville »** sur la liste.
- Champ de recherche corrigé (contrôlé → plus de warning base-ui).

**Le pattern « mutation » est établi** : Server Action (validation Zod → API → `violations` → `revalidatePath`) + formulaire `useActionState` + toast. Réutilisable pour tous les modules.

## À tester
```bash
npm run dev
```
- **Villes → Nouvelle ville** → saisis un nom → **Créer** : toast « Ville créée » + retour à la liste, la ville y apparaît.
- Recrée **le même nom** → l'erreur d'unicité du backend s'affiche **sous le champ**.
- La recherche ne déclenche plus de warning.

> Note : le bouton « Nouvelle ville » est visible pour tous pour l'instant ; si tu n'as pas la permission `VILLE_CREER`, le backend renverra un 403 (toast d'erreur). On câblera l'affichage conditionnel selon les permissions quand on enrichira `SessionUser`.

## Suite : brique 1.3 — Édition & suppression
- `modifierVille` (`PATCH /api/villes/{id}` — j'ajusterai le `Content-Type` en `application/merge-patch+json` requis par ApiPlatform) et `supprimerVille` (`PATCH /api/villes/{id}/remove` → mise en **corbeille**, avec confirmation).
- Page `/villes/[id]/modifier` (formulaire réutilisé en mode édition) + menu d'actions par ligne dans la table.

Teste la création, et on termine le CRUD des villes avec l'édition/suppression (1.3).









Oui continue, et quand je dans mes issues "Base UI: A component that acts as a button expected a native <button> because the `nativeButton` prop is true. Rendering a non-<button> removes native button semantics, which can impact forms and accessibility. Use a real <button> in the `render` prop, or set `nativeButton` to `false`.
    at Button (about://React/Server/C:%5CUsers%5Cadamo%5CDocuments%5CWeb%5CiTransport%5Cnexttransport%5C.next%5Cdev%5Cserver%5Cchunks%5Cssr%5C%5Broot-of-the-server%5D__20dhujc._.js?60:70:263)
    at VillesPage (about://React/Server/C:%5CUsers%5Cadamo%5CDocuments%5CWeb%5CiTransport%5Cnexttransport%5C.next%5Cdev%5Cserver%5Cchunks%5Cssr%5C%5Broot-of-the-server%5D__20dhujc._.js?59:369:272)"


Bon warning à corriger. Mon `<Button render={<Link/>}>` fait rendre un `<a>` là où base-ui attend un `<button>` natif — ça casse la sémantique. Le pattern shadcn propre pour un **lien stylé en bouton** est `<Link className={buttonVariants(...)}>` (un vrai `<a>`, pas de `Button`). Je vérifie que `buttonVariants` est exporté :

`buttonVariants` est bien exporté. Je corrige les liens-boutons (pattern shadcn : `<Link className={buttonVariants(...)}>`), puis j'enchaîne sur 1.3 (édition + suppression).

D'abord, **le client API** : je prépare le PATCH ApiPlatform (qui exige `application/merge-patch+json`) et j'évite d'écraser un `Content-Type` fourni :

Je corrige la pagination (liens stylés, plus de `Button` non-natif) :

Maintenant **1.3**. J'ajoute `getVille(id)` à la requête :

J'ajoute `ApiError` à l'import de queries.ts (utilisé par `getVille`) :

J'ajoute les actions `modifierVille` et `supprimerVille` (je factorise la gestion d'erreur commune) :

Je généralise le **formulaire** pour servir création *et* édition, en corrigeant au passage le bouton « Annuler » :

Je mets à jour la page de création (nouvelles props du formulaire) :

La **page d'édition** (`[id]/modifier`), avec 404 propre et l'action liée à l'id :

Le menu d'actions par ligne aura une confirmation de suppression. Je regarde l'API de l'`AlertDialog` pour l'utiliser correctement :

`AlertDialog` a une API standard. Je crée le **menu d'actions par ligne** (Modifier + Supprimer avec confirmation → corbeille) :

Enfin, je mets à jour la liste : bouton « Nouvelle ville » en lien stylé, et **colonne Actions** avec le menu :

Je valide tout 1.3 (types + build) :

Build **vert** — `/villes/[id]/modifier` en place. Le **CRUD complet des Villes** fonctionne, et tous les patterns réutilisables sont posés.

Je note le jalon en mémoire (pour la reprise) :

Mémoire à jour. **Module 1 (Villes) terminé** — un référentiel CRUD complet.

## Brique 1.3 + correctifs
- **Édition** : `getVille(id)`, action `modifierVille` (via `action.bind(null, id)`), page `/villes/[id]/modifier` réutilisant le **même formulaire** (généralisé création/édition).
- **Suppression** : `supprimerVille` (`PATCH /villes/{id}/remove` → **corbeille**) + menu d'actions par ligne avec **confirmation** (`AlertDialog`) ; l'erreur de garde backend (« ville rattachée à N gares actives ») remonte en toast.
- Correctifs : **`api.patch`** envoie désormais `application/merge-patch+json` (exigé par ApiPlatform) ; **liens-boutons** passés en `<Link className={buttonVariants()}>` → plus de warning base-ui `nativeButton` (bouton « Nouvelle ville », « Annuler », pagination).

## À tester (le CRUD complet)
```bash
npm run dev
```
- **Créer** une ville → toast + apparaît dans la liste.
- Menu **⋯ → Modifier** → change le nom → toast « Ville modifiée ».
- Menu **⋯ → Supprimer** → confirmation → « mise en corbeille », disparaît de la liste.
- Essaie de supprimer une ville **rattachée à des gares** → message d'erreur (garde backend) en toast.
- Vérifie qu'il n'y a plus de warning au chargement de `/villes`.

## Suite : Module 2 — les Gares
C'est le bon prochain module car il **réutilise tout le patron des Villes** *et* introduit une nouveauté clé : une **relation** (une gare appartient à une ville) → on posera le pattern du **select/combobox de référence** (choisir la ville dans le formulaire de gare), plus quelques champs supplémentaires (chef de gare, contacts). Ce sera plus rapide maintenant que les fondations et le patron CRUD existent.

Teste les villes, et on enchaîne sur les gares.





Oui

On enchaîne sur le **Module 2 — les Gares**. J'explore d'abord le contrat backend (champs, format de la relation ville en lecture/écriture, endpoints).

Contrat Gares clair :
- **Lecture** : `id, libelle, ville` (nom), `villeId`, `chefgare, contact1, contact2, statut, datecreation`.
- **Écriture** : `{ libelle, ville: "/api/villes/{id}", chefgare, contact1, contact2, description, datecreation }`.
- Endpoints : GET (collection **sans pagination** → tout, **pas de SearchFilter**), GET item, POST, PATCH, `/suspendre` (admin), `/remove` (corbeille).

**Choix expliqué** : contrairement aux Villes (backend paginé + filtrable → liste RSC/searchParams), le backend renvoie **toutes** les gares sans filtre serveur → la recherche se fera **côté client**. C'est le « mix » en pratique : le bon outil selon ce que l'API offre.

Je fais la brique **2.1 (liste)**. D'abord j'extrais un petit helper hydra réutilisable :

Je refactorise la requête Villes pour l'utiliser (cohérence) :

Maintenant le module Gares — **types** et **requêtes** :

La **page** (RSC) et la **table cliente** (recherche locale + badge de statut) :

J'ajoute **Gares** à la sidebar :

Build **vert** — `/gares` en place. Brique **2.1 (liste des gares) terminée**.

## Ce qui a été posé
- [lib/hydra.ts](nexttransport/lib/hydra.ts) : helper réutilisable pour lire les collections ApiPlatform (et j'ai refactoré Villes pour l'utiliser).
- [_lib/types.ts](nexttransport/app/(app)/gares/_lib/types.ts) + [_lib/queries.ts](nexttransport/app/(app)/gares/_lib/queries.ts) : `getGares()` (liste complète) et `getGare(id)`.
- [_components/gares-table.tsx](nexttransport/app/(app)/gares/_components/gares-table.tsx) : table avec **recherche côté client** (le backend ne pagine/filtre pas les gares) + **badge de statut** (Active/Suspendue).
- **Gares** ajouté à la sidebar.

**Le contraste pédagogique est posé** : Villes = liste **serveur** (searchParams, `revalidate`) ; Gares = liste **client** (tout chargé, filtre local). On choisit selon ce que l'API expose — c'est le « mix » assumé.

## À tester
```bash
npm run dev
```
→ **Gares** dans la sidebar : la liste s'affiche (libellé, ville, chef de gare, contact, statut). Tape dans la recherche → filtrage instantané côté client.

## Suite : brique 2.2 — Création d'une gare
C'est là qu'arrive **la nouveauté du module** : le formulaire de gare a plusieurs champs (libellé, chef de gare, contacts, description, date) **et surtout un choix de ville**. On posera le pattern du **combobox de référence** : charger les villes côté serveur, les passer à un `Combobox` (recherche intégrée) côté client, et envoyer la ville au backend sous forme d'**IRI** (`/api/villes/{id}`). Le reste (Zod, Server Action, toast) réutilise le patron des Villes.

Teste la liste des gares, et on attaque le formulaire avec le combobox ville.







Compris — pour les CRUD simples je fais **tout le module d'un coup**. Je termine donc le CRUD Gares (création + combobox ville, édition, suppression, suspension). D'abord je regarde l'API du `Combobox` (la nouveauté) pour choisir la ville :

Le `Combobox` base-ui est riche mais complexe et incertain à câbler. Pour un select de référence dans un formulaire, le plus robuste et le plus adapté est le **`native-select`** : un `<select>` natif stylé, **accessible**, et surtout qui s'intègre **directement à `FormData`** (via `name`) — pas de state ni de hidden input. Je vérifie son API :

`NativeSelect` est parfait : un `<select>` natif stylé qui marche directement avec `FormData`. Je construis **tout le CRUD Gares d'un coup**.

**Requêtes** — j'ajoute le chargement des villes pour le select :

**Schéma Zod** :

**Server Actions** (création, édition, suppression, suspension) :

**Formulaire** (création/édition, avec le select natif de ville) :

**Menu d'actions par ligne** (Modifier, Suspendre/Réactiver, Supprimer) :

J'ajoute la colonne Actions à la table des gares :

Les **pages** création et édition, et le bouton sur la liste :

Build **vert** — les 3 routes Gares en place. **Module 2 (Gares) complet en un tour.**

Je mets à jour la mémoire :

## Module 2 — Gares : CRUD complet

- **Liste** : recherche **côté client** + badge de statut (Active/Suspendue).
- **Création / édition** : formulaire réutilisé avec **select natif de ville** (`NativeSelect`) — accessible et compatible `FormData` directement, envoyé au backend en **IRI** `/api/villes/{id}`. En édition, la page charge gare + villes en **parallèle** (`Promise.all`) et pré-remplit tout (ville comprise).
- **Suppression** (corbeille, `AlertDialog`) et **Suspendre/Réactiver** (toggle du statut) dans le menu d'actions par ligne.
- Réutilise intégralement le patron des Villes (Zod, Server Actions, `violations`, toast, `revalidatePath`).

> Pour le champ ville j'ai choisi un **select natif** plutôt que le `Combobox` : c'est plus simple, robuste et accessible pour un référentiel. Si un jour il y a beaucoup de villes, on basculera sur un combobox à recherche (voire une recherche serveur).

## À tester
```bash
npm run dev
```
**Gares** → **Nouvelle gare** : choisis une ville, remplis les champs → **Créer**. Puis **⋯ → Modifier** (tout est pré-rempli, ville incluse), **Suspendre/Réactiver** (le badge change), **Supprimer** (corbeille). Essaie de créer une gare avec un libellé/chef/contact déjà pris → l'erreur d'unicité s'affiche sous le champ.

## Suite
Le patron est maintenant très rodé — les prochains référentiels iront vite (CRUD complet d'un module par tour). Par quoi veux-tu continuer ?
- **Référentiels simples** (rapides) : Fournisseurs, Marques, Modèles, Types (personnel / pièce / véhicule / panne)…
- **Modules plus riches** : **Lignes** (itinéraire ordonné d'arrêts + durées de tronçon — le plus complexe), **Tarifs** (prix par couple de gares), **Cars** (flotte), **Personnel**.

Dis-moi lequel et je le déroule en entier.







Fais les Référentiels simples

On attaque les référentiels simples. Comme il y en a plusieurs et qu'ils se ressemblent, j'explore d'abord leur structure exacte (champs, relations, endpoints) pour décider quoi factoriser.

Le grep a raté les champs (les groupes sont avant la propriété). Je regarde mieux les propriétés et relations de chaque entité :

Découverte clé : **8 référentiels ont une structure identique** (`id` + `libelle` seul, aucune relation) — Marque, Marquepiece, Model, Modelvehicule, Typepersonnel, Typepiece, Typevehicule, Typepanne. Seul **Fournisseur** est un peu plus riche (libellé, contact, email, adresse). Ça appelle une **factorisation**. Je vérifie juste la pagination et la validation avant de concevoir :

Verdict : 8 référentiels **strictement identiques** (`libellé` seul, non paginés). Je **factorise** avec une route dynamique `/referentiels/[type]` + une config centralisée → les 8 CRUD d'un coup, sans dupliquer. (Fournisseur, un peu plus riche, suivra en module dédié.)

Je pose d'abord la **config + le socle serveur** :

Les **Server Actions** génériques (l'endpoint vient de la config serveur, pas du client — sécurisé) :

Les **composants génériques** (formulaire, menu d'actions, table) :

Les **3 pages** de la route dynamique :

La **sidebar** avec un groupe déroulant « Référentiels » (8 entrées) :

Build **vert** — les 3 routes `referentiels/[type]` en place. **Les 8 référentiels « libellé simple » sont livrés d'un coup** grâce à la factorisation.

## Ce qui a été fait — un seul système pour 8 référentiels
- [lib/ref/config.ts](nexttransport/lib/ref/config.ts) : la config centralise les 8 types (endpoint + libellés). Ajouter un référentiel = **une ligne**.
- Socle serveur : [queries](nexttransport/lib/ref/queries.ts) + [mutations](nexttransport/lib/ref/mutations.ts) génériques ; Server Actions [_actions.ts](nexttransport/app/(app)/referentiels/[type]/_actions.ts) où **l'endpoint vient de la config serveur** (jamais du client → pas d'endpoint arbitraire).
- Composants génériques réutilisables : [ref-table](nexttransport/components/ref/ref-table.tsx), [ref-form](nexttransport/components/ref/ref-form.tsx), [ref-actions](nexttransport/components/ref/ref-actions.tsx).
- **3 fichiers de routes** (`page`, `nouveau`, `[id]/modifier`) desservent les 8 référentiels via `[type]`.
- Sidebar : groupe déroulant **« Référentiels »** avec les 8 entrées (menu collapsible).

Couverts : **Marques de véhicule/pièce, Modèles de véhicule/pièce, Types de personnel/pièce/véhicule/panne** — chacun avec liste + recherche + création + édition + suppression (corbeille).

## À tester
```bash
npm run dev
```
Sidebar → **Référentiels** (se déplie) → choisis-en un (ex. **Types de personnel**) → crée « Chauffeur », modifie-le, supprime-le. Vérifie la recherche et le message d'unicité sur un doublon.

## Il reste **Fournisseurs**
C'est le seul référentiel qui n'entre pas dans le système générique (il a plusieurs champs : libellé, contact, e-mail, adresse). Je le fais en **module dédié** (comme Villes/Gares) — c'est rapide. Je l'enchaîne maintenant ?








Oui

On finit avec **Fournisseurs**. Je vérifie les détails de l'entité (validation, champs requis/optionnels) :

Fournisseur : `libelle` (requis), `contact`, `email`, `adresse`, `pays` (tous optionnels). Je déroule le module dédié en réutilisant le patron Villes/Gares.

**Types + requêtes + schéma :**

**Server Actions :**

**Formulaire, menu d'actions, table :**

**Les 3 pages :**

J'ajoute **Fournisseurs** à la sidebar :

Build **vert** — module Fournisseurs complet. **Tous les référentiels simples sont livrés.** Je mets à jour la mémoire :

## Fournisseurs + tous les référentiels : terminés ✅

**Module Fournisseurs** (dédié) : liste (recherche client), création/édition (libellé, contact, e-mail, adresse, pays — seul le nom requis, e-mail validé s'il est rempli), suppression (corbeille). Ajouté à la sidebar.

**Bilan des référentiels simples livrés :**
- **8 référentiels génériques** via `/referentiels/[type]` (Marques véhicule/pièce, Modèles véhicule/pièce, Types personnel/pièce/véhicule/panne) — une seule base de code.
- **Fournisseurs** — module dédié.

## À tester
```bash
npm run dev
```
- Sidebar → groupe **Référentiels** (les 8) + **Fournisseurs** en entrée directe.
- Sur chacun : liste, recherche, **Nouveau**, **Modifier**, **Supprimer** (corbeille), et le message d'unicité sur un doublon.
- Pour un fournisseur : e-mail invalide → erreur sous le champ ; champs facultatifs laissés vides → OK.

## Suite — les modules « riches »
Les CRUD simples sont tous là. Restent les modules avec de la logique métier :
- **Cars** (flotte) — relations marque/modèle/type + statut ; proche des Gares.
- **Personnel** — type de personnel + infos ; simple/moyen.
- **Tarifs** — prix par **couple de gares** (deux relations gare) + création auto du sens inverse.
- **Lignes** — le plus complexe : **itinéraire ordonné d'arrêts** + durées de tronçon (édition d'une liste dynamique).

Par lequel veux-tu continuer ? Je suggère **Cars** ou **Personnel** pour rester dans une montée en difficulté progressive, mais on peut attaquer **Lignes** ou **Tarifs** si tu préfères le défi.








On continue avec  Cars.

Pour les modules riches je veux que tu utilise le composant data-table de **shadcn-ui** histoire d'avoir un tableau riche et moderne avec les filtre, pagination, tri, etc...

Aussi j'ai l'erreur suivante dans mes issues dans la partie referentiels "Base UI: A component is changing the default open state of an uncontrolled Collapsible after being initialized. To suppress this warning opt to use a controlled Collapsible."





Deux points. D'abord ton warning **Collapsible** : dans la sidebar, `defaultOpen` change quand tu navigues vers/depuis un référentiel → base-ui exige un Collapsible **contrôlé**. Je le passe en contrôlé (ouvert automatiquement sur une route référentiel, mais tu peux le refermer) :

Collapsible corrigé. Maintenant j'explore le contrat **Car** (champs, relations, endpoints) avant de construire la DataTable + le module :

Car est un module riche : `matricule`, `nbrsiege`, `etat`, **3 relations** (marque, typevehicule, modelvehicule) + champs de plan de sièges. Je regarde le modèle du frontend actuel pour connaître le **format exact des relations en lecture** (nesté vs IRI) :

Format confirmé : **read** = relations nestées `{ id, libelle }` (marque, typevehicule, modelvehicule) ; **write** = IRI. Le plan de sièges (complexe) sera hors du formulaire de base. Je regarde les valeurs d'`etat` puis je construis la DataTable :

`etat` : DISPONIBLE / EN_VOYAGE / EN_PANNE / EN_MAINTENANCE. J'ai tout. Je construis d'abord la **DataTable générique shadcn/react-table** (réutilisable par tous les modules riches) — pagination, en-têtes triables, puis le tableau :

La DataTable générique est prête (tri, filtre, pagination client). Maintenant le module **Cars**. **Types + requêtes** (dont les options des 3 selects) :

**Schéma + Server Actions :**

**Menu d'actions + table (colonnes react-table + DataTable) :**

**Le formulaire** (matricule, sièges, statut, 3 selects de référence) :

**Les 3 pages + la sidebar :**

Build **vert** (tsc OK). Je confirme juste que les routes Cars sont bien générées (le `tail` les avait coupées) :

Les 3 routes Cars sont générées. **Module Cars complet + DataTable générique livrés.** Je mets à jour la mémoire :

## Module Cars + DataTable moderne : terminés ✅

**DataTable générique** (shadcn / TanStack Table) — réutilisable par tous les modules riches : [data-table.tsx](nexttransport/components/data-table/data-table.tsx) (tri + filtre + pagination **côté client**), [en-têtes triables](nexttransport/components/data-table/data-table-column-header.tsx), [pagination](nexttransport/components/data-table/data-table-pagination.tsx).

**Module Cars** (CRUD complet) :
- **Liste** avec la DataTable : recherche par matricule, **tri sur chaque colonne** (clic sur l'en-tête), pagination — badge de statut coloré.
- **Création/édition** : matricule, nombre de sièges, statut (Disponible/En voyage/En panne/En maintenance) et **3 relations** (marque, modèle, type) via selects → envoyées en IRI. Colonnes/relations gérées proprement (nestées `{id, libelle}` en lecture).
- **Suppression** (corbeille).
- **Collapsible** de la sidebar corrigé (contrôlé → plus de warning).

> **Choix assumé** : le **plan de sièges** (`sieges_gauche/droite/plansieges`) n'est pas dans le formulaire — c'est une UI dédiée (grille de sièges) à faire plus tard ; le nombre de sièges suffit à la capacité pour l'instant.

## À tester
```bash
npm run dev
```
**Cars** → tri en cliquant les en-têtes, recherche matricule, pagination si > 10 véhicules. **Nouveau car** (choisis marque/modèle/type), **Modifier** (tout pré-rempli), **Supprimer**.

## Suite
Il reste les modules riches : **Personnel** (simple, type + infos), **Tarifs** (couple de gares + sens inverse auto), **Lignes** (le plus complexe : itinéraire ordonné d'arrêts). Par lequel continue-t-on ?










Termine d'abord le plan de sièges (sieges_gauche/droite/plansieges).

Aussi pour le DataTable je veux le tri, filtre, pagination soient faite côté serveur



Deux demandes distinctes. Je commence par le **plan de sièges** (comme demandé « d'abord »), puis on fera la DataTable côté serveur ensuite.

Le plan de sièges est spécifique — je dois d'abord comprendre le format exact de `plansieges`/`sieges_gauche`/`sieges_droite` et comment le frontend actuel le saisit.

Format compris : `plansieges` = grille (tableau de rangées, chaque cellule = n° de siège ou `null` pour une allée/trou ; marqueur lettre « B: » = banquette arrière). Le frontend actuel le saisit en **texte** (une ligne par rangée) converti en grille. Je regarde la conversion inverse (grille → texte) pour l'édition :

J'ai les deux conversions. Je reproduis le format en TypeScript (helper neutre, utilisé côté client pour l'aperçu et côté serveur pour l'envoi) :

Maintenant l'**éditeur de plan** — un composant avec les sièges gauche/droite, le plan personnalisé en texte, **et un aperçu visuel en temps réel** :

J'intègre les nouveaux champs. **Type Car** :

**Schéma** — j'ajoute les champs du plan :

**Actions** — `buildPayload` et `parse` prennent en compte le plan :

**Formulaire Car** — j'ajoute l'éditeur de plan et ses valeurs par défaut :

Enfin, la page d'édition passe les valeurs du plan (grille → texte) :

Build **vert** — le plan de sièges est intégré.

## Plan de sièges (Cars) — terminé
- Champs **sièges à gauche / à droite** (par rangée) + **plan personnalisé** en texte (une ligne = une rangée ; `.` = allée ; `B:` = banquette) — même format que le backend.
- **Aperçu visuel en temps réel** : le plan se dessine (cases numérotées, allées, banquette) à mesure que tu tapes. [plan-sieges-field.tsx](nexttransport/app/(app)/cars/_components/plan-sieges-field.tsx)
- Conversion texte ⇄ grille ([plansieges.ts](nexttransport/app/(app)/cars/_lib/plansieges.ts)) ; à l'édition le plan existant est pré-rempli ; vide → le backend génère le plan standard depuis gauche/droite.

**À tester** : `/cars/nouveau` → saisis `3 4 5 . 2 1` puis une 2ᵉ ligne, l'aperçu se met à jour ; laisse vide → « plan standard généré ».

## DataTable côté serveur — un point à trancher avec toi
Rendre le tri/filtre/pagination **côté serveur** est le bon choix pour les **gros volumes** (billets, réservations, voyages…). Techniquement, la DataTable devient « manuelle » : les contrôles écrivent dans l'URL (`?page=&order[x]=&q=`) et le backend fait le travail.

**Le blocage** : ça exige que le backend **expose** pagination + tri + recherche sur l'endpoint. Or `/api/cars` a `paginationEnabled: false` et **aucun filtre** — les contrôles serveur seraient donc **sans effet**. L'activer côté backend est possible, **mais `/api/cars` est aussi consommé par le frontend Symfony actuel** (qui charge *tous* les cars) → je le casserais.

Comment veux-tu qu'on procède ?

Parfait — **serveur là où ça compte** : je construis la DataTable server-side et je l'applique à **Villes** (le référentiel qui supporte déjà pagination + recherche + tri côté backend), ce qui pose le pattern pour les futurs gros modules (billets, réservations…). Les petits référentiels restent en client, et je ne touche pas au backend Cars → aucun impact sur l'ancien front.

Bonne nouvelle : mes composants `ListSearch` et `ListPagination` (déjà pilotés par l'URL) sont exactement les contrôles serveur. Il me manque juste l'**en-tête de tri serveur** et la coque **ServerDataTable** (react-table pour le rendu, l'état dans l'URL).

Maintenant je migre **Villes** vers ce pattern. J'adapte d'abord la requête pour un tri générique (nom ou date) :

Les **colonnes** (avec en-têtes triables serveur) et la **table** :

Enfin, je réécris la page Villes (extraction du tri depuis l'URL + la nouvelle table) :

Le build échoue **uniquement** sur le téléchargement des polices Geist depuis Google Fonts (hoquet réseau, pas mon code — tsc est vert). Je relance :

Build **vert** (c'était bien le hoquet réseau sur les polices). Les deux demandes sont faites. Je mets à jour la mémoire :

Les deux demandes sont livrées.

## 1. Plan de sièges (Cars) — terminé
Éditeur complet dans le formulaire de car : sièges gauche/droite, plan personnalisé en texte, et **aperçu visuel qui se dessine en temps réel**. Conversion texte ⇄ grille identique au backend ; à l'édition le plan existant est pré-rempli.

## 2. DataTable côté serveur — construite et appliquée à Villes
- [server-sort-header.tsx](nexttransport/components/data-table/server-sort-header.tsx) : en-têtes **triables côté serveur** — un clic écrit `?order[champ]=asc|desc` dans l'URL, le backend trie.
- [server-data-table.tsx](nexttransport/components/data-table/server-data-table.tsx) : react-table ne fait que le **rendu** (`manualSorting/Filtering/Pagination`) ; la recherche (`?q=`) et la pagination (`?page=`) réutilisent `ListSearch`/`ListPagination`. On ne charge **qu'une page** → adapté aux gros volumes.
- **Villes migré dessus** (son backend expose recherche + tri + pagination) : tri sur **Nom** et **Créée le**, recherche, pagination — **tout côté serveur**. C'est le patron pour les futurs gros modules (billets, réservations).

On a donc **deux DataTable** selon le besoin : **client** (petits référentiels, ex. Cars) et **serveur** (gros volumes, backend filtrable).

## À tester
```bash
npm run dev
```
- **Cars → Nouveau/Modifier** : l'éditeur de plan avec aperçu.
- **Villes** : clique les en-têtes **Nom** / **Créée le** → l'URL passe en `?order[...]=` et la liste se retrie (serveur) ; la recherche et la pagination fonctionnent pareil.

## Suite
Prochains modules riches : **Personnel** (type + infos, simple), **Tarifs** (couple de gares + sens inverse auto), **Lignes** (le plus complexe : itinéraire ordonné d'arrêts). Lequel ?








Oui pour le Personnel et fais le show(voir) de car.

Aussi dans la partie création de car j'ai l'erreur suivante dans mes issues "Received NaN for the `children` attribute. If this is expected, cast the value to a string."


Trois tâches : corriger le bug NaN, ajouter la fiche (voir) d'un car, et le module Personnel. Je commence par le **bug NaN** puis j'explore Personnel.

Le NaN vient de l'aperçu du plan : si tu tapes un caractère non numérique, `Number(cell)` donne `NaN` qui est rendu comme children. Je corrige à la source (dans `textToGrid`, tout ce qui n'est pas un nombre positif devient une allée) :

Maintenant j'explore le contrat **Personnel** :

Personnel : nom, prénom, contact, code, **typepersonnel** (relation), image (upload — je l'omettrai), date d'embauche ; non paginé + SearchFilter → **DataTable client**. Je confirme le format de la relation et la validation via le frontend actuel :

Bug corrigé à la source. Personnel confirmé (relation `typepersonnel` nestée `{id, libelle}`, image omise). Je traite les 3 demandes.

**Fiche car (voir)** — d'abord un composant d'affichage du plan réutilisable (sans `"use client"` → utilisable en RSC), et je refactore l'aperçu du formulaire pour l'utiliser :

Je refactore l'aperçu du formulaire pour réutiliser `PlanView` :

La **fiche du car** (`/cars/[id]`) avec ses infos et le plan de sièges rendu :

Je rends le matricule cliquable (vers la fiche) et j'ajoute « Voir » au menu d'actions :

Bien. Maintenant le **module Personnel** (CRUD complet, DataTable client, relation type, suspension). Types + requêtes :

Composants Personnel — **formulaire, menu d'actions, table** :

Les **3 pages** + la sidebar :

Build **vert** — `/cars/[id]` (fiche) et les 3 routes Personnel sont là. Les trois demandes sont faites. Je mets à jour la mémoire :

Les trois demandes sont livrées.

## 1. Bug NaN — corrigé
`textToGrid` transforme désormais toute saisie non numérique (lettre, symbole) en **allée** au lieu de produire `NaN`. Plus d'erreur « Received NaN » dans l'aperçu du plan.

## 2. Fiche du car (voir) — `/cars/[id]`
- Page de détail : matricule + statut, infos (marque, modèle, type, sièges, disposition, date) et le **plan de sièges rendu visuellement**.
- Accès : **matricule cliquable** dans la liste + entrée **« Voir »** dans le menu d'actions.
- J'ai extrait un composant [PlanView](nexttransport/app/(app)/cars/_components/plan-view.tsx) **partagé** entre la fiche (serveur) et l'aperçu du formulaire (client) — une seule source pour le rendu du plan.

## 3. Module Personnel — CRUD complet
- Liste en **DataTable client** (tri colonnes, recherche par nom, pagination) avec badge de statut.
- Création/édition : nom, prénom, **type de personnel** (select → IRI), contact, code, date d'embauche. *(L'upload de photo est omis pour l'instant — comme le plan de sièges au début, ça mérite une UI dédiée.)*
- **Suspendre/Réactiver** + suppression (corbeille).
- Ajouté à la sidebar.

## À tester
```bash
npm run dev
```
- **Cars** : clique un matricule → la fiche s'affiche avec le plan ; « Voir » dans le menu. Le bug NaN dans le formulaire a disparu.
- **Personnel** : liste triable, **Nouveau** (choisis un type), **Modifier**, **Suspendre/Réactiver**, **Supprimer**.

## Suite
Restent les deux plus riches : **Tarifs** (prix par couple de gares + création auto du sens inverse) et **Lignes** (itinéraire ordonné d'arrêts + durées de tronçon — le plus complexe). On continue avec lequel ?







On continue avec les deux, et fais le image (upload) de la partie personnel que tu as omis



Je reprends. Trois livrables : **upload photo Personnel**, module **Tarifs**, module **Lignes**. Avant d'écrire, je dois (1) comprendre le modèle de données réel côté backend pour ces trois sujets, et (2) relire la doc Next embarquée sur l'upload de fichiers (règle `AGENTS.md`). Je commence par l'exploration.

Personnel a bien `image` → une relation `ManyToOne MediaObject`. L'upload passe donc par une entité `MediaObject` dédiée. Regardons comment elle fonctionne.

L'upload = 2 étapes : `POST /api/media_objects` (multipart, champ `file`) → renvoie un IRI + `contentUrl`, puis on attache l'IRI au champ `image` du personnel. Maintenant les deux modules riches — Tarif, Ligne, Arret, Voyage :

Tarif : `garedepart`, `garearrivee` (IRI Gare), `montant` (int positif). Pagination **serveur**, filtres `garedepart.id`/`garearrivee.id` exact, tri sur montant. Le README évoque un « sens inverse auto » — vérifions le `TarifProcessor` et lisons Ligne/Arret :

Le backend crée le sens inverse tout seul — le front n'a rien de spécial à faire, juste l'indiquer. Maintenant Ligne / Arret / Voyage :

Ligne utilise un **DTO `LigneInput`** en entrée (pas l'entité directe). C'est le module le plus complexe. Je lis le DTO d'entrée, le processor et l'entité Arret :

Compris. `LigneInput` = `{ libelle, heuredepart "08:00"|null, arrets: [{gare: <id int>, ordre: 0, dureeTronconMinutes: null|int}, …] }` — les gares sont des **id entiers** (pas des IRI), ordres contigus dès 0, durées tout-ou-rien. Le backend dérive codeligne/origine/terminus. Lisons Arret :

J'ai le modèle complet des trois sujets. Maintenant je vérifie (a) la doc Next embarquée sur les formulaires/upload (règle `AGENTS.md`), (b) mon `lib/api.ts` pour l'envoi multipart, et (c) le README backend pour la logique métier Tarif/Ligne.

`apiFetch` ne gère que le JSON — je devrai ajouter un mode multipart pour l'upload. Voyons la doc Next embarquée (obligatoire avant d'écrire), puis le README backend :

Confirmé : `<input type="file">` → la Server Action reçoit un `File` via `formData.get()`. Un point Next important pour l'upload : la limite de taille par défaut des Server Actions (1 Mo) alors que le backend accepte 5 Mo. Vérifions la config et mon `next.config` :

Il faut monter `bodySizeLimit` à ~6 Mo. Regardons la config existante + les fichiers Personnel actuels + comment l'ancien front gère l'upload et les lignes, et le README backend :

Le README confirme tout. Il existe aussi un « recalage » avancé sur les lignes (hors périmètre v1 — je le noterai). Regardons l'UX de référence de l'ancien front pour la ligne (éditeur d'arrêts ordonnés) et l'upload photo :

Excellente référence UX pour la ligne. J'ai tout ce qu'il faut. Je lis maintenant les fichiers Personnel actuels pour les étendre (photo), puis les modèles Tarif/Ligne de l'ancien front pour le typage :

J'ai tout le contexte nécessaire. Le plan d'implémentation est clair. Je commence par l'infrastructure partagée (support multipart + config), puis les trois livrables.

Infra d'abord : support multipart dans `apiFetch`, `bodySizeLimit`, helper média.

Infra prête. Maintenant l'**upload photo Personnel**. Types + queries (mapping du `contentUrl` en URL absolue) :

Maintenant l'action : upload de la photo si un fichier est fourni, suppression optionnelle, sinon on ne touche pas au champ.

Maintenant les deux actions create/modify pour intégrer l'upload, avec gestion des erreurs d'image :

Maintenant le formulaire : champ photo avec aperçu circulaire, sélection de fichier et option « retirer » en édition.

Le `modifier/page.tsx` doit passer `imageUrl` dans les defaults :

Ajoutons un avatar dans la liste Personnel. Lisons la table :

Vérifions que `Input` accepte `ref` et `type="file"` (React 19 / base-ui) :

Photo Personnel terminée. Passons aux **Tarifs** (pagination/tri serveur). Je regarde le patron Villes (server DataTable) et les composants serveur pour m'y conformer :

Le patron serveur est clair. Pour Tarif il me faut des options de gares pour les filtres et le formulaire. Regardons le module Gares :

Il me faut savoir comment le backend sérialise `garedepart`/`garearrivee` dans un Tarif (IRI seul, ou objet avec libellé ?). Vérifions les groupes de l'entité Gare :

Sous `read:Tarif`, une gare imbriquée expose `id`, `libelle`, `villeNom` — je pourrai afficher les libellés. Je lis le patron d'actions (dropdown + AlertDialog) pour le répliquer :

J'ai tous les patrons. Je crée le **module Tarifs** — types, queries, schema :

Vérifions le nom JSON réel exposé par `getVilleNom()` (le module Gares utilise `ville`, pas `villeNom`) :

Le nom JSON est `ville` (via `SerializedName`), pas `villeNom`. Je corrige le type et la query :

Schema + actions Tarif :

Composants Tarif — formulaire (avec note sur le sens inverse auto), actions, filtres, colonnes, table :

Barre de filtres (gares départ/arrivée → URL) + table :

Pages Tarif — liste (avec filtres), nouveau, modifier :

Le module Tarifs est complet. J'ajoute Tarifs et (à venir) Lignes à la sidebar, en respectant le regroupement Exploitation (Gares → Lignes → Tarifs) :

Le module Tarifs est complet et câblé. Maintenant le plus complexe : **Lignes** (arrêts ordonnés + durées de tronçon). Types d'abord :

Actions Lignes (appelées directement avec un payload typé, validées par Zod côté serveur) :

Le module Lignes a besoin d'un éditeur d'arrêts riche. Vérifions les composants UI disponibles (Card, etc.) :

Tout est disponible (Card, Badge, Field…). Je crée le helper de formatage horaire puis l'éditeur de ligne :

Le formulaire de ligne est prêt. Actions, colonnes, table, fiche et pages :

Fiche de ligne : calcul des heures de passage (pur, réutilisable) + timeline présentationnelle + page :

Pages Lignes — liste, nouveau, modifier, fiche :

Regardons la fiche car pour aligner le style de la fiche ligne (cards, boutons retour/modifier) :

Tous les fichiers sont créés. Je corrige un détail de typage dans le formulaire (import de `LignePayload`), puis je lance le typecheck et le build.

TypeScript passe sans erreur. Lançons le build complet :

Le build est vert — les trois livrables compilent, toutes les routes sont présentes (`/lignes`, `/tarifs`, `/personnels` + sous-routes). Je mets à jour la mémoire projet puis je résume.

Tout est livré et le build passe. Voici le récapitulatif.

## 1. Photo Personnel (upload) — la partie omise
- **Flux Vich/MediaObject en 2 temps** : `POST /api/media_objects` (multipart) → IRI, puis rattachement au champ `image` du personnel.
- **Infra réutilisable posée** :
  - [lib/api.ts](nexttransport/lib/api.ts) accepte désormais un corps `formData` (sans fixer le `Content-Type` — `fetch` génère le boundary).
  - [lib/media.ts](nexttransport/lib/media.ts) : `uploadImage(file)` et `mediaUrl(contentUrl)` (URL absolue pour l'affichage).
  - [next.config.ts](nexttransport/next.config.ts) : `bodySizeLimit: "6mb"` (le défaut de 1 Mo aurait bloqué les images jusqu'à 5 Mo).
- **UX** : champ photo avec **aperçu rond en temps réel**, case « retirer la photo » en édition, **avatar dans la liste**. Le payload envoie l'IRI (nouvelle photo), `null` (retirer) ou rien (inchangé).

## 2. Module Tarifs (grille par couple de gares)
- **DataTable serveur** (pagination + tri `montant` côté backend).
- Filtres = deux selects de gares (**départ / arrivée**) écrits dans l'URL → filtres exacts backend. Pas de recherche texte (ça n'aurait aucun sens ici).
- **Le sens inverse est créé automatiquement par le backend** au POST → je l'indique juste par une note dans le formulaire, sans double saisie.

## 3. Module Lignes (le plus complexe)
- **Éditeur d'arrêts ordonnés** : ajouter/retirer une gare, monter/descendre, **durées de tronçon** (tout-ou-rien) avec **aperçu du cumul et de l'heure de passage estimée** en direct.
- Le backend dérive seul le **code de ligne**, l'origine et le terminus.
- Soumission via **Server Action appelée directement** avec un payload typé (pas un FormData — l'itinéraire est trop structuré), validée par Zod côté serveur.
- **Fiche `/lignes/[id]`** : itinéraire en timeline avec horaires estimés par arrêt.
- *Le « recalage » des tronçons (médiane réelle observée, réservé admin) est laissé pour une v2 — je l'ai noté.*

Sidebar mise à jour (Villes · Gares · **Lignes · Tarifs** · Cars · Personnel · Fournisseurs). **`tsc` et `npm run build` passent au vert.**

## À tester
```bash
npm run dev
```
- **Personnel** → Nouveau/Modifier : choisis une image (aperçu immédiat), enregistre ; l'avatar apparaît dans la liste. En édition, teste « Retirer la photo ». Vérifie qu'une image > 5 Mo est bien refusée (message backend).
- **Tarifs** → crée un tarif A→B : vérifie que **B→A apparaît automatiquement** au même montant. Filtre par gare, trie par montant.
- **Lignes** → crée une ligne avec 3+ arrêts et des durées de tronçon : observe l'aperçu des heures ; ouvre la fiche pour voir la timeline. Teste la validation (durées partielles → refus).

On enchaîne avec **Voyages** (instances de lignes à une date) ou tu préfères d'abord roder ces trois modules en conditions réelles ?







On enchaîne avec Voyages

On enchaîne. Comme pour les modules précédents, je commence par comprendre le modèle réel côté backend : entité Voyage, ses processors, le README, et l'UX de référence de l'ancien front. Je ne conclus pas sur la lecture seule.

C'est l'entité la plus riche du système : un **cycle de vie complet** (créer, démarrer, réceptionner, repartir, clôturer) + affectations (car, personnel, commercial). Je ne vais pas tout implémenter d'un coup — je dois cadrer un v1 pertinent pour un back-office. Lisons le `VoyageProcessor` (contrat de création exact) et le README voyage :

Contrat de création clair : on envoie `ligne` (IRI) + `datedepartprevue` (+ `datearriveeprevue`, `placesprevues`, `car` optionnels) ; le backend dérive provenance/destination/code/gare de provenance. Le PATCH (merge) permet dates/places/**car** (le changement de car déclenche toute la logique de réaffectation des sièges). 

Vu l'ampleur du cycle de vie (démarrer/réceptionner/clôturer/affecter commercial…), je cadre un **v1 back-office = planification + suivi** et je laisse l'exploitation terrain pour plus tard. Vérifions la frise `horaires` (VoyageProvider) et les champs de Car/User exposés :

La frise `horaires` est propre et parfaite pour une timeline. Vérifions les champs de Car/User exposés sous `read:Voyage`, et l'UX de l'ancien front :

J'ai le schéma complet du voyage. Regardons l'ancien `VoyageTable` pour la dérivation du statut et l'UX de création :


J'ai tout le nécessaire. Je cadre le **v1 Voyages = planification + suivi** (liste filtrable, création, fiche avec frise horaires, édition dont changement de car, suppression), en laissant l'exploitation terrain (démarrer/réceptionner/clôturer/commercial) pour une itération dédiée. Je vérifie les helpers cars/lignes existants côté nexttransport :

J'ai le contrat complet (création, PATCH, frise `horaires`, statut dérivé des dates réelles). Je construis le module. Types d'abord :

Helper de statut (dérivé des dates réelles) + formatage :

Queries — liste filtrable (recherche codevoyage, ligne, état via ExistsFilter), fiche, options lignes/cars :

Schema + actions (création/édition/suppression) :

Formulaire (création/édition) — ligne verrouillée en édition, dates, places, affectation car :

Je vérifie que `FieldDescription` est bien exporté par le composant field :

`FieldDescription` est exporté. Filtres (ligne + état), colonnes, table, actions :

Colonnes + table :

Frise des horaires (présentationnel) :

Pages — liste, création, édition, fiche :

Fiche voyage — infos, compteurs, frise horaires :

Sidebar — j'ajoute Voyages après Lignes/Tarifs (module Exploitation) :

Tous les fichiers créés. Typecheck puis build :

Build **vert** — les 4 routes Voyages compilent, TypeScript passe. Je mets à jour la mémoire puis je résume.

Le module **Voyages** est livré et le build passe au vert.

## Ce que j'ai construit (v1 back-office = planification + suivi)

**Liste** (DataTable serveur — pagination/tri backend) : code voyage cliquable, trajet (provenance → destination), départ prévu, car, **badge d'état**, nombre de billets.
- **Filtres** : recherche par code voyage, select **ligne**, select **état** (À venir / En cours / Clôturés — traduits en `exists[...]` côté backend). Tri sur départ prévu et trajet.
- **État dérivé** des dates réelles (jamais stocké) : arrivée réelle → *Clôturé*, départ réel → *En cours*, sinon *À venir*.

**Création** : on choisit une **ligne** + le **départ prévu** (+ arrivée prévue, places prévisionnelles, car — tous optionnels). Le backend dérive tout seul provenance, destination, code voyage et le point de départ réel (origine de la ligne pour un compte central, gare de l'agent pour un départ partiel).

**Édition** : dates, places, et **changement de car** — qui déclenche automatiquement la réaffectation des sièges des billets côté backend. La ligne est **verrouillée** (non modifiable après création, comme le veut le métier).

**Fiche `/voyages/[id]`** : infos complètes, **compteurs** (billets / courriers / bagages / personnel) et surtout la **frise des horaires par gare** — heure prévue (somme des tronçons), heures réelles, retard, avec la **position actuelle du car** mise en évidence.

**Suppression** (corbeille, bloquée s'il reste des billets actifs).

## Laissé pour une itération dédiée « exploitation terrain »
Les actions de cycle de vie liées à la position physique du car : **démarrer / réceptionner / repartir / clôturer**, affecter **personnel** et **commercial à bord**, faire **avancer** la position, et les **bordereaux**. Le backend les expose déjà (routes PATCH + DTO), mais elles sont contextuelles à la gare de l'agent — mieux vaut une UI opérationnelle séparée que de les mélanger au back-office de planification.

## À tester
```bash
npm run dev
```
- **Créer** un voyage sur une ligne existante → vérifie que provenance/destination/code sont bien remplis automatiquement.
- **Filtrer** par ligne et par état ; trier par départ prévu.
- **Fiche** : ouvre un voyage → regarde la frise des horaires (les heures prévues doivent correspondre aux tronçons de la ligne).
- **Changer le car** en édition → si le car est trop petit ou déjà utilisé, message clair du backend.

## Suite
Il reste le **cœur du métier** : **Billetterie / Réservations** (vente par tronçon, surbooking assumé, éviction, report — le sujet le plus riche, cf. le chantier « sièges évincés » déjà en prod côté Symfony), puis **Courrier** et **Bagage**. On attaque la Billetterie ?











## New 2


Salut Claude Code,

Dans la session précédente, on a travaillé sur l'application de compagnie de transport multi-entreprises et multi-gares, en architecture séparée :

## Stack technique
- **Backend** : Symfony, API Platform, LexikJWTAuthenticationBundle, RefreshTokenBundle
- **Frontend** : Symfony, Twig, React (UX), Shadcn/ui, Tailwind v4
- **Apps Mobile** : Flutter, React Native

## Rappel
- **Ignore les fichiers `brl.md`** du projet : ce sont juste des brouillons

## Ce que j'attends

Analyse le projet pour :
- te remettre à jour sur l'architecture générale et les modules existants,
- retrouver l'état d'avancement du projet,
- être prêt pour la suite des instructions que je vais te donner.




Dans l'état actuelle, si une gare intermédiaire réceptionne un voyage qui n'est pas encore à son niveau du genre Bouaké réceptionne alors que le cars est en route vers Yamoussoukro, est ce que ça pénalise Yamoussoukro ?





Bonne question — c'est un cas de saisie erronée bien réel. Je vais lire le chemin de réception plutôt que de répondre de mémoire.

Oui — lourdement, et **sans retour en arrière possible**. J'ai suivi le chemin complet.

## Rien n'empêche la réception prématurée

[`VoyageGuard::assertPeutReceptionner`](Backend-Transport/src/Security/VoyageGuard.php:127) ne vérifie que trois choses : gare intermédiaire, desservie par la ligne, voyage non clôturé. **Aucun contrôle d'ordre ni de position réelle du car.** Bouaké peut donc réceptionner à tout moment.

## Ce que Yamoussoukro perd immédiatement

La réception avance `garecourante` à Bouaké (ordre 2), puis appelle [`cloturerMonteesDepassees`](Backend-Transport/src/Domain/Service/ReservationEcheanceService.php:339) dans la foulée. Yamoussoukro (ordre 1) est désormais « dépassée » :

**Ses réservations vivantes sont fermées sur-le-champ** — `dateexpiration` ramenée à maintenant, place libérée, paiement fermé. Les réservations payées sont marquées `penaliteexoneree` (pas de pénalité financière, c'est la seule consolation), mais au prochain passage du cron elles basculent en `A_REGULARISER` : le client a payé, sa place est reprise, et il faudra le reporter sur un autre départ. Alors que le car n'est même pas encore arrivé chez lui.

Ensuite, `monteeDepassee(Yamoussoukro)` reste vrai en permanence (position 2 > montée 1). Ça ferme pour Yamoussoukro, sur ce voyage :

- la **vente de billets** ([`TicketProcessor:167`](Backend-Transport/src/State/TicketProcessor.php:167))
- la **correction d'un billet** ([`TicketUpdateProcessor:92`](Backend-Transport/src/State/TicketUpdateProcessor.php:92))
- le **désistement**, report comme annulation ([`DesistementProcessor:130`](Backend-Transport/src/State/DesistementProcessor.php:130))
- **création, encaissement, émission du billet et régularisation** d'une réservation

Pire pour le diagnostic : le voyage **disparaît purement et simplement** des sélecteurs ([`VoyagesReservablesProvider:137`](Backend-Transport/src/State/VoyagesReservablesProvider.php:137) et `DepartsPubliquesProvider`). L'agent de Yamoussoukro n'a même pas de message d'erreur — le départ n'est plus dans sa liste, et rien ne lui dit pourquoi.

## Ce qui n'est pas touché

Les **courriers et bagages** de Yamoussoukro ne bougent pas : le processor ne bascule que ceux de la gare qui réceptionne. Yamoussoukro pourra toujours réceptionner plus tard — le garde l'autorise — et ses colis basculeront normalement à ce moment-là.

Le **recalage** est protégé : le tronçon mesuré ressortirait négatif, et [`RecalageService:133`](Backend-Transport/src/Domain/Service/RecalageService.php:133) écarte déjà tout delta ≤ 0 (« un delta ≤ 0 trahit un horodatage erroné »).

## Ce qui est faussé en plus

Le `Passage` de Bouaké porte une **arrivée qui n'a pas eu lieu** : son retard est flatté, et si Yamoussoukro réceptionne ensuite, son arrivée sera postérieure à celle de Bouaké — l'« évolution du retard le long du trajet » devient incohérente. Et si le départ n'avait pas été marqué à l'origine, la réception l'**invente** à l'instant présent (`VoyageDepartService::marquerDepart`).

## Aucune réparation par l'application

`garecourante` est exposée en **lecture seule** ([`Voyage.php:388`](Backend-Transport/src/Entity/Voyage.php:388), groupe `read:Voyage` uniquement) et son avance est **monotone**. Une réception de Yamoussoukro ensuite ne la ramène pas en arrière, aucun endpoint n'annule une réception. Seule une correction directe en base répare — et elle ne ressusciterait pas les réservations déjà fermées.

Aucun test ne couvre l'ordre des réceptions.

---

Si tu veux que je referme ça, il y a deux approches et le choix t'appartient, parce qu'elles n'ont pas le même coût d'exploitation :

1. **Garde d'ordre à la réception** — refuser une réception qui saute un arrêt non encore horodaté. Simple et net, mais une gare qui oublie de réceptionner **bloque toutes les suivantes** : il faudrait un rattrapage explicite (réceptionner pour le compte des gares sautées, ou une action d'admin).
2. **Découpler la fermeture des réservations de la position déclarée** — ne fermer les montées d'une gare que si le **départ de cette gare** est consigné, pas seulement parce que `garecourante` l'a dépassée. Plus tolérant, mais ça élargit une règle qui sert partout, et une gare qui ne pointe jamais son départ continuerait de vendre.

Dis-moi laquelle, ou si tu préfères d'abord voir l'étendue exacte des appels concernés pour la seconde.









Option 1 avec rattrapage par un admin. Je regarde d'abord comment la position avance par ailleurs, pour que la garde et le rattrapage parlent de la même chose.

Le rattrapage doit rester un **pointage de passage**, pas une réception de colis — sinon on déclarerait livrés des colis encore à bord. Je vérifie deux détails avant d'écrire.

Je commence par la garde d'ordre.

Maintenant le service partagé d'avance de position, pour que la réception et le rattrapage ne divergent pas.

Maintenant l'action de rattrapage : l'entrée, la constante d'audit, puis le processor.

Il reste à déclarer l'opération sur l'entité `Voyage`.

Les tests, en commençant par le scénario exact que tu as décrit.

La garde fonctionne. Deux échecs sont dus à mon test (le démarrage horodate à l'instant présent). J'en profite pour fermer aussi le cas d'une heure dans le futur.

10 tests au vert. Je lance la suite complète pour vérifier que la garde ne casse rien ailleurs.

Un test de domaine affirmait que Bouaké pouvait réceptionner directement — c'est précisément ce que la garde interdit désormais. Je le mets à jour.

210 tests au vert. Il reste à exposer l'action côté frontend.

Maintenant l'interface. Je regarde la frise des horaires sur la fiche voyage, l'endroit naturel pour ça.

Je complète les pastilles du journal d'activité pour le nouveau type, puis les README.

Les README, puis vérification en conditions réelles.

Le test en conditions réelles demanderait d'écrire dans ta base de dev (démarrer, réceptionner). Je regarde d'abord, en lecture seule, s'il existe déjà un voyage à la bonne forme.

Voyage 40 est parti, 4 arrêts, **aucune arrivée pointée** — exactement la forme voulue. Un refus n'écrit rien, je peux donc le vérifier sans toucher à tes données.

La garde refuse proprement, et le refus n'écrit rien. Je regarde maintenant l'interface.

La session du navigateur a été perdue en fermant le panneau. Il me faut une reconnexion pour le contrôle visuel.

C'est fait et testé. Voici l'état.

## La garde d'ordre

[`VoyageGuard::assertPeutReceptionner`](Backend-Transport/src/Security/VoyageGuard.php:159) exige désormais que **tous les arrêts situés entre l'origine effective et la gare qui réceptionne portent une arrivée horodatée**. Le refus nomme la gare oubliée et dit par où sortir.

Vérifié à l'instant sur ta base de dev, sur le voyage `LI-ABI-KOR-0001-V37` (parti, aucune arrivée pointée) :

```
PATCH /api/voyages/40/receptionner  (agent de Bouaké)
→ HTTP 400
« Le car ne peut pas être arrivé à Gare de Bouaké sans être passé par Gare de
  Yamoussoukro, qui n'a pas encore été pointée. Faites-la réceptionner, ou
  demandez à un administrateur de rattraper ce passage. »
```

Et après le refus : position toujours à Adjamé, un seul passage (le départ), aucune réservation touchée. **Un refus n'écrit rien.**

Un point de conception que j'ai tranché : la garde cherche une **arrivée dans `Passage`**, pas une réception. L'avance déclarée par le commercial à bord suffit donc — une gare traversée sans agent, le cas ordinaire, ne bloque pas la suivante. Exiger une réception aurait rendu la garde inapplicable là où elle sert le plus.

## Le rattrapage admin

`PATCH /api/voyages/{id}/rattraper-passage`, `ROLE_ADMIN`, [RattraperPassageProcessor.php](Backend-Transport/src/State/RattraperPassageProcessor.php).

**Il consigne un passage, il ne réceptionne pas.** Les courriers et bagages qui descendent à cette gare ne basculent pas : s'il n'y avait personne pour pointer le car, il n'y avait probablement personne pour décharger. La gare fera sa réception ensuite — l'arrivée déjà posée n'est pas réécrite, seuls les colis bougent.

**L'heure réelle est obligatoire et doit s'insérer dans la chronologie** : postérieure au départ ou au passage amont, antérieure au passage aval, jamais dans le futur. Sans cette garde, le rattrapage rouvrait par la porte de service le défaut qu'on vient de fermer — tronçon mesuré négatif, retard de la gare égal à celui de l'oubli — et cette fois signé par un administrateur, donc au-dessus de tout soupçon.

Refus explicites : voyage clôturé, départ non enregistré (sinon le passage serait antérieur au départ), gare hors ligne, origine, terminus, passage déjà consigné. Audité à part sous `VOYAGE_PASSAGE_RATTRAPE`.

## Une chose que j'ai extraite

L'avance de position vit maintenant dans [PositionCouranteService](Backend-Transport/src/Domain/Service/PositionCouranteService.php), partagée par la réception et le rattrapage : deux chemins, un seul état possible. J'ai laissé `AvanceePositionService` (commercial) distinct **exprès** — il ne ferme pas les réservations. Le commercial signale où est le véhicule, la gare *constate* son passage, et c'est ce constat qui décide qu'un client ne montera pas. Les fusionner fermerait des réservations sur une simple déclaration de position, y compris rejouée depuis une file hors ligne.

## Interface

Sur la **frise des horaires** de la fiche voyage, chaque arrêt intermédiaire sans arrivée offre à un admin un dépliant « Passage non pointé — rattraper » : heure réelle, bornée à maintenant, avec confirmation qui rappelle que les colis ne sont pas réceptionnés. Placé là plutôt que dans la barre d'actions — le trou se voit sur la frise, la réparation doit être au même endroit.

## Tests

**210 tests au vert.** Dix nouveaux dans [ReceptionOrdreTest.php](Backend-Transport/tests/Api/ReceptionOrdreTest.php), dont celui qui compte : après un refus, l'échéance de la réservation de Yamoussoukro est **intacte** et la position n'a pas bougé.

Un test de domaine existant affirmait que Bouaké pouvait réceptionner directement — c'est exactement ce que la garde interdit. Je l'ai basculé sur Yamoussoukro, le premier intermédiaire, en renvoyant à la nouvelle suite.

---

Deux choses pour finir :

**Pour le contrôle visuel**, la session du navigateur a été perdue en fermant le panneau tout à l'heure. Si tu te reconnectes sur http://127.0.0.1:8300 (frontend de test, API sur 8200, ta base), j'ouvre le voyage 40 et je te montre le dépliant sur la frise. Le **chemin réussi** (rattraper puis réceptionner) écrit en revanche dans ta base — je ne le lance pas sans ton feu vert.

**Et une question que je te laisse** : `AvanceePositionService`, la voie du commercial, n'a **aucune garde d'ordre** non plus. Un commercial peut donc sauter Yamoussoukro d'un geste. Ça ne ferme pas ses réservations (ce service ne les touche pas), mais ça avance quand même `garecourante` — donc ça bloque la vente à Yamoussoukro via `monteeDepassee`. Tu ne m'as parlé que des gares, je ne l'ai pas élargi de moi-même. Dis-moi si tu veux que je m'en occupe.




Oui.




Je prends ton « oui » pour la voie du commercial. Avant de coder, je regarde comment l'app mobile déclare la progression — le hors-ligne change ce qui est acceptable.

Un point décisif : l'app mobile n'avance **que d'un arrêt à la fois** (`prochainArret`), donc la garde ne lui coûterait rien. Je vérifie ce point et un piège de rejeu hors ligne.

Deux confirmations qui décident de tout :

- l'app n'avance que vers `prochainArret` — **la garde ne lui coûte rien** ;
- mais `PassageService` persiste **sans flusher**, donc ma garde actuelle lit `$voyage->getPassages()` et ne verrait pas une arrivée posée quelques lignes plus haut dans un lot hors ligne. Je corrige ça d'abord.

Maintenant la garde sur la voie du commercial.

Mon édition par script a coupé trop large dans `VoyageGuard` — des méthodes ont disparu. Je restaure et je reprends proprement.

Je complète le test pour couvrir la voie du commercial, y compris le rejeu hors ligne.

12 tests au vert. Suite complète :

Tu as commité le travail précédent entre-temps — les modifications restantes sont donc bien uniquement celles d'aujourd'hui. Voici le bilan.

## La garde, étendue au commercial

Même règle dans [`AvanceePositionService`](Backend-Transport/src/Domain/Service/AvanceePositionService.php:76) que pour les gares : on n'avance pas par-dessus un arrêt jamais pointé.

Le point qui a décidé de l'approche : **l'app du commercial n'avance que vers `prochainArret`**, l'arrêt immédiatement suivant. Un saut lui est impossible, en ligne comme hors ligne, et le rejeu d'une file reproduit la même progression pas à pas. La garde ne lui coûte donc **rien** — pas de refus qui tomberait à la synchronisation après coup, ce qui aurait été rédhibitoire. Elle attrape ce qu'elle doit : un appel direct à l'endpoint, un admin qui saute un arrêt, un futur client mal écrit.

## Un piège que j'ai failli livrer

Ma garde initiale lisait `$voyage->getPassages()`. Or `PassageService::pour()` persiste **sans flusher et sans rattacher** le passage à la collection du voyage. Dans un lot hors ligne, la deuxième avance n'aurait donc pas vu l'arrivée posée par la première, et aurait été refusée au motif qu'elle n'avait jamais eu lieu — exactement le piège que ton `PassageService::connu()` documente pour le départ après une arrivée.

La lecture de la chaîne vit maintenant dans [`PassageService::premierArretNonPointe`](Backend-Transport/src/Domain/Service/PassageService.php:77), qui consulte le cache de requête avant la base. `VoyageGuard` et `AvanceePositionService` l'utilisent tous les deux. Un test verrouille le cas : deux avances successives dans la même séquence doivent passer.

## À signaler : j'ai cassé puis restauré `VoyageGuard`

Une édition par script a coupé trop large et supprimé `monteeAtteinte`, `monteeDepassee`, `aQuitteLaGare` et `ordresPosition`. La suite de tests l'a montré immédiatement (45 échecs, `Call to undefined method`). J'ai restauré le fichier depuis git et refait les trois modifications une par une. Le fichier a bien ses 10 méthodes d'origine plus le constructeur.

## Total

**212 tests au vert**, dont 12 dans [ReceptionOrdreTest.php](Backend-Transport/tests/Api/ReceptionOrdreTest.php).

Vérifié aussi sur ta base de dev, sans rien y écrire : Bouaké se fait refuser la réception du voyage 40 en nommant Yamoussoukro, et après le refus la position est toujours à Adjamé, un seul passage, aucune réservation touchée.

## Reste ouvert

Le **contrôle visuel** du dépliant de rattrapage sur la frise. Les serveurs de test tournent (front `http://127.0.0.1:8300`, API sur 8200, ta base) mais la session du navigateur a été perdue en fermant le panneau. Si tu te reconnectes, j'ouvre le voyage 40 et je te montre. Le chemin réussi — rattraper puis réceptionner — écrit en revanche dans ta base, je ne le lance pas sans ton accord.









Feu vert reçu. Je capture d'abord l'état avant, pour pouvoir te montrer exactement ce qui change.

L'état a changé depuis hier — le car a avancé. Je regarde le détail avant de choisir quoi faire.

Les passages ont été pointés cette nuit à 22:58 et 23:00. Je vérifie dans le journal si c'est le rattrapage qui a servi.

Tu l'as déjà lancé toi-même cette nuit, et ça a marché de bout en bout. Le journal du voyage 40 en garde la trace exacte :

Il n'y avait plus rien à lancer : **tu l'as fait toi-même cette nuit**, et le journal du voyage 40 en garde la trace complète.

```
23:00:04  VOYAGE_RECEPTION          Voyage réceptionné à Gare de Bouaké
          par agent.bouake@ira-transport.ci

22:59:23  VOYAGE_PASSAGE_RATTRAPE   Passage rattrapé à Gare de Yamoussoukro
          par admin@ira-transport.ci     (arrivée déclarée le 21/09/2026 22:58)
```

C'est exactement le parcours prévu, dans le bon ordre et avec les bons acteurs : l'admin consigne le passage oublié à 22:58, puis Bouaké réceptionne **41 secondes plus tard** — alors que la même réception était refusée juste avant. Les passages en base le confirment : Adjamé (départ 16:52), Yamoussoukro (22:58), Bouaké (23:00), Korhogo encore vide, position courante à Bouaké.

J'ai quand même fermé la dernière boucle, en lecture seule — un second rattrapage sur Yamoussoukro :

```
HTTP 400
« Le passage à Gare de Yamoussoukro est déjà consigné (21/09/2026 22:58) :
  il n'y a rien à rattraper »
```

L'idempotence tient : on ne réécrit jamais un horodatage posé.

**Ce que je n'ai donc pas relancé, et pourquoi** : le voyage 40 n'a plus de trou dans sa chaîne. Rejouer le rattrapage se ferait refuser (ci-dessus), et re-réceptionner Bouaké passerait mais n'écrirait qu'une ligne de journal en double, sans rien démontrer. Le seul arrêt restant est le terminus, qui relève de la clôture.

Du coup le contrôle visuel du dépliant n'est plus possible sur ce voyage : il ne s'affiche que sur un arrêt intermédiaire **sans** arrivée, et il n'y en a plus. Si tu veux le voir, il faut un départ qui présente le trou — soit un nouveau voyage sur la ligne Adjamé → Korhogo démarré sans pointer Yamoussoukro, soit tu attends le prochain cas réel. Dis-moi si tu veux que j'en prépare un.

J'ai arrêté les serveurs de test (8200 et 8300) et nettoyé les jetons.

**Un détail que j'ai noté au passage** : le rattrapage n'a pas pu démontrer qu'il laisse les colis tranquilles — Yamoussoukro n'avait ni courrier ni bagage à descendre sur ce voyage. C'est couvert par un test, mais pas observé en vrai.










Feu vert. Je le crée par l'API, avec les vraies règles — pas par insertion SQL.

Le voyage est en route avec le trou. Je vérifie que la garde se déclenche et que l'état est bien celui voulu.

Le voyage de démonstration est prêt. Je relance le frontend et je regarde si une session subsiste.

La session tient. Voici la frise du voyage préparé :

L'interface est exactement celle prévue. Un détail de mon jeu de test fausse la lecture (« -385 min ») : j'ai daté le départ prévu à 06:00 le lendemain. Je corrige.

Le bouton « Consigner » est dans le DOM mais je ne le vois pas rendu. Je vérifie ses styles calculés.

Vrai défaut trouvé : `bg-amber-600` calcule en **transparent** — texte blanc sur fond transparent, bouton invisible. La classe n'existe pas dans le CSS compilé : je n'ai pas rebâti les assets après avoir écrit ce gabarit.

La page chargée dans le navigateur date d'avant le rebuild. Je recharge et je revérifie.

Le voyage de démonstration est prêt et l'interface se comporte comme prévu.

## Ce que j'ai créé

**Voyage 41 — `LI-ABI-KOR-0001-V38`**, ligne Adjamé → Yamoussoukro → Bouaké → Korhogo, car `4521 AB 01` (60 places), parti à 23:35 pour un départ prévu à 23:00.

Tout est passé par l'API avec les vraies règles, jamais par insertion SQL :

- un **billet** Adjamé → Yamoussoukro (`…-TCK-2026-1`, siège 12, 5 000 F) ;
- un **bagage** qui descend à Yamoussoukro (`BAG-2026-69`, 14 kg, 2 500 F) → `EMBARQUE` ;
- un **courrier** à destination de Yamoussoukro (`CRR-2026-7`) → `EN_TRANSIT` ;
- le voyage démarré, **et Yamoussoukro volontairement non pointée**.

Le bagage et le courrier sont là exprès : ils permettront d'observer en vrai que le rattrapage **ne les livre pas** — le seul point que les tests couvraient sans qu'on puisse le voir.

## Ce que ça donne

L'API refuse bien la réception de Bouaké :

> « Le car ne peut pas être arrivé à Gare de Bouaké sans être passé par Gare de Yamoussoukro, qui n'a pas encore été pointée. Faites-la réceptionner, ou demandez à un administrateur de rattraper ce passage. »

Et sur la frise, connecté en admin :

- **Adjamé** — `DÉPART` · `CAR ICI` · `+35 min` · départ 23:35
- **Yamoussoukro** — *Passage non pointé — rattraper*, dépliant ouvert avec l'heure réelle pré-remplie et le bouton **Consigner**
- **Bouaké** — même dépliant, replié
- **Korhogo** — `TERMINUS`, **aucun dépliant** : le terminus relève de la clôture, pas du rattrapage

## Deux choses à te signaler

**J'ai cru trouver un bug, c'en était un de ma part** : le bouton « Consigner » s'affichait en blanc sur transparent. J'avais oublié de rebâtir les assets après avoir écrit le gabarit — la classe `bg-amber-600` n'existait pas encore dans le CSS compilé. Après `npm run build` et rechargement, le bouton est bien en ambre (`rgb(218,119,0)`). Rien à corriger dans le code, mais **le build est nécessaire** avant de déployer ce changement.

**J'ai ajusté l'heure de départ prévue** du voyage 41 après coup (de 06:00 le 22 à 23:00 le 21) : mon premier choix affichait « -385 min », un car en avance de plus de six heures, ce qui rendait la frise illisible. C'est une écriture supplémentaire sur ce voyage de démonstration, pas sur tes données existantes.

---

Les serveurs tournent toujours (front `http://127.0.0.1:8300`, API 8200). Tu peux cliquer « Consigner » toi-même pour voir la suite : Bouaké devient réceptionnable, et le bagage comme le courrier de Yamoussoukro doivent rester `EMBARQUE` et `EN_TRANSIT`. Dis-moi si tu veux que je le déroule, ou que je supprime ce voyage de démonstration une fois que tu l'auras vu.





## Travail en cours (non commité)

Tout tourne autour du **dernier chantier : la vente hors ligne du commercial**, plus un démarrage de PWA.

**Backend** — garde anti-collision de code dans [SynchronisationHorsLigneService.php:175](Backend-Transport/src/Domain/Service/SynchronisationHorsLigneService.php:175) : un code `B1` déjà pris est refusé *avant* l'écriture (sinon la violation d'index ferme l'EntityManager, annule le lot entier et bloque la file pour toujours). Même garde pour le bagage, par entreprise. Test `unCodeDejaPrisNeBloquePasLaFile` ajouté.

**commercialflutter** — nouveau [vidange_automatique.dart](commercialflutter/lib/core/offline/vidange_automatique.dart) : la file repart seule sur 4 déclencheurs (ouverture SQLite, connectivité, retour au premier plan, retentative 2 min tant que la file n'est pas vide — le cas « antenne connectée sans débit »). Monté à la racine dans `app.dart`. Et `CodesHorsLigne` prend désormais le **max(file locale, instantané serveur)** pour que le compteur « B » ne reparte pas à 1 après réinstallation.