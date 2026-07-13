### BK-Transport

- Application de compagnie de transport mutli-entreprise en architecture séparé
    > Backend - Symfony, ApiPlatform, LexikJwtBundle, refreshTokenBundle

- **Imprtant**
    > Les explications des options `ApiPlatform` utilisés sont dans l'entité `User` et `Typepiece`
    > On a utiliser l'authentification via le `jwt`
    > !! géré le filtre du `identreprise` dans `EntrepriseScopeExtension` via `EntrepriseOwnedInterface`
    > Pour empêcher la suppression en `softDelete` lorsqu'un enregistrement est déjà lié à un autre mais ne fonctionne que sur `OneToMany` on n'a `HasSoftDeleteGuard` et `SoftDeleteProcessor`
    > !! la récupération des données si on veut utiliser un `provider` tout en profitant de la gestion des filtres, pagination, tri, extensions.. native de `ApiPlatform` on a `InventaireProvider` dans lequel on s'est brancher au pipeline au lieu de le remplacer
    > !! bloquer la connexion aux utilisateurs suspendus on a `UserChecker`, vu que le `checker` ne bloque pas un utilisateur suspendu qui a déjà un `jwt token` valide ou qui est connecté ce qui lui permet de faire des requêtes api après avoir été suspendu pour ça on utilisé l'évènement `lexik_jwt_authentication.on_jwt_authenticated` de `lexikBundle` qui se déclanche quand un `jwt` valide est présenté sur une requête, dans `JWTSubscriber` va vérifié à chaque requête authentifiée si il y'a un `jwt` existant pas sur toutes les requêtes
        > !! une entreprise désactivée on a fais la vérification dans `UserChecker` et `JWTSubscriber` pour bloquer ses utilisateurs
        > !! gare..
    > !! éviter d'hydratée une collection pour avoir le nombre dans le partie `frontend` on a crée des `get..` dans les entités concernés qui renvoi le `->count()` dans lequel `Doctrine` fait un `COUNT` sql et non un `SELECT *`, mais si la collection est très lourdes alors on doit passer par un repository via `DQL COUNT`
    > !! récupérer la collection de `personnel` pour `voyages` et `depannages` on n'a un cas différent vu qu'il ne sont pas directement liées mais via `Detailpersonnel`, on a 3 approches
        > Sol 1 : On crée un filtre personnalisé `PersonnelFilter` qui indique comment récupérer les données selon un param `personnel.id` dans l'url et on l'applique sur `Voyage` et `Depannage` ce qui permet de récupérer les voyages et dépannages d'un personnel tout en profitant de la pagination, filtre, tri..
        > Sol 2 : !! met une `ApiResource` avec l'endpoint `GetCollection` sur `Detailpersonnel`, on applique les filtres `personnel.id`, `voyage.id` et `depannage.id` et pour ne charger que les `detailpersonnels` liés à un voyage sans les dépannages on ajoute `ExistsFilter` sur `'voyage', 'depannage'` puis dans la requête du voyage on met `'exists[voyage]': 'true'` et pour dépannage `'exists[depannage]': 'true'`
        > Sol 3 : !! peut créer un provider personnalisé qui fais la requêtes en faisant un join et prendre en compte la pagination, filtre, tri..
    > !! éviter d'avoir une erreur à cause des données que j'envoi au select comme `typepiece`, `marquepiece`.. lorsqu'on donne la permission à utilisateur de voir les `piece` par ex et qu'il accède à la page de listing des pièces, on a `or is_granted('ROLE_USER')` sur le `getCollection` des entités ou.. créer un endpoint pour les select
    > !! un bypass `ROLE_ADMIN_GARE` pour éviter de lui donner des rôles manuellement et on.. son périmètre via les extensions et processors.. pour qu'il puisse gérer les rôles on.. lui donner des permissions explicite `Role_` ou mettre `Role` dans le bypass de l'administrateur de gare

