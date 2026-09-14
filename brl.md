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
    > php bin/console doctrine:database:create
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
- Tirer les choses utile de la conversation `Claude` du desktop du travail
- La possibilités d'activer ou désactiver des modules dans l'application

Docker, Server, Sql Postgre(claude), Git(claude), ia, UI, Symfony








Moyen de paiement, on vas mettre plusieurs, mais on vas commencer par Stripe




php -S 10.0.2.2:8000 -t public
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000


scoop install ngrok
ngrok config add-authtoken $YOUR_AUTHTOKEN
ngrok http 80 --url https://proud-gauntlet-elongated.ngrok-free.dev

flutter build apk --release --dart-define=API_BASE_URL=https://proud-gauntlet-elongated.ngrok-free.dev









Le prix ne vient jamais de l'appareil. Le téléphone calcule pour encaisser, le serveur recalcule depuis la grille. L'écart est consigné, pas appliqué : le contrôle anti-fraude reste entier. --- Faire attention à la remise
Billet vendu 9 500 à bord  admin change tarif 	enregistré à 8 000 (grille), écart de 1 500 tracé au journal


Je comble le trou que l'essai a révélé : sans réseau après un redémarrage, la liste des voyages ne chargeait pas — le vendeur ne pouvait même plus atteindre l'écran de vente.

Trouvé au passage : le lot dispensait des permissions. Être le commercial du voyage est un bornage, pas un droit — un vendeur privé de CREER/Ticket l'obtenait en passant hors ligne. VENTE et BAGAGE sont désormais contrôlés opération par opération.

Deux réserves : la correction du séparateur orphelin (« Siège 10 · 8000 FCFA · ») est dans la dernière build mais je ne l'ai pas re-jouée à l'écran, la file ayant été vidée ; et android/app/src/debug/AndroidManifest.xml autorise le trafic en clair — variant debug uniquement, la production reste en HTTPS.








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

Le Mobile Money reste hors périmètre en attendant l'agrégateur
- Générer l'impression côté Backend et donner la possibilités au Frontend de choisir le modèle qu'il souhaite, donc on aura plusieurs formats de tickets :: Regarde d'autres endroit de l'application dans lequel il est logique de dédié toutes les tâches lourdes au backend, c'est qui est logique dans une architecture séparé
- La possibilités de coché tous les sièges pour vendre
- Quand tu regarde l'appli comment je pourrais écrire une explication ou guide pour les endroits éssenciel de l'application

- 
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

 




Salut Claude, j'ai développé une application de compagnie de transport multi-entreprises et multi-gares en architecture séparé
    > Backend : Symfony, ApiPlatform, LexikJwtBundle, refreshTokenBundle
    > Frontend : Symfony, Twig, React UX, Shadcn, tailwind v4..

Aussi j'ai listé quelques informations sur les modules de l'application dans le README.md du Backend-Transport

Ignore les fichiers brl.md des 2 projets, ce sont juste des brouillons

Analyse les 2 projets pour bien pour comprendre






Salut Claude, dans la session précédente on a travailler sur l'application de compagnie de transport multi-entreprises et multi-gares en architecture séparé
    > Backend : Symfony, ApiPlatform, LexikJwtBundle, refreshTokenBundle
    > Frontend : Symfony, Twig, React UX, Shadcn, tailwind v4..

Je t'avais dit d'ignorer les fichiers brl.md des 2 projets vu que ce sont juste des brouillons

Aussi je t'avais dit qu'il y'a quelques informations sur les modules de l'application dans le README.md du Backend-Transport que tu mettais à jour régulièrement

Aussi analyse les 2 projets pour bien pour comprendre et te mettre à jour, aussi les applications mobile pour la partie réservation












Aussi je t'avais dit qu'il y'a quelques informations sur les modules de l'application dans le README.md du Backend-Transport que tu mettais à jour régulièrement




Salut Claude Code,

Dans la session précédente, on a travaillé sur l'application de compagnie de transport multi-entreprises et multi-gares, en architecture séparée (backend/frontend) :

## Stack technique
- **Backend** : Symfony, API Platform, LexikJWTAuthenticationBundle, RefreshTokenBundle
- **Frontend** : Symfony, Twig, React (UX), Shadcn/ui, Tailwind v4

## Rappel
- **Ignore les fichiers `brl.md`** dans les deux projets : ce sont de simples brouillons, sans valeur pour l'analyse (comme indiqué précédemment).

## Ce que j'attends
Comme le contexte de la session précédente n'est plus disponible, analyse à nouveau les deux projets (backend et frontend) afin de :
- te remettre à jour sur l'architecture générale et les modules existants,
- retrouver l'état d'avancement du projet,
- être prêt pour la suite des instructions que je vais te donner.

**Remarques :**
- J'ai ajouté "comme le contexte de la session précédente n'est plus disponible" pour que Claude Code comprenne bien *pourquoi* il doit refaire cette analyse (sinon il pourrait supposer à tort qu'il a un souvenir persistant).
- Si vous avez un fichier de notes/résumé de la session précédente (changelog, `NOTES.md`, etc.), c'est le bon moment pour le mentionner explicitement — ça évite de tout ré-analyser depuis zéro.
- Voulez-vous que j'ajoute une section pour préciser sur quoi portera la suite du travail (nouvelle fonctionnalité, bugfix, etc.), ou gardez-vous ça pour un prompt séparé ?












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

- - 
- Dépenses : 2 types (Dépense générale et gare)
    Objetdepense -> libelle          Objetdepensegare..
    Depense                          Depensegare..
        objetdepense -> vers Objetdepense
        date
        montant
        detail


























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