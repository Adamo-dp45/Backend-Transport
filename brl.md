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
- Pour la simulation du paiement dans la partie réservation on vas utilisé Stripe pour simuler
- Concernant la notion du polling je me dis vente de ticket

- Le commercial doit pouvoir faire une remise, aussi la gestion des bagages.. à tester sans admin gare


Forcer montant bagage, ticket format, avancer curseur


Hors-scope pour l'instant : vente hors-ligne (file d'attente + resync) — à rediscuter, réel enjeu pour un vendeur qui perd le réseau en route.
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
- Géolocalisation pour le suivi des cars en temps réel
- Fais moi un système d'autocomplétion dans un formulaire de recherche sur plusieurs ressources dans une application Symfony
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


- Le cron sur `app:reservations:expirer` pour la réservation vu que c'est lui qui matérialise le passage en à régulariser
    > L'expiration via le cron `app:reservations:expirer` (avant le départ) → no-show `A_REGULARISER` :: Une place réservée est tenue jusqu'à ce délai avant le départ, puis libérée. 0 = jusqu'au départ. (120 = 2 h)
    > php bin/console app:reservations:expirer
        */5 * * * * cd /chemin/vers/BK-Transport && /usr/bin/php bin/console app:reservations:expirer --env=prod --no-interaction >> var/log/cron-reservations.log 2>&1
    > Ou 'loc..:8000/api/cron/reservations-expirer?token=' pour tester côter backend
    > Le cron qui ne s'exécute pas sur l'hébergeur => C'est un grand classique de l'hébergement mutualisé (mauvais binaire PHP, mauvais `APP_ENV`, ou host qui ne propose qu'un cron **par URL** et pas en ligne de commande). Je regarde la sécurité pour te proposer une solution robuste (un endpoint HTTP déclenchable + du log pour vérifier).


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