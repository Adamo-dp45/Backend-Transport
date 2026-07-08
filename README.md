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
        > Hiérarchie de gestion des comptes (via `UserManagementGuard`) : nul ne se gère soi-même (profil dédié) ; fondateur & admins entreprise gérés uniquement par le super admin ; un agent rattaché à une gare ne gère que les utilisateurs simples de SA gare (jamais un admin de gare) ; un utilisateur central sans gare gère tout le monde sauf les admins

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

    > Le module `Billetterie` : Ticket
        > Émission des tickets PAR TRONÇON : un ticket a une gare de montée (`gare`) et une gare de descente (`garedescente`), toutes deux arrêts de la ligne du voyage (descente après montée)
        > Calcul automatique du montant via la grille `Tarif` GLOBALE (montée → descente)
        > La gare de montée est FORCÉE à la gare de l'agent (un agent ne vend qu'au départ de sa gare) ; la gare de destination (terminus) ne peut pas vendre
        > Capacité PAR TRONÇON : un même siège peut être revendu sur des tronçons disjoints d'un voyage, avec priorité à la gare AMONT (une vente d'une gare en aval ne grise/bloque pas l'amont)
        > Suivi du nombre de places vendues et de la recette par voyage
        > Si on annule un ticket on décrémente les places occupées du voyage

    > Le module `Courrier` : Tarifcourrier, Courrier, Detailcourrier
        > Pour calculer la taxe d'un colis `Detailcourrier` on se base sur valeur, à la création on cherche le `TarifCourrier` dont `valeur_min <= valeur <= valeur_max` et on affecte son `montanttaxe` ou `montant` du colis
        > Gares & voyage : si un voyage est affecté, `garedepart`/`garearrivee` doivent être des arrêts de SA ligne (départ avant arrivée) et la gare de départ est forcée à la gare de l'agent. Sans voyage, le courrier reste `EN_ATTENTE` (gares nulles, affectées plus tard)
        > On a géré le tarif des colis via un système de `grille tarifaire` ou tranches `10 001 - 50 000 FCFA → taxe fixe 3 000` et on peut le faire aussi avec le poids du colis `k`
        > !! que le `statut` du courrier suit automatiquement le voyage on a `VoyageClotureStautSubscriber` qui gère la transition `EN_TRANSIT → RECEPTIONNE` qui correspond à l'accusé de réception à la gare d'arrivée qui confirme l'arrivée des colis
        > !! la transition du statut `RECEPTIONNE → LIVRE` qui correspond à la remise au destinataire avec potentiellement un paiement, c'est l'agent de la gare d'arrivée qui confirme la remise au destinataire via l'endpoint `../livrer`
        > !! le paiement de la taxe on a 2 types, à l'envoi ou à la reception du courrier
        > La recette totale du courrier se base sur le mode de paiement

    > Le module `Bagage` : Tarifbagage, Bagage
        > On a 2 façon de faire
            > Le modèle `A` déclaration à l'achat qui permet au client de déclarer ses bagages en achetant son ticket de voyage. Le prix est calculé et inclus immédiatement
            > !! `B` facturation au chargement qui au chargement du car les bagages du client sont pesés physiquement et un ticket de pesée séparé est émis qui est un reçu distinct du ticket de voyage qui documente le poids, la nature et le coût des bagages.. et lie le bagage au client
        > Le tarif du bagage est basée sur le poids
        > Gares & voyage : le bagage circule sur un voyage ; sa gare de descente (`garedescente`) est un arrêt de la ligne, après sa gare d'origine (`garedepart`, forcée à la gare de l'agent). La livraison (`EMBARQUE → LIVRE`) se fait quand la gare intermédiaire de descente réceptionne le voyage, ou à la clôture au terminus
        > Pour gérer l'automatisation du statut du bagage on a `VoyageClotureStautSubscriber` qui écoute les changements sur `Voyage` et va causer un soucis si on a clôturé le voyage avant de déclarer que le bagage est perdu
        > La recette totale du bagage se base sur le moment ou le bagage est embarqué

    > Le module `Tableau de bord` & `Rapports`
        > Exploitation
            > Nombre de voyages par période
            > Taux de remplissage
            > Voyages par statut 
        > Financier
            > Recettes billetterie
            > Coût des dépannages
            > Coût approvisionnements
        > Stock
            > Stock actuel par pièce
            > Pièces critiques
            > Mouvements récents
        > Flotte
            > Véhicules les plus en panne
            > Véhicules par état
            > Coût de maintenance par véhicule

eager_loading:
    max_joins: 50 # Vu qu'une entité peut porter plus de 4 relations or 'ApiPlatform' a une limite de jointures de l'eager-loading '30' par défaut

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