- **Les modules**
    > Périmètre des données par gare (via `GareScopeExtension`, actif pour un agent rattaché à une gare et non-admin ; les admins entreprise/super et les utilisateurs centraux sans gare voient tout)
        > Données entreprise (toutes les gares) : Gare, Ligne, Tarifs, Car, Personnel, Dépannage, stock, référentiels
        > Données partagées le long d'une ligne : Voyage (dont la ligne dessert sa gare), Ticket (idem via le voyage), Courrier & Bagage (gare de départ OU d'arrivée = sa gare)
        > Données propres à la gare : les utilisateurs de sa gare

    > Le module `Administration` : Entreprise, User, Role, Permission, UserRole
        > Gestion des comptes utilisateurs et de l'entreprise
        > Gestion et attribution des rôles
        > Gestion des permissions RBAC
        > Hiérarchie de gestion des comptes (via `UserManagementGuard`) : nul ne se gère soi-même (profil dédié) ; fondateur & admins entreprise gérés uniquement par le super admin ; un agent rattaché à une gare ne gère que les utilisateurs simples de SA gare (jamais un admin de gare) ; un utilisateur central sans gare gère tout le monde sauf les admins ; le super admin est protégé (ni suspension ni modification, même par un autre super admin)

    > Le module `Système` (super admin) : Maintenance
        > Mode maintenance GLOBAL de la plateforme (singleton, hors périmètre entreprise) piloté par le super admin : quand il est actif, seul le super admin accède à l'application ; tous les autres voient une page de maintenance
        > Verrou DUR côté interface FT ET côté API (BK) via `MaintenanceSubscriber` (kernel.request) : les requêtes non-super-admin renvoient 503, sauf whitelist (login/refresh/logout, `/api/me`, lecture de l'état) ; fail-open si l'état ne peut pas être lu (on n'enferme jamais à cause d'un hoquet)

    > Le module `Personnel` ou `RH` : Typepersonnel, Personnel, Detailpersonnel
        > Gestion des employés de la compagnie
        > Affectation d'un personnel à un voyage ou depannage via detail personnel
        > Historique des affectations avec les detail du personnel

    > Le module `Gestion de stock` & `Approvisionnement` : Typepiece, Marquepiece, Model, Fournisseur, Piece, Approvisionnement, Detailapprovisionnement, Inventaire
        > Gestion des des pièces détachées
        > Gestion des fournisseurs
        > Approvisionnement : Entrée des pièces en stock ou enregistrer un achat de pièces
            > On crée un approvisionnement et ses details approvisionnements ce qui génère un mouvement `ENTREE` dans `Inventaire` et met à jour le stock automatiquement
        > Dépannage : Sortie de stock.. voir module flotte
            > !! dépannage qui génère un mouvement `SORTIE` dans `Inventaire` et met à jour le stock automatiquement
        > Ajustement manuel pour corriger le stock et génère un mouvement `AJUSTEMENT` dans `Inventaire` et les inventaires sont en lecture seule `getCollection` et `get`
        > Alertes stock faible
        > Inventaire : Suivi de stock actuel et historique des mouvements

    > Le module `Flotte` & `Maintenance` : Marque, Car, Depannage, DetailDepannage
        > Gestion des cars
        > On crée un dépannage ce qui ajoute des détails dépannage et génère un mouvement `SORTIE` dans `Inventaire` et met à jour le stock
        > Affecter un personnel à un détail dépannage ex: mécaniciens
        > Historique des maintenances par véhicule

    > Le module `Exploitation` : Gare, Ligne, Arret, Tarif, Voyage
        > Gestion des gares
        > Une `Ligne` est un itinéraire ordonné d'arrêts (`Arret` = Gare + ordre) : 1er arrêt = origine, dernier = terminus, les autres sont intermédiaires. (Remplace l'ancien `Trajet`)
        > La grille `Tarif` est GLOBALE par entreprise : un prix par couple de gares (garedepart → garearrivee), saisi une seule fois et partagé par toutes les lignes, avec création automatique du sens inverse au même montant. (Remplace l'ancien tarif par ligne `TarifLigne`)
        > Un `Voyage` est une instance d'une `Ligne` à une date donnée (provenance/destination dérivées de la ligne)
            > Affecter un car disponible et du personnel via détail personnel à un voyage
            > Gérer horaires de départ et d'arrivée
        > Droits par position de gare sur le voyage (via `VoyageGuard`)
            > Préparation (créer, modifier, affecter car/personnel, supprimer) : toute gare SAUF la destination, ou un admin
            > Clôture : uniquement la gare de DESTINATION (terminus) ou un admin ; un voyage clôturé n'est plus modifiable ni affectable
            > Réception `/voyages/{id}/receptionner` : uniquement une gare INTERMÉDIAIRE (ni provenance ni terminus) ; bascule automatiquement les courriers (`EN_TRANSIT → RECEPTIONNE`) et bagages (`EMBARQUE → LIVRE`) qui y descendent
        > Suivi du statut voyage
        > Historique complet pour reporting ou voyages par ligne et véhicule
        > Impression de bordereau qui est un document qui résume toutes les ventes de tickets d'un voyage dans une gare donnée, donc on a `Ticket` ManyToOne `Gare`
            > Le bordereau de gare qui est un document filtré par gare d'émission et liste les tickets vendus depuis une gare spécifique pour un voyage destiné au chef de gare qui fait le bilan de sa caisse..
            > !! chauffeur qui est un document global pour le voyage entier, sans filtre de gare et liste tout ce que le chauffeur transporte comme tous les tickets, tous les courriers embarqués sur et tous les bagages embarqués sur ce voyage remis à la gare d'arrivée
        > Si on peut annuler un voyage alors le car devient disponile et les places remboursées
        > Commercial à bord (vendeur mobile) : un `Voyage` peut porter un `commercial` (User) + une `garecourante` qui AVANCE au fil du trajet (réception + auto-avance) ; il vend depuis la position réelle du car et dispose d'un espace dédié `/mon-espace`
        > Départ partiel : une gare intermédiaire peut démarrer un voyage depuis SA gare (provenance effective = elle) ; ventes et réservations sont alors bornées à cette provenance et au-delà

    > Le module `Billetterie` : Ticket, Client, Beneficiaire
        > Émission PAR TRONÇON : gare de montée (`gare`) + descente (`garedescente`), toutes deux arrêts de la ligne (descente après montée) ; montant via la grille `Tarif` GLOBALE ; gare de montée FORCÉE à la gare de l'agent (le terminus ne vend pas)
        > Capacité PAR TRONÇON avec PRIORITÉ À LA GARE AMONT : un même siège peut être revendu sur des tronçons disjoints (une vente d'une gare en aval ne bloque pas l'amont) ; siège libéré en route (`garedescentereelle` + `/tickets/{id}/descendre`) pour revendre après une descente anticipée
        > Client : entité `Client` (téléphone = clé, find-or-create via `ClientResolver`, snapshotée sur le ticket)
        > Remise : `remisetype`/`remisevaleur` → `remise` (FCFA), plafond configurable (config remise dédiée `ConfigRemise`), bénéficiaire optionnel, audit anti-abus (`ActiviteLogger`)
        > Désistement `/tickets/{id}/desister` : REPORT (nouveau billet sur un autre voyage) ou ANNULATION (remboursement, motif OBLIGATOIRE) ; bloqués une fois le car passé à la gare de montée (intermédiaire-aware) ; les bagages liés sont annulés en cascade
        > Vendeur à bord : un ticket peut porter un `commercial` (snapshot) → sa recette revient à la GARE D'AFFECTATION du commercial, pas à la gare de montée

    > Le module `Réservation` : Reservation, ParametreReservation
        > Réserver une PLACE (pas un siège) à l'avance, en INVITÉ (sans compte) ; paiement Mobile Money SIMULÉ ; capacité prévisionnelle partagée avec la vente (`CapaciteService`)
        > Recette reconnue AU PAIEMENT (pas à l'émission), par gare de provenance ; le billet émis à partir de la réservation ne recompte pas (anti double-comptage : `t.reservation IS NULL` côté tickets)
        > Expiration via le cron `app:reservations:expirer` (avant le départ) → no-show `A_REGULARISER`
        > Régularisation d'un no-show : report sur un nouveau départ avec PÉNALITÉ (config par entreprise `ParametreReservation`) + complément tarifaire ; la pénalité, encaissée physiquement au guichet, est comptée en recette
        > API PUBLIQUE `/api/reservation/*` (multi-tenant via `?slug=`) consommée par les apps mobiles (Flutter + React Native)

    > Le module `Fidélité` : ProgrammeFidelite
        > Carte à tampons opt-in, sur le nombre de voyages ; état ENTIÈREMENT DÉRIVÉ de l'historique des billets (aucun compteur stocké → aucune dérive)
        > Récompense = remise sur un billet (`fideliteRecompense`), gardée à la vente (client membre + programme actif + récompense réellement acquise) ; auditée

    > Le module `Courrier` : Tarifcourrier, Courrier, Detailcourrier
        > Taxe par colis (`Detailcourrier`) via grille tarifaire (tranches de valeur `valeur_min <= valeur <= valeur_max → montanttaxe`)
        > Gares OBLIGATOIRES dès la création (`garedepart` forcée à la gare de l'agent, `garearrivee` saisie) ; VOYAGE optionnel (affecté après) ; s'il y a un voyage, les gares doivent être des arrêts de sa ligne (départ avant arrivée)
        > Statut suivant le voyage : `EN_TRANSIT → RECEPTIONNE` (réception à la gare intermédiaire, via `VoyageClotureStautSubscriber`) puis `RECEPTIONNE → LIVRE` (remise au destinataire via `../livrer`)
        > Annulation ET suppression gardées (seulement `EN_ATTENTE` + gare émettrice) et AUDITÉES (`COURRIER_ANNULE` / `COURRIER_SUPPRIME`)
        > Recette reconnue à la création ; peut être EXCLUE du chiffre d'affaires par entreprise (config recette `ConfigRecette.courriershorsca`)

    > Le module `Bagage` : Tarifbagage, Bagage
        > Bagage TOUJOURS lié à un billet : il SUIT le billet (voyage, gares, identité, canal de vente) ; tarif basé sur le POIDS
        > Le montant peut être FORCÉ (≠ tarif de la grille) — tracé + audit anti sous-déclaration (`BAGAGE_MONTANT_FORCE`, écarts par agent)
        > Recette reconnue dès `ENREGISTRE` ; livraison `EMBARQUE → LIVRE` à la réception de la gare intermédiaire de descente ou à la clôture au terminus
        > Annulation ET suppression gardées (seulement `ENREGISTRE` + gare de dépôt) et AUDITÉES (`BAGAGE_ANNULE` / `BAGAGE_SUPPRIME`)

    > Le module `Recette` (3 canaux) : source unique `RecetteGareService`
        > GUICHET (billets/bagages/courriers émis à la gare) + COMMERCIAL (ventes à bord → gare d'affectation du vendeur) + RÉSERVATION (payée, gare de provenance)
        > Config de composition du CA par entreprise : plafond de remise (`ConfigRemise`), courriers hors CA (`ConfigRecette`), pénalité de réservation (`ParametreReservation`)

    > Le module `Journal d'activité` & anti-fraude : Activite
        > `ActiviteLogger` trace les événements critiques (voyage, ticket annulé/reporté/supprimé/remisé, courrier & bagage annulé/supprimé/perdu, montant bagage forcé, récompense fidélité…) avec l'auteur et la cible
        > Détection : remises / annulations / suppressions / forçages par agent, taux d'annulation par agent, cartes de fidélité « captées » (« actions critiques par agent »)

    > Le module `Tableau de bord` & `Rapports`
        > Exploitation (voyages par période, taux de remplissage, par statut), Financier (recettes par CANAL, coûts dépannage/appro, bénéfice net), Stock, Flotte
        > Détails : par gare (`RecetteGareService`), par ligne, par commercial, départs effectifs, clients, fidélité, réservations, agents (encaissements + actions critiques), billetterie (désistements, remises, matrice Origine-Destination, heures de pointe)
        > Manifeste / feuille de route par voyage (occupation par tronçon + recettes par gare) ; bordereau de gare + bordereau chauffeur ; exports xlsx / PDF
        > Accès selon le rôle : admin entreprise = dashboard global ; agent / admin de gare = « Ma gare » ; utilisateur central sans gare = dashboard global SANS la partie financière (réservée à l'admin)

eager_loading:
    max_joins: 50 # Vu qu'une entité peut porter plus de 4 relations or 'ApiPlatform' a une limite de jointures de l'eager-loading '30' par défaut

- Le cron sur `app:reservations:expirer` pour la réservation vu que c'est lui qui matérialise le passage en à régulariser

- On a mis en place le force brute `security.yaml` - `login_throttling`, au delà l'authentification échoue avec `TooManyLoginAttemptsAuthenticationException`, avant même de vérifier le mot de passe. Ça marche bien malgré `stateless: true` : le comptage est stocké dans le **cache** (pool `cache.rate_limiter`), pas dans la session.
    > Pour configurer le statut HTTP renvoyé quand c'est bloqué dépend de ton `failure_handler` LexikJWT → il sort un **401** avec le message d'erreur de l'exception. Si tu veux un **429 (Too Many Requests)** propre, il faut un failure handler maison qui teste `instanceof TooManyLoginAttemptsAuthenticationException` ; sinon le 401 standard suffit.
    > pour ça on a créer un `LoginFailureHandler` pour Voir le message « bloqué » quand le limiter s'applique via `failure_handler` dans `security.yaml`


- **Git**
    > git push -u origin main

- **Production**
    > On peut désactiver la doc `ApiPlatform` dans `config/packages/api_platform.yaml`
    > On décomente la contrainte de l'url dans `ForgotPasswordInput`
    > La 1ère
        > git clone .. .
        > Pour le `.env..` on peut `cp .env .env.local` ou `composer dump-env prod` qui génère un fichier `.env.local.php` qui est plus optimisé
        > composer install --no-dev --optimize-autoloader
        > composer require symfony/apache-pack
            > On.. le code du `https`
        > php bin/console lexik:jwt:generate-keypair : Pour générer les clés jwt vu qu'ils ne sont pas versionné
        > php bin/console doctrine:migrations:migrate --no-interaction
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod
    > Les prochaines
        > git pull origin main
        > composer install --no-dev --optimize-autoloader
        > php bin/console doctrine:migrations:migrate --no-interaction
        > php bin/console cache:clear --env=prod
        > php bin/console cache:warmup --env=prod