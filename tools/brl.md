### Brl 2

## New 3

## Les modules et leurs invariants
Administration (RBAC, hiérarchie `UserManagementGuard`, inscription réservée au super admin) · Système (maintenance globale, corbeille 27 types auto-découverts) · RH · Stock & appro · Flotte · **Exploitation** (lignes = arrêts ordonnés, durées par tronçon + recalage par médiane, `Passage` avec retards dérivés, droits par position « l'origine prépare · l'intermédiaire réceptionne · le terminus clôture ») · **Billetterie** (émission par tronçon, **priorité absolue à la gare amont**, on compte des **sièges** pas des passagers, surbooking assumé, éviction dérivée + `venduAval` en prévention) · Réservation (deux délais, hold qui se libère seul, repêchage à la replanification) · Fidélité · Courrier · Bagage · Recette 3 canaux · Journal d'activité · Alertes (12 types, balayeur idempotent) · Tableau de bord & rapports (ponctualité, qualité de la donnée, ETA).

## Les deux derniers chantiers livrés
- **Vente hors ligne du commercial** — `GET /me/instantane` (armement) + `POST /me/sync` (premier endpoint acceptant un lot), idempotence par `referenceOffline`, codes série « B » générés par le téléphone, trois relâchements seulement, prix toujours arbitré par le serveur avec écart consigné. Les règles sont **rejouées** côté Dart, pas seulement affichées (`regles_rejouees_test.dart`).
- **Garde d'ordre des réceptions** — une gare ne réceptionne plus par-dessus un arrêt non pointé, avec le rattrapage admin `PATCH /rattraper-passage` comme seule porte de sortie (il consigne un passage, il ne réceptionne pas). Verrouillé par `ReceptionOrdreTest`.









On vas mettre en place la notion de départ dans la partie voyage !


Avant de partir dans une direction : « départ » a déjà plusieurs sens dans le code (`datedepartprevue`/`datedepartreelle`, le départ partiel d'une gare intermédiaire, l'action `/repartir`). Je veux être sûr de ce que tu veux ajouter.

Qu'entends-tu par « la notion de départ » dans la partie voyage ?

ça tend vers le 1, vu que sur des tickets de voyage je vois souvent "Départ 1" ou "Départ 2" etc... ! mais ici je me dis qu'on procédera d'une façon auto en faisant l'incrémentation par jour, etc... vu qu'on aura plusieurs départs dans une journée ! Tu vois où je veux en venir !



Je vois exactement : un **numéro de départ** sur le voyage (« Départ 1 », « Départ 2 »…), attribué automatiquement et remis à zéro chaque jour. Je regarde d'abord comment les voyages sont créés et codés aujourd'hui, pour brancher ça au bon endroit.

Deux décisions à trancher avant de coder — elles changent le code et la migration.


« Départ 2 », c'est le deuxième départ de quoi exactement ?

Mon idée tend vers le 3, mais explique moi mieux celui du 1 (aussi je veux comprendre aussi si le Abidjan->Bouaké est une ligne aussi dans ce cas !)

Comment le numéro est-il attribué quand les voyages du jour ne sont pas créés dans l'ordre des heures ?

Figé à la création (Recommandé)



Bonne question — et la réponse tient au modèle. Je vérifie sur tes données réelles.

## Abidjan → Bouaké, c'est une ligne ?

Dans ton modèle actuel, **non**. Tu as la ligne `LI-ABI-KOR-0001` « Abidjan → Korhogo », qui dessert quatre arrêts ordonnés : Abidjan (origine) · Yamoussoukro · Bouaké · Korhogo (terminus). Abidjan → Bouaké est un **tronçon** de cette ligne : le billet s'émet par tronçon (montée + descente) et le prix vient de la grille `Tarif` globale — 8 000 F Abidjan→Bouaké, 15 000 F Abidjan→Korhogo.

Ça *pourrait* être une ligne, si la compagnie exploitait un car qui termine à Bouaké : il suffirait de la créer avec ses arrêts. Rien ne l'interdit. Mais un car Abidjan→Korhogo transportant 40 passagers dont 10 descendent à Bouaké, c'est **un seul voyage, un seul départ**.

Conséquence : le voyage porte une ligne, jamais un couple origine-destination.

## Option 1 vs option 3

Elles ne diffèrent **que sur le départ partiel**. Le reste du temps elles donnent le même numéro, puisqu'un voyage normal part toujours de l'origine de sa ligne.

Mardi, sur la ligne Abidjan → Korhogo :

| Heure | Qui lance | Option 1 (ligne + gare) | Option 3 (ligne seule) |
|---|---|---|---|
| 7h | Abidjan | Départ 1 | Départ 1 |
| 9h | Abidjan | Départ 2 | Départ 2 |
| 14h | **Bouaké** (départ partiel) | **Départ 1** (son propre compteur) | **Départ 3** |

Le seul arbitrage est là : à 14h, le passager de Bouaké voit partir le premier car de sa journée. L'option 1 le lui annonce « Départ 1 » ; l'option 3 lui dit « Départ 3 » — exact du point de vue du siège, déroutant au guichet de Bouaké. En échange, l'option 3 donne une suite unique et sans trou pour toute la ligne : 1, 2, 3 le mardi, ce qui se lit bien sur un tableau d'exploitation.

Si ta compagnie ne pratique pas les départs partiels, les deux se valent exactement.

Maintenant que le départ partiel est le seul cas qui les sépare, lequel tu retiens ?

Ligne + gare de départ, par jour (option 1)


Décisions actées : compteur par **ligne + gare de provenance + jour**, numéro **figé à la création**. Je regarde maintenant où ça se branche.

Le ticket de référence tranche le rendu : la case que ton gabarit remplit aujourd'hui avec le code voyage porte en réalité **« DÉPART 4 »**, à côté de « SIÈGE 29 ». C'est bien ce champ-là qu'on met en place.

Voici comment je compte m'y prendre.

## Modèle

Deux colonnes sur `Voyage` :

- **`numerodepart`** (int) — le numéro affiché, attribué à la création et figé.
- **`jourdepart`** (date) — le jour de `datedepartprevue`, maintenu dans le setter pour qu'il ne puisse jamais diverger.

`jourdepart` est un dérivé stocké, ce qui va contre la doctrine maison — mais c'est ce qui permet de déclarer l'index unique `(identreprise, ligne, gareprovenance, jourdepart, numerodepart)` **dans le mapping Doctrine**. C'est nécessaire : `make test-db` construit la base de test avec `doctrine:schema:update --complete`, donc un index posé en SQL brut dans la migration n'existerait pas en test. Sans index, deux créations simultanées produiraient deux « Départ 2 » en silence — exactement le défaut que tu as corrigé sur `codeticket`.

## Attribution

Un `NumeroDepartService` : `MAX(numerodepart) + 1` sur le groupe (entreprise, ligne, gare de provenance, jour), pris sous verrou pour sérialiser deux créations concurrentes. Appelé par `VoyageProcessor` au POST.

Un cas que ton choix « figé » laisse ouvert : **le voyage replanifié vers un autre jour**. Son numéro appartient à l'ancien jour ; le garder créerait un doublon avec le « Départ 2 » déjà ouvert le jour cible. Je réattribue donc le numéro quand le jour change (et seulement le jour — décaler de 7h à 9h ne touche à rien), avec une entrée au journal d'activité. Le billet déjà imprimé porte de toute façon une heure devenue fausse, le client doit être rappelé.

## Migration

Ajout nullable → back-remplissage des voyages existants (numérotés par groupe, dans l'ordre de `datedepartprevue` puis `id`) → passage NOT NULL → index unique. Avec la requête de contrôle des doublons en commentaire, comme tes migrations précédentes.

## Diffusion

Backend : groupes `read:Voyage` et `read:Ticket`, DTO publics de réservation, bordereaux de gare et chauffeur, manifeste, instantané du commercial (indispensable — le reçu s'imprime hors ligne), suivi de gare. Puis FT (impression + écrans), puis les trois apps mobiles.

Deux points à trancher avant que je code :


Sur le billet imprimé, la case porte aujourd'hui « VOYAGE / LI-ABI-KOR-0001-V3 ». Le ticket SKAE de référence, lui, porte « DÉPART / 4 ». On fait quoi ?

On vas mettre le départ à la place du code, puis mettre le code du voyage à place du véhicule (mais met le en commentaire au lieu de le supprimé)

Jusqu'où je vais dans la foulée ?

Backend d'abord, puis on voit (Recommandé)



Parfait. Je note pour le palier frontend : case **DÉPART** à la place du code, code voyage à la place de « Véhicule », ligne véhicule mise en commentaire — pas supprimée.

Je commence le backend.

Je modifie l'entité `Voyage`.

Maintenant le repository et le service d'attribution.

Maintenant le processor : attribution au POST, renumérotation si le jour change au PATCH.

Maintenant le `ScenarioBuilder` des tests, qui doit rejouer la vraie règle.

Le numéro est exposé par les groupes de l'entité. Restent les DTO et contrôleurs qui composent leur propre charge.

Les quatre contrôleurs qui composent leur charge à la main :

Maintenant le test qui verrouille la règle.

Les six passent. Je lance toute la suite pour vérifier qu'aucune règle existante ne bouge.

Tests au vert : 218 tests, 617 assertions. Je documente la notion dans le README.

Backend terminé. Reste à jouer la migration sur ta base de développement — une écriture, donc je te demande avant.

Ce qui a été fait :

| Fichier | Rôle |
|---|---|
| [Voyage.php](Backend-Transport/src/Entity/Voyage.php) | `numerodepart` + `jourdepart` (dérivé dans le setter), index unique dans le mapping |
| [NumeroDepartService.php](Backend-Transport/src/Domain/Service/NumeroDepartService.php) | l'attribution, avec le contrat « l'appelant tient le verrou » |
| [VoyageRepository.php](Backend-Transport/src/Repository/VoyageRepository.php) | `maxNumeroDepart`, qui compte **aussi les voyages supprimés** |
| [VoyageProcessor.php](Backend-Transport/src/State/VoyageProcessor.php) | attribution au POST sous verrou sur la ligne ; reprise du numéro si le jour change |
| [Version20260922110000.php](Backend-Transport/migrations/Version20260922110000.php) | colonnes, back-remplissage, NOT NULL, index |
| [NumeroDepartTest.php](Backend-Transport/tests/Api/NumeroDepartTest.php) | 6 cas, dont le numéro figé et la corbeille qui ne rend pas son numéro |

Exposé côté API : groupes de lecture du voyage (donc du billet), bordereaux de gare et chauffeur, manifeste, DTO publics de réservation, suivi de gare, espace commercial et **instantané hors ligne** — sans lui, le reçu imprimé à bord ne pourrait pas porter le « DÉPART 4 ».

Deux écarts que je te signale plutôt que de les cacher : `jourdepart` est un dérivé stocké (justifié au-dessus, et documenté dans l'entité comme dans le README), et je n'ai pas ajouté le numéro aux DTO de **statistiques** (`AgentDetailVoyageDto`, stats d'exploitation) — un numéro de départ sans sa journée n'y veut rien dire. Dis-moi si tu le veux quand même.

Je joue la migration sur ta base de développement ?

Oui, migre (Recommandé)


Migration jouée et vérifiée sur ta base.

```
36 voyages · tous « Départ 1 » · 0 jour incohérent
uniq_voyage_numerodepart : identreprise, ligne_id, gareprovenance_id, jourdepart, numerodepart (unique)
doctrine:schema:validate → in sync
218 tests, 617 assertions → OK
```

Tous tes voyages passent à « Départ 1 » parce qu'aucune journée ne porte deux départs sur une même ligne depuis une même gare — c'est le jeu de test, pas un défaut de la numérotation ; les six cas du test API couvrent les suites à plusieurs départs.

## Ce que ça donne concrètement

Abidjan ouvre son car de 7h → **Départ 1**. Elle en ouvre un second à midi → **Départ 2**. Bouaké lance son départ partiel à 14h → **Départ 1**, sa propre suite. Demain, Abidjan repart à **1**.

Si le départ de midi est mis en corbeille, le suivant prend **3** et non 2 : le voyage supprimé garde sa ligne en base, donc son numéro — c'est la leçon de `codeticket` appliquée d'emblée. Si un départ est replanifié au lendemain, il reprend un numéro dans la journée d'accueil et l'opération est tracée au journal (`VOYAGE_NUMERO_DEPART`).

## Pour la suite

Le palier frontend est prêt à démarrer, avec tes décisions notées : case **DÉPART** à la place du code voyage, code voyage à la place de « Véhicule », ligne véhicule **commentée**. À faire aussi côté FT : le nouveau type d'audit `VOYAGE_NUMERO_DEPART` n'est pas dans la table de couleurs de [activite.html.twig:34](Frontend-Transport/templates/home/activite.html.twig:34) — sans ça il tombera sur la couleur par défaut dans le journal.

Rien n'est commité : les cinq dépôts ont leurs modifications en attente, tu regardes d'abord si tu veux.












Les gabarits lintent, le TS compile, le build passe, 29 tests FT verts. Je veux voir le billet rendu — je monte une commande de diagnostic temporaire.

Frontend terminé. Le billet est dans le panneau d'aperçu — voici ce qu'il produit :

```
Ticket n° : LI-ABI-KOR-0001-V3-TCK-2026-7
Départ : 22/09/2026 - 07:00
              VOYAGE
             Abidjan
             Korhogo
   ┌──────────┐   ┌──────────┐
   │  DÉPART  │   │  SIÈGE   │
   │    4     │   │    29    │
   └──────────┘   └──────────┘
        TARIF : 15 000 FCFA
   Voyage : LI-ABI-KOR-0001-V3
```

Exactement la disposition du ticket SKAE. La ligne « Véhicule » est conservée en commentaire sur le billet **et** sur la souche, avec la raison écrite à côté.

## Où le numéro apparaît

**Là où l'on désigne un départ** : colonne « N° départ » du listing, badge sur la fiche voyage, les **trois sélecteurs** (vente, réservation, report d'un désistement — « Départ 2 · LI-ABI-KOR-0001-V3 »), bordereaux de gare et chauffeur, manifeste, fiches billet et réservation, contrôle d'un billet, bon de réservation, tableau de bord de gare, espace commercial, et le sous-titre des cinq pages d'action du voyage.

**Volontairement absent** des tableaux de statistiques (`detailParVoyage` de la page agent) : un numéro sans sa journée n'y veut rien dire — c'est la même raison qui m'avait fait laisser le DTO côté backend.

J'ai aussi déclaré `VOYAGE_NUMERO_DEPART` dans les **deux** tables de couleurs du journal (page activité et fiche voyage) : une table oubliée n'aurait rien cassé, l'événement serait juste tombé en gris.

## Vérifications

273 gabarits lintés, `tsc --noEmit` propre sur `assets/` (les deux erreurs restantes sont préexistantes et viennent de `node_modules`), 29 tests FT verts, assets reconstruits avec `npm run dev` — cohérent avec ton build actuel sans empreintes.

Le billet, je l'ai rendu pour de vrai via une commande de diagnostic temporaire, que j'ai supprimée après usage. Ce que je n'ai **pas** pu vérifier : les écrans dans un navigateur, parce qu'aucun serveur ne tourne (le FT attend l'API sur `localhost:8001`) et que je n'ai pas de compte pour m'y connecter. Si tu veux les voir :

```bash
cd Backend-Transport && php -S localhost:8001 -t public
```

26 fichiers modifiés hors `public/build`. Prochain palier quand tu veux : `commercialflutter` — le champ est déjà servi par `/me/instantane`, donc le reçu imprimé hors ligne pourra porter le numéro.










Avant de toucher au reçu bagage, je regarde les images de référence restantes.

Le troisième cliché est un reçu **courrier**, pas un bagage — aucune référence pour l'étiquette bagage. Je continue.

Reste `resanative` (Expo / React Native).

Les trois apps mobiles sont faites. Le chantier est bouclé sur les cinq dépôts.

## commercialflutter

Le reçu imprimé à bord est maintenant identique au thermique du web : la case face au siège porte **« DEPART 4 · SIEGE 29 »**, le code voyage a pris la ligne du véhicule, et l'ancienne ligne est conservée en commentaire — billet **et** souche, comme côté FT. La carte d'un départ et la barre de titre de sa fiche affichent « Départ 2 · LI-ABI-KOR-0001-V3 » via un `libelleDepart` dérivé sur le modèle.

J'ai ajouté une assertion au test backend de l'instantané : le numéro doit y voyager, sinon le reçu imprimé hors couverture ne le porterait jamais — un téléphone ne peut pas le recalculer, c'est un compteur de gare par jour.

Un cas que je signale : un cache de départs écrit par la version précédente n'a pas le numéro. Le reçu retombe alors sur « - » et le libellé sur le code seul, plutôt qu'un « Départ null ». Le premier chargement avec réseau le remplit.

**J'ai laissé l'étiquette bagage inchangée** : elle porte déjà une ligne « DEPART » qui désigne la *gare* de départ. Deux sens sous le même mot sur un ticket de 80 mm se liraient mal, et le code voyage y identifie déjà le départ. Si tu le veux quand même, il faudra renommer les lignes existantes en « GARE DEPART / GARE DESCENTE » — dis-le-moi.

## resaflutter et resanative

Symétriques. Dans la liste des départs, le numéro remplace le code voyage : le code est du vocabulaire d'exploitation, il ne dit rien au client, alors que « Départ 2 » est ce qu'on lui annoncera au guichet. Le bon gagne une ligne « N° de départ ». Repli sur le code si l'API ne sert pas le numéro.

## Vérifications

| Dépôt | Résultat |
|---|---|
| Backend | 218 tests, 618 assertions ✓ |
| Frontend | 29 tests ✓ · 273 gabarits lintés · `tsc` propre |
| commercialflutter | `flutter analyze` propre · 70 tests ✓ |
| resaflutter | `flutter analyze` propre · 4 tests ✓ |
| resanative | `tsc --noEmit` propre · eslint sans avertissement |

Un point d'environnement : `build_runner` échoue sur ce poste, Windows bloquant `gen_snapshot.exe` pour la compilation AOT. Le contournement est `--force-jit` — je l'ai noté dans le README de `commercialflutter` et en mémoire, tu retomberas dessus.

```bash
dart run build_runner build --delete-conflicting-outputs --force-jit
```

Rien n'est commité : 30 fichiers côté backend, 26 côté frontend, 14 + 9 + 4 sur les mobiles. Les deux écrans que je n'ai pas pu voir tourner restent les mêmes qu'hier — aucun serveur ni émulateur ne tourne ici.











On vas mettre en place la gestion des dépenses


Nouveau chantier. Je commence par explorer ce qui existe autour des flux financiers et le patron d'un module complet.

Deux constats de l'exploration, avant de te poser les bonnes questions :

- **Rien n'existe** pour l'argent qui sort : aucune entité, migration, route ni écran. Les seuls « coûts » modélisés sont `Depannage::$couttotal` et `Detailapprovisionnement::$couttotal`.
- Le bénéfice net se calcule à **un seul endroit**, en dur : [FinancierStatsProvider.php:59](Backend-Transport/src/State/FinancierStatsProvider.php:59) — `recettes − coutDepannages − coutApprovisionnements`. C'est le point d'entrée naturel pour un troisième poste.

Quatre décisions changent le modèle :

À quoi une dépense doit-elle pouvoir être rattachée ? (plusieurs réponses possibles)

On sais qu'une compagnie de transport gagne de l'argent avec (les tickets, courriers, etc...) et qu'elle dépense pour (carburant, maintenance, salaires, péages, pneus, imprévus, etc...) donc je me disais qu'on vas mettre en place le suivi des dépenses pour savoir si l'entreprise gagne réellement de l'argent ! donc, je me dis qu'on aura les dépenses de l'entreprise et aussi par gare !

Concernant le voyage, on allais mettre en place la notion de frais de route (c'est de l'argent que la compagnie donne au chauffeur et commercial pour le voyage en cas de dépenses, du genre ration équipage) !

Une dépense saisie compte-t-elle tout de suite, ou faut-il qu'un responsable la valide ?

Saisie directe

Comment articuler avec les coûts déjà calculés (dépannages et approvisionnements) ?

Ici quand tu dis Tout ramener aux dépenses que veux tu faire !

Que doit porter une dépense en plus du montant, de la date et du type ?

Mode de règlement (Recommandé), Justificatif (pièce jointe), Bénéficiaire / fournisseur


Je réponds d'abord à ta question sur « tout ramener aux dépenses ».

## Ce que ça voulait dire

Aujourd'hui, l'argent qui sort n'est nulle part dans une table de charges : quand tu enregistres un approvisionnement, le montant est calculé et stocké sur `Detailapprovisionnement.couttotal` ; quand tu clôtures un dépannage, sur `Depannage.couttotal`. Le bénéfice va lire ces deux totaux directement.

**Option A — troisième poste.** On crée `Depense` pour ce qui ne passe par aucun module : carburant, salaires, péage, pneus, loyer, imprévus. Le bénéfice devient `recettes − dépannages − approvisionnements − dépenses`. Les trois postes restent distincts à l'écran. Le seul risque est humain : un agent qui ressaisirait en dépense un achat de pièces déjà enregistré en approvisionnement compterait deux fois — ça se traite dans l'aide et par les types de dépense proposés.

**Option B — tout ramener.** Chaque approvisionnement et chaque dépannage écrirait *aussi* une ligne dans la table des dépenses, pour n'avoir qu'un seul endroit où lire ce qui sort. Le coût est lourd et durable : il faut maintenir la synchronisation dans les deux sens (annuler un appro doit annuler sa dépense, modifier un dépannage doit la corriger), migrer l'existant, et surtout retirer les anciens totaux du calcul du bénéfice — sinon le double comptage devient *technique*, donc invisible. C'est un chantier à part entière pour un gain de présentation.

**Il existe un entre-deux** que je te recommande : garder l'option A dans le modèle, et ajouter un écran « toutes les charges » qui agrège les trois sources **en lecture seule**. Tu as la vue unique sans la machinerie de synchronisation.

Sur les frais de route, j'ai besoin de savoir où tu veux les mettre.

Du coup, comment on articule avec les dépannages et approvisionnements ?

Je vais d'abord t'expliquer ce qu'on va faire

Les frais de route (ration équipage remise pour un voyage) : on les traite dans ce chantier ?

Je vais d'abord t'expliquer ce qu'on va faire

Un frais de route, dans ta compagnie, ça se passe comment ?

Forfait, point final











Concernant l'articulation avec les coûts déjà calculés on vas aller sur l'`Option A` (On crée `Depense` pour ce qui ne passe par aucun module : carburant, salaires, péage, pneus, loyer, imprévus. Le bénéfice devient recettes − dépannages − approvisionnements − dépenses...).

Concernant les frais de route, je me dis que ce n'est pas lié à ce chantier vu que c'est lié au voyage donc je me dis que la recette du voyage serait égal au ventes du voyage - les frais de route ! Si ce n'est la meilleur façon de procédé, tu me dis !

Pour ce que je voulais dire :
```
En discutant avec un autre développeur, il m'a fait savoir que je n'avais pas pris en compte la notion de caisse dans l'application !

Aussi, qu'on devrait avoir une caisse pour l'entreprise et pour la gare du genre (recette entreprise = caisse entreprise - depense entreprise, recette gare : caisse gare - depense gare) ! donc, je me dis aussi que ça rejoint un peu le `A1 - Clôture de caisse quotidienne par agent` de la note de cap **Feuille de route** !

Donc je me dis qu'en gros ça ferait une **Gestion des dépenses + caisse** !
```



Deux choses à te dire avant de poser mes dernières questions.

## Frais de route : attention au mot « recette »

Ta formule marche, mais elle mélange deux notions que le reste de l'application tient séparées. La **recette**, dans iTransport, c'est ce qui a été *encaissé* (`RecetteGareService`, trois canaux). Ce que tu décris — ventes moins frais de route — c'est le **résultat** du voyage. Si on appelle ça « recette du voyage », le jour où tu rapprocheras la caisse d'une gare, le chiffre ne tombera plus : l'agent a bien encaissé les ventes entières, les frais sont sortis par une autre porte.

Surtout, il y a un risque de double comptage. Les frais de route, c'est de l'argent qui sort de la caisse : si on les enregistre comme dépense **et** qu'on les déduit du voyage, ils pèsent deux fois sur le bénéfice de la compagnie.

Ma recommandation : **une seule écriture, deux lectures**. Le frais de route est une dépense, de type « frais de route », rattachée à un voyage. Le résultat du voyage se dérive alors tout seul (ventes − dépenses rattachées à ce voyage), et la dépense reste comptée une fois et une seule dans le bénéfice. Concrètement, ça veut dire prévoir dès maintenant un champ `voyage` optionnel sur la dépense — la migration est faite une fois, l'écran frais de route viendra quand tu voudras.

## Caisse : ta formule est à l'envers

« recette gare = caisse gare − dépense gare » : en réalité c'est la caisse qui est le reste, pas la recette.

```
solde de caisse = fonds de roulement
                + encaissements en espèces   (recette, déjà calculée aujourd'hui)
                − dépenses payées en espèces (ce qu'on va créer)
                − versements remis au siège
```

La recette, tu l'as déjà. Ce qui manque, c'est le **solde** et son rapprochement avec l'argent réellement dans le tiroir.

Jusqu'où va ce chantier ?

Je dirai le 1, mais est ce que ça nous permettra de savoir par exemple : Combien la gare a vendu aujourd'hui, etc...

Qui tient la caisse ?

L'agent, par session de poste (Recommandé)

La « caisse entreprise », c'est quoi au juste ?

Une vraie caisse du siège

Sur les frais de route, tu suis ma recommandation ?

Oui : dépense rattachée à un voyage (Recommandé)



Et une précision qui change le modèle : dans ton A1, la caisse se tient **par agent** — c'est lui qui a l'argent, lui qui compte à la fin de son poste, lui qui répond de l'écart. La gare et l'entreprise sont alors des agrégats, pas des tiroirs.

La réponse à ta question, d'abord : **oui, et ça existe déjà**. `GET /api/gares/me/dashboard?periode=jour` ([GareDashboardController.php:26](Backend-Transport/src/Controller/Api/GareDashboardController.php:26)) rend déjà, pour la gare de l'agent, la recette du jour ventilée par canal, avec le nombre de billets, courriers, bagages et réservations. Ce que le chantier ajoute, c'est l'autre moitié : **combien elle a dépensé**, donc ce qu'elle a réellement gagné.

Je fais concevoir l'implémentation.

Je vérifie les trois points du plan qui touchent du code partagé.

Les trois points sont confirmés dans le code. Deux décisions te reviennent.



Les dépenses du siège (loyer, salaires) : qui peut les voir ?

Admin + utilisateur central sans gare

Le justificatif accepte-t-il les PDF ?

Oui, j'assouplis MediaObject (Recommandé)



Je rédige le plan.

Le plan est prêt. Trois points méritent que tu les regardes en particulier :

- le **discriminant siège/gare** est simplement `gare` nulle, sans colonne de portée qui pourrait diverger — c'est déjà le choix fait pour `Role` ;
- la **limite du résultat par gare** : les dépannages et approvisionnements n'étant rattachés à aucune gare, je ne peux pas les y déduire. Je préfère l'afficher honnêtement (« résultat d'exploitation », pas « bénéfice », plus deux totaux qui se recoupent côté entreprise) plutôt que de sortir un chiffre qui ment ;
- le **test sentinelle** du bénéfice, qui tombera le jour où quelqu'un tenterait de dériver une dépense depuis un dépannage — c'est ce qui tient l'Option A dans le temps.

Tu as raison, et je vérifie : le projet a déjà un précédent.

Vérifié : le projet utilise déjà `bigint` à cinq endroits, avec exactement cette justification — `Depannage::$couttotal`, `Detailapprovisionnement::$couttotal`, `Courrier::$total`, `Detailcourrier`. Le patron maison est `#[ORM\Column(type: 'bigint')]` avec la propriété typée `?int` et le commentaire qui explique la limite. Je m'aligne dessus. Ma ligne était fausse sur `Depannage::$couttotal`, qui est déjà en bigint.

Corrigé : `montant` en **`bigint`**, propriété `?int`, avec le commentaire de justification comme les quatre autres montants du projet.

Plan approuvé. Je démarre le palier 1.

Je crée l'enum et le référentiel.

Maintenant l'entité `Depense`.

J'écris le `DepenseRepository` avec ses agrégats.

Maintenant le processor, qui porte les règles d'imputation.

Maintenant les constantes d'audit et l'assouplissement du `MediaObject`.

Schéma validé, base de test à jour. Je branche le bénéfice.

Maintenant le test qui écrit la règle.

Trois échecs instructifs. Je regarde les groupes de sérialisation des entités liées.

Deux de mes tests décrivaient mal le comportement réel. Je les corrige — et le commentaire du processor avec, parce qu'il affirmait quelque chose de faux.

11 tests au vert. Je vérifie que rien d'autre n'a bougé.

230 tests au vert. Je documente dans le README, qui est la source de vérité métier.

Palier 1 terminé. Voici l'état.

**Backend livré** — 230 tests, 657 assertions au vert, migration jouée, schéma en phase avec le mapping.

| Fichier | Rôle |
|---|---|
| [Depense.php](Backend-Transport/src/Entity/Depense.php) · [Typedepense.php](Backend-Transport/src/Entity/Typedepense.php) | le modèle, `gare` nulle = siège |
| [DepenseProcessor.php](Backend-Transport/src/State/DepenseProcessor.php) | imputation bornée, appartenance des références, audit |
| [DepenseRepository.php](Backend-Transport/src/Repository/DepenseRepository.php) · [DepenseGareService.php](Backend-Transport/src/Domain/Service/DepenseGareService.php) | les agrégats, une seule définition pour trois surfaces |
| [FinancierStatsProvider.php:59](Backend-Transport/src/State/FinancierStatsProvider.php:59) | le bénéfice, désormais à quatre termes |
| [GareDashboardController.php](Backend-Transport/src/Controller/Api/GareDashboardController.php) · [GareStatsController.php](Backend-Transport/src/Controller/Api/GareStatsController.php) | résultat par gare, et N vs N-1 |
| [DepenseStatsProvider.php](Backend-Transport/src/State/DepenseStatsProvider.php) | `/api/stats/depenses` : par poste, gare, mois, mode |
| [DepenseTest.php](Backend-Transport/tests/Api/DepenseTest.php) | 12 cas, dont la sentinelle du bénéfice |

**Deux choses que les tests m'ont apprises et que j'ai corrigées** plutôt que de forcer le code à leur donner raison :

- `gare: null` envoyé explicitement par un agent de gare n'est **pas refusé** : la dépense est ramenée à sa gare, exactement comme si le champ était absent. La garantie qui compte est tenue — il ne crée jamais de charge de siège — et distinguer « absent » de « null » aurait demandé de lire la charge brute pour un refus sans valeur.
- Une gare d'une autre compagnie est refusée **par ApiPlatform lui-même** (le périmètre s'applique à la résolution d'un IRI), avant d'atteindre mon processor. J'avais écrit le contraire dans un commentaire ; c'est rectifié — ma relecture est une ceinture, pas la bretelle.

Le README documente le module, y compris la limite du résultat par gare et le risque de lisibilité des justificatifs (`MediaObject` n'a pas d'`identreprise` — vrai depuis toujours pour les photos, plus sensible pour un bulletin de salaire).

Il reste à vérifier sur tes données, ce qui demande d'en avoir.


Comment on vérifie sur des données réelles ?

J'écris les fixtures et on recharge (Recommandé)



J'écris les fixtures.

Les fixtures sont prêtes. Je recharge la base — elle sera remise à plat comme tu l'as demandé.

Chargé. Je vérifie maintenant les chiffres bout en bout, par l'API.

Le calcul tombe juste au franc près. Mais il révèle autre chose : **IRA est déjà déficitaire dans le jeu de démonstration, avant mes dépenses** — 2,64 M de recettes contre 5,79 M d'approvisionnements et 1,94 M de dépannages. Mes montants, calibrés sur un vrai transporteur, écrasent un jeu dont les recettes ne sont pas à cette échelle. Je recalibre.

Il y a un bug : les dépenses du siège existent en base mais `totalSiege` renvoie 0.

C'est un défaut **préexistant** qui fausse toutes les statistiques, pas seulement les miennes : à 10h33, `first day of this month` vaut « 1er septembre à 10h33 », donc tout ce qui s'est passé le 1er avant l'heure courante sort de la période.

Le siège apparaît, et les recettes montent aussi — la correction récupère les opérations du 1er qui étaient perdues. Je vérifie que rien ne casse ailleurs.

Backend des dépenses livré et vérifié sur tes données.

## Ce que ça donne, en vrai

Tableau de bord du chef de gare d'Adjamé, mois en cours :

```
recette   1 145 400        dépenses    200 100  (41 lignes)
                             Carburant     163 300
                             Péage          30 800
                             Frais de route  6 000
RÉSULTAT    945 300        périmètre : DEPENSES_GARE
```

Et l'écran d'analyse, côté entreprise :

```
Adjamé        dépenses 205 100   recette 1 145 400   résultat   940 300
Bouaké        dépenses 194 300   recette   310 800   résultat   116 500
Korhogo       dépenses 170 750   recette         0   résultat  -170 750
Yamoussoukro  dépenses       0   recette   492 400   résultat   492 400
Siège         dépenses 310 000   recette         0   résultat  -310 000
```

Korhogo dépense sans encaisser et ressort en rouge : c'est exactement la question que tu voulais pouvoir poser. Le carburant pèse 84 % des postes.

## Deux choses que la vérification a révélées, et que je te dois

**J'ai corrigé un bug préexistant dans `PeriodeTrait`.** La période par défaut commençait à `first day of this month` **sans remettre l'heure à zéro** : à 10h33, elle valait « le 1er septembre à 10h33 », donc tout ce qui s'était passé le premier jour avant l'heure de consultation sortait du calcul. Ça touchait **toutes** les statistiques, recettes comprises — le même écran ne donnait pas le même chiffre le matin et le soir. La fin était bornée à 23:59:59, le début ne l'était pas. C'est ce qui faisait disparaître les salaires du siège de mes chiffres, d'où la découverte. Correction d'une ligne, 230 tests toujours verts, et les recettes d'Adjamé remontent de 46 000 F au passage.

**Le jeu de démonstration est déficitaire, et ce n'est pas le fait des dépenses.** IRA achète pour 5,8 M de pièces et 1,9 M de dépannages contre 1,9 M de recettes mensuelles : le bénéfice était déjà négatif avant ce chantier. J'ai calibré les dépenses sur l'échelle du jeu (550 000 F/mois, soit ~30 % des recettes) plutôt que sur celle d'un vrai transporteur, sinon le carburant seul aurait pesé cinq fois la recette et tous les écrans seraient devenus illisibles. C'est écrit dans les fixtures. Rééquilibrer le stock et la flotte serait un autre chantier — dis-moi si tu le veux.

## État

| | |
|---|---|
| Tests | 230, 657 assertions ✓ |
| Migration | `Version20260923090000` jouée, schéma en phase |
| Fixtures | rechargées — 147 dépenses, dont 2 rattachées à un voyage |
| Rôle | « Comptable » ajouté (portée entreprise) |

Le frontend est le prochain palier : CRUD, groupe de menu « Finances », page d'analyse, bloc résultat sur le tableau de bord de gare. Le plan liste trois pannes silencieuses à ne pas manquer là-bas — dont un `map()` Twig qui filtre les clés et laisserait le nouveau poste hors du graphique sans la moindre erreur.

Je t'écoute — vas-y.

Ce que j'ai déjà acté de tes réponses, pour ne pas te le refaire dire :

- les dépenses se suivent **au niveau entreprise et par gare**, pour savoir si la compagnie gagne réellement de l'argent ;
- **saisie directe**, sans circuit de validation ;
- une dépense porte, en plus du montant, de la date et du type : **mode de règlement**, **justificatif** en pièce jointe, **bénéficiaire/fournisseur** ;
- les frais de route sont un **forfait remis pour le voyage**, sans justification au retour.

Reste en suspens, et j'attends ton explication : l'articulation avec les coûts déjà calculés (dépannages, approvisionnements) et la place des frais de route dans ce chantier.











Frontend livré, et vérifié dans le navigateur plutôt que sur parole.

## Ce qui tourne

J'ai fait la boucle complète à l'écran, connecté en admin : saisie d'une dépense de 17 500 F imputée à Bouaké → `La dépense a été enregistrée avec succès`, la ligne apparaît au listing, le journal d'activité porte `DEPENSE_ENREGISTREE — Carburant : 17 500 FCFA (Gare de Bouaké) — Station Total`, puis suppression → base revenue à son état.

La page d'analyse, sur tes données :

```
DÉPENSES SAISIES  880 150 FCFA   (95 dépenses)
   Gares 570 150  ·  Siège 310 000
Charges non imputées à une gare   7 738 000 FCFA
   Dépannages 1 944 000 · Approvisionnements 5 794 000

Par poste     Carburant 55,1 %  Salaires 28,4 %  Loyer 6,8 %  …
Par gare      Adjamé  +940 300   Bouaké +116 500
              Korhogo −170 750   Siège −310 000
```

Les parts totalisent 99,9 % et les postes somment exactement au total : les deux totaux se recoupent, ce qui était l'objectif du bloc de réconciliation.

Le tableau de bord porte les cinq KPI, `Bénéfice net −6 669 550` = `1 948 600 − 1 944 000 − 5 794 000 − 880 150`, et j'ai vérifié en JavaScript que la clé `depense` atteint bien le graphique des coûts — c'était la panne silencieuse annoncée.

## Trois choses trouvées en chemin

**Le `map()` Twig filtre les clés** — confirmé, et corrigé : sans `depense: c.depense`, la troisième courbe n'existait tout simplement pas, sans erreur.

**Le macro KPI n'a pas d'icône `wallet`**, que le KPI « Bénéfice net » réclamait déjà : il retombait en silence sur l'icône de graphique. Ajoutée, plus une icône `expense` pour les charges.

**Le serveur intégré PHP rend les assets en `text/html`** quand on lui passe `public/index.php` en routeur : aucun composant React ne se monte. C'est le même piège que ton service worker, déjà documenté pour lui. J'ai vérifié avec un routeur qui sert les fichiers existants — `symfony serve` fait la même chose et est installé chez toi. C'est noté au README du FT.

## État

| | |
|---|---|
| Gabarits | 283 lintés ✓ |
| Tests FT | 29 ✓ · `tsc --noEmit` propre |
| Assets | reconstruits |
| Serveurs | arrêtés |

Une dépense de test reste en corbeille — je l'y ai laissée plutôt que de la purger, elle documente que le chemin de suppression fonctionne.

Il reste le chantier que tu as cadré mais pas engagé : **la caisse** — sessions par agent avec comptage et écart à la clôture, caisse du siège alimentée par les versements des gares. Le crochet est posé : `modereglement = ESPECES` identifie déjà ce qui sort physiquement d'un tiroir.





## Plan

# Gestion des dépenses

## Contexte

Une compagnie de transport gagne de l'argent avec les billets, courriers, bagages et réservations —
et en dépense pour le carburant, les péages, les pneus, les salaires, le loyer, les imprévus.
L'application sait tout de ce qui rentre : `RecetteGareService` compose la recette par gare et par
canal, et `/api/gares/me/dashboard` répond déjà à « combien ma gare a vendu aujourd'hui ».

Elle ne sait presque rien de ce qui sort. Les deux seuls coûts modélisés sont l'achat de pièces
(`Detailapprovisionnement::$couttotal`) et le dépannage (`Depannage::$couttotal`). Tout le reste —
l'essentiel des charges réelles — n'existe nulle part : aucune entité, aucune table, aucun écran.
La question « est-ce que la compagnie gagne réellement de l'argent, et laquelle de mes gares ? »
n'a donc pas de réponse aujourd'hui.

Ce chantier crée le module **Dépenses** et branche son total sur le bénéfice et sur le résultat par
gare. Il prépare, sans le livrer, le chantier suivant (la **caisse** : sessions par agent, caisse du
siège, versements des gares — cf. A1 de la feuille de route).

## Décisions actées

- **Option A** : `Depense` ne porte que les charges saisies à la main. Dépannages et
  approvisionnements gardent leurs postes. Le bénéfice devient
  `recettes − dépannages − approvisionnements − dépenses`. Aucune dépense n'est jamais dérivée d'un
  dépannage ou d'un approvisionnement — c'est ce qui garantit l'absence de double comptage.
- **Saisie directe**, aucun circuit de validation.
- Deux portées : **gare** (`gare` renseignée) et **siège/entreprise** (`gare` nulle).
- Les dépenses du siège sont visibles et saisissables par un **admin ou un utilisateur central sans
  gare** ; un agent rattaché à une gare ne voit et ne saisit que celles de sa gare.
- Le **justificatif accepte les PDF** : `MediaObject` passe de `Assert\Image` à `Assert\File`.
- Champ **`voyage` optionnel dès la migration** : les frais de route (forfait remis à l'équipage)
  seront plus tard un type de dépense rattaché à un voyage, et le résultat d'un voyage se dérivera
  (ventes − dépenses du voyage). Une seule écriture, aucune reprise de schéma. **L'écran frais de
  route n'est pas dans ce chantier.**

## Modèle

**`Typedepense`** — référentiel par entreprise, copie stricte de [Typepiece.php](Backend-Transport/src/Entity/Typepiece.php) :
`libelle`, `identreprise` (via `IdEntrepriseTrait`), `#[UniquePerEntreprise(['libelle'])]`,
`EntrepriseInjectionProcessor` / `UpdatedbyProcessor` / `SoftDeleteProcessor`, et un
`getSoftDeleteBlockers()` qui refuse la corbeille tant qu'une dépense active le porte. Les postes
varient d'une compagnie à l'autre : un enum figé imposerait une migration à chaque nouveau poste.

**`Depense extends EntityBase implements EntrepriseOwnedInterface, GareOwnedInterface, HasSoftDeleteGuard`**

| Champ | Type | Null | Note |
|---|---|---|---|
| `datedepense` | `datetime_immutable` | non | `datetime` et non `date` : `PeriodeTrait::parsePeriode()` borne la fin à 23:59:59 |
| `montant` | **`bigint`** | non | FCFA entier. `bigint` comme `Depannage::$couttotal`, `Detailapprovisionnement::$couttotal` et `Courrier::$total` : un INT plafonne à ~2,1 milliards de francs, hors d'atteinte pour un péage mais pas pour un lot de salaires ou l'achat d'un véhicule. Propriété typée `?int` et commentaire de justification, comme les quatre autres. `Assert\Positive` — un montant négatif remonterait le bénéfice |
| `typedepense` | FK `Typedepense` | non | |
| `gare` | FK `Gare` | **oui** | `null` = dépense du siège. C'est le seul discriminant — pas de colonne `portee` qui pourrait diverger. Même choix que `Role::$gare` |
| `voyage` | FK `Voyage` | oui | crochet frais de route, indexé, exposé par aucun écran |
| `modereglement` | string(20) | non | enum `Modereglement` (`ESPECES`, `MOBILE_MONEY`, `VIREMENT`, `CHEQUE`), stocké en string comme `Approvisionnement::$statut`. C'est le crochet de la future caisse |
| `beneficiaire` | string(255) | oui | texte libre |
| `fournisseur` | FK `Fournisseur` | oui | quand le bénéficiaire est déjà référencé |
| `justificatif` | FK `MediaObject` | oui | patron `Piece::$image` |
| `libelle` | string(255) | oui | le « pour quoi ». Aussi ce que la corbeille affiche (`CorbeilleService::GETTERS_LIBELLE`) |

`Fournisseur` est réutilisé mais ne suffit pas seul : il exige `contact`, `adresse` et `pays` non
nuls — forcer une fiche pour payer un péage produirait des fiches bidon. D'où le texte libre à côté.

**Index déclarés en attributs sur l'entité** (`identreprise+datedepense`, `identreprise+gare+datedepense`,
`voyage`), pas seulement dans la migration : `make test-db` construit la base de test par
`doctrine:schema:update`, un index posé uniquement par la migration n'existerait pas en test.

## Sécurité

Opérations : `GetCollection`/`Get` en `VOIR`, `Post` en `CREER`, `Patch` en `MODIFIER`, et
`/depenses/{id}/remove` réservé à **`ROLE_ADMIN`** — une sortie d'argent est un document comptable,
même garde que `Approvisionnement`. Pas d'action dédiée au-delà des quatre communes : en saisie
directe, une dépense n'a aucun effet de bord à défaire.

Lecture : `gareScopeField(): 'gare'` suffit. [GareScopeExtension](Backend-Transport/src/Doctrine/GareScopeExtension.php)
écrit `d.gare = :id`, et `NULL = 5` est faux en SQL — les dépenses du siège sont donc déjà invisibles
pour un agent de gare, tandis que l'admin et le central sans gare échappent au filtre. C'est
exactement la règle retenue, rien à écrire.

`Depense` **n'est pas ajoutée à `GareScopedEntities::ENTITIES`** : y figurer donnerait à tout
`ROLE_ADMIN_GARE` un bypass d'écriture sans permission, et le droit de déléguer ces permissions.
Sur une donnée qui fait sortir de l'argent, la permission reste explicite, accordée par l'admin
d'entreprise — même régime que le stock.

**`DepenseProcessor`** (patron [ApprovisionnementProcessor](Backend-Transport/src/State/ApprovisionnementProcessor.php)) :
injecte `identreprise`/`createdBy`/`updatedBy` ; **auto-affecte la gare de l'acteur** quand elle est
absente et **refuse (403)** une autre gare ou une dépense de siège pour un utilisateur rattaché ;
refuse le changement de gare au PATCH pour un non-admin ; et **vérifie que `typedepense`, `gare`,
`fournisseur` et `voyage` appartiennent à l'entreprise de l'acteur** — `EntrepriseScopeExtension` ne
s'applique pas au POST, sans cette vérification on crée une dépense pointant la gare d'une autre
compagnie.

## Ce qui se branche sur l'existant

**Le bénéfice** — [FinancierStatsProvider.php:59](Backend-Transport/src/State/FinancierStatsProvider.php:59) :
un `$coutDepenses` de plus dans la soustraction, alimenté par `DepenseRepository::coutTotal()` (copie
de `DepannageRepository::coutTotal()`). `FinancierStatistiqueOutput` gagne `coutDepenses` ;
`CoutParJourDto` gagne un 3ᵉ champ **avec défaut** — son unique instanciation utilise des arguments
nommés, aucun appelant PHP ne casse.

**Le résultat par gare** — c'est la demande centrale. Un `DepenseGareService::parGare()` symétrique de
[RecetteGareService](Backend-Transport/src/Domain/Service/RecetteGareService.php), indexé par gareId,
plus une entrée `siege` à part. Branché sur [GareDashboardController](Backend-Transport/src/Controller/Api/GareDashboardController.php)
(bloc `depenses` + `resultat`) et sur `GareStatsController::pilotage()` et `gares()`. Une seule règle,
trois surfaces — dupliquer la requête garantirait qu'elles divergent.

**Conséquence assumée, à rendre lisible plutôt qu'à cacher** : `Depannage` et `Approvisionnement`
n'ont aucune relation vers `Gare`. Le résultat d'une gare ne peut donc déduire que les dépenses qui
lui sont imputées. Trois garde-fous : on n'écrit jamais « bénéfice » sur un écran de gare mais
**« résultat d'exploitation »** ; la charge porte un champ `perimetre` et l'écran affiche la phrase
qui l'explique ; et la page dépenses réconcilie côté entreprise en **deux totaux exacts** —
`Σ gares + siège = coutDepenses` d'un côté, « non imputé à une gare : dépannages + approvisionnements »
de l'autre. Deux totaux justes valent mieux qu'un chiffre unique qui ment. La dette (imputer une gare
sur `Depannage`) est écrite au README.

**Audit** — trois constantes dans [ActiviteLogger](Backend-Transport/src/Domain/Service/ActiviteLogger.php) :
`DEPENSE_ENREGISTREE`, `DEPENSE_MODIFIEE` (seulement si `montant`, `gare` ou `typedepense` changent —
tracer une correction de libellé noierait le journal), `DEPENSE_SUPPRIMEE`. Aucune constante ne trace
aujourd'hui une sortie d'argent ; c'est le seul geste du système qui fait sortir du cash sans
contrepartie automatique, la trace est tout ce qui reste. **Pas d'alerte** dans ce chantier : un seuil
(« dépense anormale ») ne se calibre pas avant plusieurs mois de données réelles.

**Stats dédiées** — `GET /stats/depenses` (`ROLE_ADMIN`), déclaré dans
[Statistique.php](Backend-Transport/src/Entity/Data/Statistique.php) à côté de `/stats/financiere` :
par type, par gare (avec la recette en regard), par mois, par mode de règlement.

## Frontend

CRUD complet sur le patron `Fournisseur` : `DepenseController` + `TypedepenseController`, leurs
`FormType`, les gabarits `templates/depense/*` et `templates/typedepense/*`, les tables React
`assets/react/controllers/Finance/{DepenseTable,TypedepenseTable}.tsx` et leurs modèles TS.
Le sélecteur « Siège » du formulaire n'apparaît que pour un profil autorisé à le saisir.
L'upload du justificatif suit [PieceController](Frontend-Transport/src/Controller/PieceController.php) :
`ApiHelper::postMediaObject($file)` puis l'IRI dans la charge — la dépense reste du JSON pur.

Nouveau groupe de menu **« Finances »** dans `templates/base.html.twig` (après Stock), gardé par
`DEPENSE_VOIR or TYPEDEPENSE_VOIR` : il accueillera la caisse au chantier suivant.
Nouvelle page `owner.stats.depenses` + tuile dans `home/owner.html.twig`, graphiques en **JS vanilla**
dans `assets/app.js` comme les autres stats. Rubrique d'aide dans `home/aide.html.twig`.

**Trois pannes silencieuses à ne pas manquer** : le `map()` de
[_entreprise.html.twig:71](Frontend-Transport/templates/home/_entreprise.html.twig:71) filtre les clés
(le nouveau champ n'atteindra pas le graphique sans `depense: c.depense`) ; `HomeController`
re-normalise les charges de stats dans des tableaux littéraux (clé oubliée + `strict_variables` = 500
sur le tableau de bord) ; et le sous-titre de `home/recettes.html.twig` (« Recettes − coûts
(dépannages, appro.) ») devient faux.

## Ordre de livraison

**Palier 1 — backend.** Entités, enum, repositories, `DepenseProcessor`, `MediaObject` assoupli,
migration, `make test-db`. Puis l'audit, l'extension de `Fournisseur::getSoftDeleteBlockers()` (il ne
compte que les approvisionnements aujourd'hui), les fabriques `ScenarioBuilder` et
`tests/Api/DepenseTest.php`. Puis le bénéfice, le résultat par gare, `/stats/depenses`.
**Arrêt pour validation sur données réelles.**

**Palier 2 — frontend.** CRUD, menu, upload, page de stats, blocs résultat de gare, aide.

**Palier 3 — fixtures et documentation.** `DepenseFixtures` : IRA bénéficiaire, **SAHEL au-dessus de
ses recettes** — seul moyen de voir à l'œil nu que le bénéfice passe au rouge et que la question
« est-ce que je gagne de l'argent ? » peut recevoir « non ». Rôle `comptable` dans `RoleFixtures`
(portée ENTREPRISE, `Depense` n'étant pas dans `GareScopedEntities`). Les deux README.

## Vérification

```bash
php bin/console doctrine:migrations:migrate --no-interaction
make test-db && make test-api
```

Les tests qui écrivent la règle (`tests/Api/DepenseTest.php`) :

1. **Périmètre entreprise** — la dépense de B invisible chez A ; POST chez A avec un `typedepense`
   de B refusé (le POST échappe à `EntrepriseScopeExtension`).
2. **Périmètre gare** — agent de Bouaké : ne voit que Bouaké, ne voit pas le siège, `GET` d'une autre
   gare en 404.
3. **Écriture bornée** — agent de gare qui POSTe une autre gare ou le siège → 403 ; sans gare → sa
   propre gare.
4. **Bénéfice** — `/stats/financiere` avant/après une dépense de 50 000 : `beneficeNet` baisse
   d'exactement 50 000. **C'est la sentinelle de l'Option A** : elle tombe le jour où quelqu'un
   dérive une dépense d'un dépannage.
5. **Résultat par gare** — `resultat.net === recetteTotale − depenses.montant`, et une dépense de
   siège n'y entre pas.
6. **Suppression** — `Depense SUPPRIMER` sans `ROLE_ADMIN` → 403 sur `/remove`.

Puis, à la main sur la base de développement : charger les fixtures, ouvrir `/stats/depenses` et le
tableau de bord d'une gare, vérifier que le bénéfice affiché à l'écran est bien celui de l'API et que
la compagnie SAHEL ressort en rouge.

## Hors périmètre

- **La caisse** (chantier suivant) : sessions par agent, comptage et écart à la clôture, visa, caisse
  du siège, versements des gares. Rien ici ne la bloque — `modereglement = ESPECES` est le crochet, et
  une future `Sessioncaisse` se raccroche par une FK nullable, colonne additive.
- **L'écran frais de route** : le champ `voyage` existe, rien ne l'exploite.
- Export comptable, budgets et plafonds, dépenses récurrentes, imputation d'une gare sur `Depannage`.




















On enchaîne avec la caisse

Le cadrage est déjà pris : sessions par agent, caisse du siège alimentée par des versements, `ESPECES` comme crochet. J'explore ce que l'application sait déjà de l'argent qui passe par un guichet.

L'exploration met au jour un obstacle qu'il faut regarder en face avant de concevoir.

## Le problème central

Une caisse se contrôle en comparant **ce qu'il devrait y avoir dans le tiroir** à ce qu'on y compte. Or aujourd'hui, **aucune recette ne dit comment elle a été payée** : un billet, un bagage, un courrier n'ont pas de mode de règlement. Seule la `Depense` en a un. Si on calcule le théorique à partir de toutes les ventes, il inclura les paiements mobiles et l'écart sera faux tous les soirs.

Trois autres trous, plus petits :

- **le remboursement d'un désistement n'est chiffré nulle part** — le billet passe `ANNULE`, la recette baisse rétroactivement, mais la sortie d'espèces ne laisse aucune trace ([DesistementProcessor.php:136](Backend-Transport/src/State/DesistementProcessor.php:136)) ;
- **le commercial à bord détient des espèces sans qu'aucune remise ne soit constatée** — le système connaît sa recette théorique, jamais le moment où il rend l'argent ;
- la confirmation d'un bon au guichet est un encaissement réel, alors qu'un commentaire du code affirme que cet argent « n'est pas dans le tiroir de la gare ».

Quatre décisions avant que je conçoive :

Comment sait-on qu'une vente a été payée en espèces ?

Tout le guichet est réputé espèces (Recommandé)

Un agent peut-il vendre sans avoir ouvert sa caisse ?

Oui, et la vente rejoint sa session (Recommandé)

Le commercial à bord entre-t-il dans ce chantier ?

Plus tard, séparément (Recommandé)

Les versements d'espèces d'une gare vers le siège ?

Dans ce chantier (Recommandé)




Cadrage complet. Je fais concevoir l'implémentation.

Le plan tient. Deux questions métier me reviennent avant de l'écrire — et la première met au jour un défaut existant.

Confirmé : `fraissuivi` est saisi et stocké (500 F, 1 000 F dans tes fixtures) mais **n'entre dans aucun calcul de recette** — aucun repository ne le somme. C'est de l'argent encaissé au guichet que l'application ne compte nulle part. Ton ticket de référence SBTA portait d'ailleurs « FRAIS DE SUIVI SMS : 100 ».


Les frais de suivi d'un courrier sont-ils encaissés au guichet ?

Oui — caisse ET recette (Recommandé)

Un bagage ou un courrier annulé au guichet est-il remboursé au client ?

On ne rembourse pas un courrier ou bagage annulé, c'est quand il y'a perte qu'on rembourse




Noté, et c'est une distinction qui compte : **l'annulation ne rembourse pas, la perte oui**. Le remboursement pour perte relève de B4 de ta feuille de route (réclamations et indemnisations), qui n'existe pas encore — je prévois le mécanisme sans le brancher. J'écris le plan.

Plan écrit. Quatre points méritent ton œil avant que je démarre.

**Le théorique sera persisté**, contre la règle maison du « rien de dérivable stocké ». C'est volontaire : une clôture est une pièce opposable, et un chiffre recalculé bougerait au moindre tarif corrigé ou billet annulé — l'écart signé par l'agent ne voudrait alors plus rien dire.

**Aucun filtre sur le statut des billets**, et c'est le point le plus contre-intuitif du module. Toutes les requêtes existantes portent `statut = 'VALIDE'` ; ici ce serait faux. L'argent est entré dans le tiroir à la vente ; une annulation ne l'efface pas, elle le ressort par une ligne de remboursement. Filtrer ferait disparaître une vente et son annulation du même jour **des deux côtés**, en masquant deux mouvements réels.

**La correction des frais de suivi fera monter tes recettes courrier affichées** — parce qu'elles étaient incomplètes, pas parce que le calcul change. À savoir avant de comparer avec un chiffre d'avant.

Quatre décisions que j'ai tranchées sans te déranger, faciles à retourner : tout écart demande un motif (seuil à zéro), la grille de dénominations reste dans le navigateur et seul le total part au serveur, un agent ne voit pas la caisse d'un collègue, et le solde du siège part de zéro à la mise en service.

Deux bonnes questions — le plan employait « visa » sans jamais le définir.

## Le visa

C'est le mot de ta feuille de route : *« Le chef de gare vise. Rien ne se modifie après le visa. »* Viser, c'est contrôler et approuver — mettre son nom sur le document.

Le parcours d'une caisse aurait donc deux temps :

1. **L'agent clôture.** Il compte les espèces de son tiroir, saisit le total, l'écart apparaît en face du théorique, il le justifie s'il y en a un. La caisse passe `CLOTUREE`.
2. **Le chef de gare vise.** Il reçoit l'argent, vérifie que le compte y est, et approuve. La caisse passe `VISEE` et devient immuable.

L'intérêt tient en une phrase : sans ce second regard, **l'agent est seul juge de son propre manquant**. Le visa met un deuxième nom sur la pièce, et c'est ce qui la rend opposable — sinon « il manque 12 000 F, motif : erreur de rendu monnaie » n'engage que celui qui l'a écrit. C'est aussi pourquoi *nul ne vise sa propre caisse*.

## La sentinelle, avec des chiffres

Un agent vend un billet **15 000 F** le matin. L'après-midi, le client se désiste : on lui rend ses 15 000 F.

Dans le tiroir : `+15 000` puis `−15 000` = **0**. Rien n'a bougé.

Maintenant, comment le système calcule son théorique :

| | avec le filtre `statut = VALIDE` | sans filtre (retenu) |
|---|---|---|
| la vente | **0** — le billet est passé `ANNULE`, il disparaît du calcul | **+15 000** — elle a bien eu lieu |
| le remboursement | −15 000 | −15 000 |
| **théorique** | **−15 000** ❌ | **0** ✅ |

Avec le filtre, le système réclamerait 15 000 F manquants à un agent dont la caisse est parfaitement juste. Le piège est vicieux parce que `statut = 'VALIDE'` est le réflexe partout ailleurs dans ton code — `TicketRepository`, `RecetteGareService`, les bordereaux. Quelqu'un l'ajoutera un jour par habitude. Le test existe pour tomber ce jour-là.

Et si l'annulation a lieu **le lendemain** : la caisse de lundi garde ses `+15 000` (l'agent les a bien remis lundi soir), et la sortie tombe dans la caisse de mardi. C'est pour ça que le billet porte **deux** rattachements distincts — sinon on modifierait rétroactivement une caisse déjà clôturée et visée.


Tu veux ce second niveau de contrôle, le visa du chef de gare ?

Non, la clôture suffit



Bien — deux statuts, et le contrôle se fera par la lecture des écarts. J'ajuste le plan.

Le plan est aligné : deux statuts, `OUVERTE` puis `CLOTUREE`, et le contrôle hiérarchique passe par la lecture des écarts — écran filtrable et alerte `CAISSE_ECART_ELEVE` en portée direction. Le ticket imprimé garde deux lignes de signature, parce que remettre l'argent reste un geste physique même sans acte dans l'application.

Ça retire un écran, une action dédiée, une permission et une alerte du chantier.

Entendu, je ne touche à rien.

Le plan est figé dans `~/.claude/plans/on-vas-mettre-en-groovy-hopper.md`, marqué comme validé et en attente. Il contient tout le cadrage : les six décisions que tu as prises (guichet réputé espèces, ouverture automatique sans garde bloquante, commercial hors périmètre, versements inclus, frais de suivi réparés dans la caisse et dans la recette, annulation sans remboursement), le modèle à deux statuts, la mécanique de rattachement par clé explicite, et l'ordre de livraison en six paliers avec un arrêt de validation après le backend.

Dis-moi quand tu veux que je démarre — je reprendrai au palier 1, le rattachement.







Au faite, je crois que j'ai mal compris la notion de caisse, recettes, etc...(explique moi mieux), donc je veux que tu analyse ce que je vais te dire pour voir si on n'est sur le bon chemin :

```
Concernant la `La « caisse entreprise », c'est quoi au juste ?`, je me dis que je me suis trompé et que je faisais allusion au solde de l'entreprise, aussi pour la gare ! du genre les ventes d'une gare alimentent son solde, les versements alimentent le solde de l'entreprise ! où est ce que je me trompe !

Aussi, je me dis qu'à la création d'une gare on lui définie son solde initial qui sera incrémenté et décrémenté ! aussi pour l'entreprise !

Aussi, si une gare vend aujourd'hui et que demain elle fais une dépense, l'argent devrait sortir du solde de la gare plutôt que dans la caisse du jour !
```


Aussi :
- Ignore le dossier `brl/` dans `Backend-Transport/tools/`, c'est un brouillon aussi







Bonne remise en question — et tu es plus près du vrai que tu ne le crois. Je vérifie deux choses dans le code avant de te répondre.

Tu ne t'es presque pas trompé. Ce que tu décris est **plus juste** que ce que j'avais écrit dans le plan. Reprenons les trois mots, parce qu'ils désignent trois choses différentes qu'on confond facilement.

## Recette, dépense, solde

| | ce que c'est | question à laquelle ça répond |
|---|---|---|
| **Recette** | ce qu'on a **gagné** sur une période | « combien on a vendu en septembre ? » |
| **Dépense** | ce qu'on a **déboursé** sur une période | « combien on a payé de carburant ? » |
| **Solde** | ce qu'on **détient maintenant** | « combien d'argent y a-t-il dans le coffre ? » |

Les deux premiers sont des **flux** : ils mesurent un mouvement pendant un temps donné. Le troisième est un **stock** : il mesure ce qui reste à un instant.

C'est la différence entre un compteur kilométrique et une jauge de carburant. Le compteur ne fait que monter et dit le chemin parcouru ; la jauge monte et descend, et dit ce qu'il reste dans le réservoir. Aucun des deux ne remplace l'autre.

Déroulons sur ta gare d'Adjamé :

```
Elle ouvre avec un fonds de           50 000     ← solde initial
Lundi : vend pour                    +300 000
                                      ─────────
Lundi soir, solde                     350 000
Lundi soir : verse au siège          −300 000
                                      ─────────
Solde                                  50 000
Mardi : paie le gasoil                −20 000
                                      ─────────
Solde                                  30 000
```

Sur ces deux jours : **recette = 300 000**, **dépense = 20 000**, **résultat = 280 000**, et **solde = 30 000**. Quatre chiffres tous justes, qui ne disent pas la même chose. Le résultat te dit si l'affaire est rentable ; le solde te dit ce qu'il y a dans le tiroir ce soir.

## Ce que tu as dit, point par point

**« Les ventes d'une gare alimentent son solde, les versements alimentent le solde de l'entreprise »** — exact, et c'est déjà la formule du plan.

**« À la création d'une gare on lui définit son solde initial »** — juste, et **meilleur que ce que j'avais prévu**. J'avais écrit « le solde part de zéro ». C'est faux dans la vraie vie : une gare ouvre avec une encaisse pour rendre la monnaie. Il faut un solde initial sur la gare et sur l'entreprise. Aucun des deux n'existe aujourd'hui — je viens de vérifier, `Gare` et `Entreprise` n'ont aucun champ de ce genre.

**« Si une gare vend aujourd'hui et dépense demain, l'argent sort du solde de la gare »** — exact aussi. Et c'est là qu'est ta seule vraie confusion, qui vient sans doute de mon vocabulaire.

## La nuance sur la caisse

Une session de caisse **n'est pas un pot d'argent séparé** du solde de la gare. C'est une **période de responsabilité d'une personne**.

Quand l'agent Kouassi prend son poste à 8h et le quitte à 18h, sa session dit : *« Kouassi a encaissé 300 000 F aujourd'hui ; à 18h, il doit y avoir 300 000 de plus dans son tiroir qu'à son arrivée. »* Si le compte n'y est pas, on sait **qui** était derrière le guichet.

C'est un zoom sur une personne et une plage horaire, **à l'intérieur** du solde de la gare. Le même argent, regardé sous deux angles :

- le **solde** répond à « combien la gare détient-elle ? » — pour le patron ;
- la **session** répond à « le compte de Kouassi tombe-t-il juste ce soir ? » — pour le contrôle.

Donc ta phrase est juste : la dépense de demain sort bien du solde de la gare. Et **en plus**, si c'est Kouassi qui sort les billets de son tiroir pour payer le gasoil, elle s'impute à sa session — sinon il aurait 20 000 F de manquant inexpliqué à sa clôture. Si c'est le chef de gare qui paie depuis le coffre, la dépense touche le solde sans toucher aucune session.

## Une correction technique à ta formulation

Tu dis « un solde initial qui sera incrémenté et décrémenté ». C'est la bonne image mentale, mais on ne le codera pas ainsi : on stocke le **solde initial** (une donnée) et on **recalcule** le solde courant à la lecture. Un compteur qu'on incrémente finit toujours par dériver — une dépense corrigée, une vente annulée, et le chiffre ment sans que personne ne le voie. C'est déjà la règle du projet, écrite pour la fidélité : *« aucun compteur stocké, aucune dérive »*.

Le résultat affiché est identique, le risque disparaît.

## Ce que ça change au plan

Trois ajouts, et le reste tient :

1. **`soldeinitial` sur `Gare` et sur `Entreprise`**, saisis à la création — ce que j'avais raté.
2. **Le solde devient l'écran de tête** : « ma gare détient X » pour le chef de gare, « l'entreprise détient Y » pour le patron. La session de caisse passe au second plan, comme outil de contrôle quotidien.
3. Le reste du plan (rattachement, clôture, versements) ne bouge pas — c'est la mécanique qui alimente ces soldes.

Deux questions que ta remarque fait remonter :

À la fin de son poste, l'agent remet-il son argent au chef de gare ?

Oui, il remet tout (Recommandé)

Une dépense de gare sort-elle du tiroir d'un agent ou du coffre ?

Toujours du coffre du chef de gare



Ces deux réponses **simplifient nettement** le chantier. Je mets le plan à jour.

Noté pour `tools/brl/` — ignoré comme les `brl.md`.

## Ce que tes deux réponses changent

Elles **retirent du travail** plutôt qu'elles n'en ajoutent.

**Le module Dépenses livré ne bouge plus du tout.** Puisqu'une dépense sort toujours du coffre du chef de gare, elle ne s'impute à aucune caisse d'agent : aucune colonne à ajouter, aucun processor à reprendre. Elle pèse sur le **solde de la gare**, ce qui est exactement ton intuition de départ.

**La caisse d'un agent devient très simple.** Elle ne connaît plus que des entrées — billets, bagages, courriers, bons, régularisations — et **une seule sortie** : le remboursement qu'il rend de son tiroir à un client qui se désiste. Plus de dépenses ni de versements dans son théorique.

```
théorique = fonds avancé le matin
          + ce qu'il a encaissé
          − ce qu'il a remboursé
```

Et comme il remet tout le soir, un écart appartient toujours à une personne et à une journée, sans report possible.

## Le plan réordonné

Le solde passe **en premier**, parce que c'est le chiffre que tu veux voir et qu'il est livrable seul :

| | |
|---|---|
| **1** | Les soldes — `soldeinitial` sur gare et entreprise, calcul, écrans d'API. **Déjà utile sans rien d'autre** : ventes et dépenses existent déjà |
| **2** | Le rattachement des ventes à une session |
| **3** | La clôture avec comptage et écart |
| **4** | Remboursements et périmètre |
| **5** | Versements gare → siège — *arrêt pour validation* |
| **6** | Frontend |
| **7** | Alertes, fixtures, documentation |

Le palier 1 répond déjà à « combien ma gare détient-elle ? ». Les suivants répondent à « et le compte de Kouassi tombe-t-il juste ce soir ? ».

## Un point que j'ai corrigé dans le plan

J'avais écrit que le solde partait de zéro. C'était faux, et ta remarque l'a attrapé : une gare ouvre avec une encaisse pour rendre la monnaie. `soldeinitial` est maintenant au plan sur `Gare` **et** sur `Entreprise` — ni l'une ni l'autre n'a le moindre champ de ce genre aujourd'hui, je l'ai vérifié.

Seule nuance sur ta formulation : on stockera le solde **initial**, pas un compteur qu'on incrémente. Le solde courant se recalcule à chaque lecture. Un compteur finit toujours par dériver — une dépense corrigée, une vente annulée, et le chiffre ment sans que personne ne s'en aperçoive. Le résultat à l'écran est identique.

Le plan est à jour. Je ne démarre pas, tu me diras.












Le plan est à jour et intègre les deux dernières décisions (l'agent remet tout, la dépense sort du coffre). Je demande l'approbation pour sortir du mode plan et attaquer le palier 1.

On selected text:
>  le solde d'une gare bouge d'exactement le montant d'une vente puis d'une
> dépense en espèces.
Quand tu dis "dépense en espèces", tu fais allusion à quoi  ? donc si la gare dépense autrement ça ne compte pas ?


Bonne question, et elle touche un vrai point.

## Ce que « en espèces » veut dire ici

Une dépense par virement **n'est pas ignorée**. Elle compte déjà, mais pas dans le même chiffre :

| | compte les dépenses… |
|---|---|
| **Résultat** (recettes − dépenses) — déjà livré | **tous modes confondus** : espèces, virement, chèque, mobile money |
| **Solde du coffre** — à venir | **seulement ce qui est physiquement sorti** |

Le filtre `ESPECES` ne sert pas à minimiser la dépense, il sert à garder le solde **comptable à la main**. Si je soustrais du coffre un virement de 2 M de salaires, le chiffre affiché ne correspond plus à ce que le chef de gare peut compter dans son coffre — et le contrôle qu'on construit ne vaut plus rien. L'argent d'un virement est parti d'un compte en banque, que l'application ne suit pas.

C'est déjà écrit dans le code, d'ailleurs — le commentaire de l'énumération le prévoyait ([Modereglement.php:8](Backend-Transport/src/Domain/Enum/Modereglement.php#L8)) :

> `ESPECES` est ce qui, demain, reliera une dépense à la CAISSE d'une gare.

## Là où ta question tape juste

Le **Mobile Money d'une gare**. En vrai, l'agent prend des billets dans le coffre, les dépose sur le portefeuille, et paie le fournisseur. L'argent **est** sorti du coffre. Avec le filtre `ESPECES` seul, le solde affiché reste trop haut et ment dans l'autre sens — silencieusement.

Je dois te faire trancher ça avant d'écrire le calcul.


Comment décider qu'une dépense est sortie du coffre de la gare ?

Explique comment tu définie les dépenses d'une gare, et aussi d'une entreprise ? Quel est la logique derrière



## La logique d'imputation, telle qu'elle est écrite

### Un seul champ décide

Il n'y a **pas** de colonne « portée ». Il y a `Depense.gare`, et c'est tout ([Depense.php:184](Backend-Transport/src/Entity/Depense.php#L184)) :

| `gare` | signification |
|---|---|
| renseignée | dépense **de cette gare** |
| **nulle** | dépense du **siège**, donc de l'entreprise |

`getPortee()` renvoie `GARE` ou `ENTREPRISE` en lisant la relation, sans jamais la stocker — une ligne « portee = GARE » avec `gare` nulle ne voudrait rien dire.

### Qui a le droit de poser ce champ

C'est la règle du processor ([DepenseProcessor.php:130](Backend-Transport/src/State/DepenseProcessor.php#L130)) :

- **Agent rattaché à une gare** → sa gare, imposée. Champ vide : on le remplit. Champ pointant ailleurs : **403**, pas de réécriture silencieuse. Il ne peut donc **jamais** imputer au siège.
- **Admin, ou utilisateur central sans gare** → libre, siège compris.

La raison est de lecture, pas de confiance : le périmètre de gare masque les dépenses sans gare (`d.gare = :id` est faux pour `NULL`). Un agent qui imputerait au siège **perdrait de vue sa propre dépense** dès la page suivante.

### Ce qui décide métier de la portée

La question est : **qui a engagé la charge, et pour quelle activité ?**

- **Gare** — carburant d'un départ, péage, petite maintenance, gardiennage du guichet, ration d'équipage. Engagée sur place, pour l'activité de cette gare.
- **Entreprise (siège)** — loyer du siège, salaires de la direction, assurance de la flotte, licences. Elle ne se rattache à aucune gare **sans clé de répartition inventée**.

Et c'est là le choix de fond : on **ne répartit pas** les charges du siège sur les gares. Il faudrait une clé — au prorata des recettes ? des départs ? — et toute clé est une convention que le logiciel imposerait sans que personne ne la voie. D'où le vocabulaire tenu à l'écran : **« résultat d'exploitation »** sur une gare, jamais « bénéfice », plus le bloc « Charges non imputées à une gare » qui affiche à part les dépannages et les approvisionnements — eux n'ont aucune relation vers `Gare` du tout.

## Pourquoi ma question portait sur autre chose

Ce sont **deux axes indépendants**, et c'est l'essentiel :

| axe | champ | question à laquelle il répond |
|---|---|---|
| **Portée** | `gare` | qui **porte** la charge dans son résultat |
| **Trésorerie** | `modereglement` | d'**où l'argent est sorti** |

Une dépense de la gare d'Adjamé payée par virement reste **à 100 % une charge d'Adjamé** — son résultat baisse du montant plein. Mais son **coffre** n'a pas bougé, parce que l'argent est parti d'un compte en banque.

C'est le seul point que ta question soulève, et il ne concerne que le solde : quels modes de règlement font baisser le coffre.

Quels modes de règlement font baisser le COFFRE d'une gare (la portée reste inchangée dans tous les cas) ?

J'avoue que je suis perdu, on vas y revenir plus tard mais répond à cette question : Est ce qu'une dépense par virement ou autre d'une gare diminue sa recette?



Réponse courte : **non, jamais.** Aucune dépense ne diminue la recette d'une gare, quel que soit son mode de règlement.

## Les trois chiffres

| chiffre | ce qu'il mesure | une dépense y touche ? |
|---|---|---|
| **Recette** | ce qui est **entré** : billets, bagages, courriers | **non, jamais** |
| **Dépenses** | ce qui est **sorti** | oui — **tous modes confondus** |
| **Résultat** | recette − dépenses | oui, du montant plein |

La recette est un chiffre de **vente**. Elle ne connaît que les encaissements ; rien de ce qui sort ne la touche. C'est pour ça qu'elles sont **trois colonnes distinctes** sur l'écran, et pas un chiffre unique ([depenses.html.twig:99](Frontend-Transport/templates/home/depenses.html.twig#L99)).

## Et côté dépenses, le mode ne change rien

Je viens de le vérifier dans le code : **aucune requête de total ne filtre sur `modereglement`**. Il n'apparaît qu'à un seul endroit du repository, pour la ventilation d'affichage « comment elles sont réglées » ([DepenseRepository.php:142](Backend-Transport/src/Repository/DepenseRepository.php#L142)).

Donc un virement de 500 000 F et 500 000 F en espèces font **exactement le même effet** : le résultat de la gare baisse de 500 000 dans les deux cas, sa recette ne bouge dans aucun.

Le mode de règlement ne servira qu'au **futur solde du coffre** — nulle part ailleurs. C'est sa seule utilité à venir, et c'est pour ça que ma question ne concernait que ce chiffre-là.

## Ce que je fais en attendant

Je garde ce que le plan validé dit déjà : **espèces seules** font baisser le coffre. On y reviendra quand tu voudras — c'est **une ligne de requête** à changer si tu préfères y ajouter le Mobile Money, ou une colonne si tu veux la case explicite. Je note la réversibilité au plan pour ne pas la perdre de vue.

Tout est commité, au fait — les cinq dépôts sont propres, seuls tes `brl.md` sont modifiés. Ça retire un point de la liste.


On vas y revenir, je te dirai si j'accepte le plan, aussi dit moi ce qu'on peut faire parmi les choses qu'on a mis en attente



## Ce qui est en attente

### Faisable tout de suite, sans toucher à la caisse

**1. Les frais de suivi du courrier — un vrai trou, pas un confort.**
Je viens de le revérifier : `fraissuivi` n'apparaît **dans aucun repository**. Il est saisi, il est encaissé, et il n'entre dans **aucun** calcul de recette. Les recettes courrier affichées sont donc **fausses par défaut**, en moins. Cinq méthodes de `CourrierRepository` à passer en `montant + COALESCE(fraissuivi, 0)`, plus une note au README parce que les chiffres affichés vont monter. Le plan caisse le prévoyait au palier 3, mais il n'a **aucun besoin** de la caisse pour être corrigé.

**2. L'échelle du jeu de démonstration.**
≈5,8 M d'approvisionnements + 1,9 M de dépannages contre ≈1,9 M de recettes : la démo est structurellement déficitaire, et tous les écrans financiers affichent du rouge qui ne veut rien dire. Ça vient de `StockFixtures` et `DepannageFixtures`, pas du module Dépenses. Rééquilibrer les volumes rendrait les écrans lisibles — et c'est justement ce dont on aura besoin pour juger le solde sur données réelles.

**3. La corbeille des dépenses pour l'admin de gare.**
Vérifié : `Remove_Depense` est gardé par `ROLE_ADMIN` seul, et `ROLE_ADMIN_GARE` **n'hérite pas** de `ROLE_ADMIN` — c'est un bypass du `PermissionVoter` limité aux entités de gare ([PermissionVoter.php:71](Backend-Transport/src/Security/Voter/PermissionVoter.php#L71)). Conséquence concrète : un chef de gare qui saisit une dépense de travers **ne peut pas la corriger**, il doit appeler un admin d'entreprise. C'est une décision de ta part, puis deux lignes.

**4. Les frais de route.**
`Depense::$voyage` existe, aucun écran ne l'expose — tu avais dit que ce n'était pas lié au chantier dépenses. Il reste à décider si on ajoute un sélecteur de voyage au formulaire : à partir de là, le résultat d'un départ (ventes − frais de route) se dérive sans rien ajouter au schéma.

### Bloqué par la caisse

**5. La remise d'espèces du commercial à bord.** Tu avais dit « plus tard, séparément ». Elle n'a de sens qu'une fois les versements posés — elle se traitera probablement comme un versement à une gare.

### La feuille de route, après A1

Par priorité décroissante : **D1** limite de débit sur la réinitialisation de mot de passe *(Haute, sécurité)*, **B2** contrôle d'embarquement par QR, **C1** recherche globale opérationnelle, **A2** report de masse d'un départ annulé, **B1** notifications SMS, **A3** e-mails asynchrones. **B3** (vente en mode dégradé) est déjà traitée pour le commercial à bord ; il resterait le guichet.

On fait quoi maintenant, en attendant ta décision sur la caisse ?

Le 1, 2, 3, 4



Je commence par le trou réel : les frais de suivi.

Maintenant un constructeur de courrier pour les tests, puis la sentinelle.

Maintenant la sentinelle.

Le suite complète tourne en arrière-plan. Je passe à l'échelle des fixtures.

237/237 vert. Maintenant l'échelle du jeu de démonstration.

Je peux simuler l'effet exact sans toucher à la base — les tirages sont déterministes.

La simulation le dit clairement : le problème n'était pas les coûts, c'était le **taux de remplissage** — 8 billets par départ sur des cars de 50 à 70 places.

Maintenant le champ dans le formulaire.

Je lance la suite complète et la compilation des assets en parallèle.

Tout est vert : 239 tests. Je mets le plan de la caisse à jour, puisqu'un de ses paliers vient d'être réglé à l'avance.

Les quatre points sont faits côté code, et tout passe. Voilà le détail avant la seule action qui touche la base.

## 1 · Frais de suivi du courrier — le trou est bouché

Les **douze** sommes de [CourrierRepository.php](Backend-Transport/src/Repository/CourrierRepository.php) portent maintenant `SUM(c.montant + COALESCE(c.fraissuivi, 0))` — annulations et suppressions par agent comprises, puisqu'elles mesurent la recette qui *disparaît* du livre et doivent se lire dans la même unité.

Mesuré sur tes données : la recette courrier passe de **19 500** à **21 000 FCFA**, soit exactement les 1 500 F de frais de suivi des courriers non annulés.

Le piège était l'inverse du bug : `fraissuivi` est nullable, et `montant + NULL` vaut `NULL` en SQL. Sans le `COALESCE`, **tous les courriers sans frais de suivi** auraient disparu de la somme. J'ai vérifié que la sentinelle tombe dans les deux sens :

| version du code | tests en échec |
|---|---|
| le bug d'origine (`SUM(montant)`) | 4 |
| le correctif naïf (`SUM(montant + fraissuivi)`) | 3 |
| le correctif retenu | **0** |

`tests/Domain/RecetteCourrierTest.php` (5 tests), plus un constructeur `courrier()` dans `ScenarioBuilder`.

## 2 · L'échelle du jeu de démonstration — ce n'étaient pas les coûts

La simulation hors base (les tirages sont déterministes) désigne un autre coupable : **le taux de remplissage**. Les cars de l'historique ont 50, 60 et 70 places, et `HistoriqueFixtures` leur vendait **5 à 12 billets** — 14 % de charge. Une flotte de cinq cars coûte ce qu'elle coûte ; cette recette-là ne pouvait jamais la couvrir.

| plage | billets | recette totale | bénéfice | marge |
|---|---|---|---|---|
| 5–12 *(avant)* | 241 | 2 483 600 | **−7 156 050** | — |
| 18–32 | 711 | 7 303 900 | −2 335 750 | — |
| **26–44** *(retenu)* | **997** | **10 154 600** | **+514 950** | 5,1 % |

Le plafond de 44 reste sous les 50 sièges du plus petit car — un billet prend le siège `$i + 1`, la référence doit exister.

J'ai ajouté un seul geste sur les coûts : la commande de **24 pneus** (3,3 M à elle seule, plus que tout le reste réuni) descend à **18**. Plancher imposé non par le budget mais par le seuil d'alerte : `DepannageFixtures` en consomme 6 sur D2, et le stock final doit rester **strictement** au-dessus de 6, sinon un `STOCK_FAIBLE` de plus apparaît et le scénario d'alertes calibré là-bas — plaquettes faibles, batteries en rupture, et rien d'autre — cesse d'être vrai.

Résultat attendu : **≈ +1,34 M de bénéfice, 13 % de marge**. Une marge de transporteur, et assez épaisse pour qu'un filtre de période ne la fasse pas basculer dans le rouge.

## 3 · La corbeille s'ouvre à l'admin de gare

`Remove_Depense` accepte désormais `ROLE_ADMIN_GARE`. La garde reste un **rôle**, jamais la permission `SUPPRIMER` — c'est tout l'intérêt : un chef de gare délègue la saisie à son caissier sans lui ouvrir la corbeille.

Le point que j'ai vérifié plutôt que supposé : `GareScopeExtension` implémente aussi `QueryItemExtensionInterface`, donc la dépense d'une autre gare — ou du siège — répond **404 avant** que l'expression de sécurité ne soit évaluée. L'ouverture n'élargit rien d'autre que son propre périmètre. Test à l'appui.

La **restauration** reste `ROLE_SUPER_ADMIN`. Asymétrie assumée, et déjà vraie pour l'admin d'entreprise.

## 4 · Les frais de route sont exposés

`Depense::$voyage` a maintenant un sélecteur, avec une fenêtre volontairement étroite : **la semaine écoulée et les deux jours à venir**. Un forfait se remet à l'équipage le jour du départ ; servir tout l'historique ferait un sélecteur de plusieurs milliers de lignes où l'on ne trouve plus le départ du matin.

L'étiquette d'un départ reprend le **numéro du jour** — « ABJ-KOR-V12 · départ 2 · 24/09 à 07h00 » — parce que c'est ainsi qu'un guichet désigne un départ à l'oral. Le champ reste facultatif : loyer, salaires et gasoil du parc ne se rattachent à aucun départ.

Un détail que j'ai dû corriger en cours de route : `Voyage::$id` n'avait pas le groupe `read:Depense` (seul `codevoyage` l'avait), donc le formulaire de modification n'aurait pas pu repré-sélectionner le départ déjà rattaché. Ajouté, avec un test qui le verrouille.

**239 tests verts.** README et plan de la caisse à jour — le palier 3 y perd sa partie « recette ».

Le rechargement des fixtures : les nouvelles valeurs n'existent que dans le code, la base porte encore l'ancien jeu.

Recharge, et vérifie


Reste une seule action, et elle écrit dans la base.

Rechargé. Je mesure.

côté chiffres, c'est mesuré. Je contrôle maintenant les alertes de stock calibrées.

Les deux alertes calibrées sont intactes, et aucune de plus. Je vérifie maintenant à travers l'API réelle.

Le bénéfice est passé de **−7,1 M à +2,01 M** (19,5 % de marge), mesuré à travers l'API. Il me reste à voir le sélecteur de départ à l'écran — c'est la seule chose qu'aucun test ne couvre.

Rechargé et vérifié. Les quatre points sont livrés.

## Ce que la base dit maintenant

Mesuré à travers l'API réelle (`/api/stats/financiere`), pas déduit du code :

| poste | avant | après |
|---|---|---|
| Recettes | 2 631 600 | **10 309 600** |
| Approvisionnements | 5 920 000 | 4 966 000 |
| Dépannages | 1 944 000 | 1 944 000 |
| Dépenses | 1 775 650 | 1 385 850 |
| **Bénéfice net** | **−7 156 050** | **+2 013 750** |
| Marge | — | **19,5 %** |

Les billets valides passent de 252 à **1 007**, soit 26 à 44 par départ sur des cars de 50 à 70 places — un remplissage de transporteur.

Et les deux alertes calibrées sont exactement là où `DepannageFixtures` les attend, sans une de plus :

| pièce | stock | seuil | état |
|---|---|---|---|
| Batterie 12V 150Ah | 0 | 2 | **RUPTURE** |
| Plaquette de frein | 8 | 10 | **FAIBLE** |
| Pneu 315/80 R22.5 | 12 | 6 | ok |
| *les trois autres* | — | — | ok |

Le pneu à 12 est le résultat voulu de la commande ramenée à 18 : D2 en consomme 6, et le stock devait rester **strictement** au-dessus de 6 sous peine d'ajouter une alerte au scénario.

## Le sélecteur de frais de route, côté données

`/api/voyages` sur la fenêtre retenue sert **11 départs**, triés du plus récent au plus ancien, avec les quatre champs dont l'étiquette a besoin :

```
LI-ABI-KOR-0001-V3    · départ 1 · 25/09 à 07h00
LI-ABI-KOR-0001-V4    · départ 1 · 24/09 à 13h32
LI-ABI-DAL-0002-V1    · départ 1 · 24/09 à 13h02
…
```

Tous à « départ 1 » : `HistoriqueFixtures` ne lance qu'un car par jour sur cette ligne. C'est juste, pas un défaut.

## Ce qu'il reste, et pourquoi je ne l'ai pas fait

Le rendu de l'écran est la seule chose qu'aucun test ne couvre — et c'est précisément là que le bug du mois dernier s'était caché (l'alias `t` écrasé, visible en agent de gare seulement). Je n'ai pas saisi de mot de passe dans le formulaire : je ne le fais pas, même sur un compte de démonstration.

Les deux serveurs tournent, et le panneau est sur la page de connexion. Si tu te connectes — **`chef.abidjan@ira-transport.ci`** est le profil qui compte, c'est lui qui gagne les deux nouveautés — je lis l'écran derrière toi :

- `/depense/nouvelle` : le champ « Frais de route — départ concerné » doit lister les 11 départs ;
- `/depense` : le bouton **Supprimer** doit apparaître dans le menu d'une ligne, ce qui lui était refusé hier ;
- `/` : le bloc « Résultat d'exploitation » doit enfin afficher du vert.

Pour référence — backend : [CourrierRepository.php](Backend-Transport/src/Repository/CourrierRepository.php), [Depense.php](Backend-Transport/src/Entity/Depense.php), [HistoriqueFixtures.php:189](Backend-Transport/src/DataFixtures/HistoriqueFixtures.php#L189), [StockFixtures.php](Backend-Transport/src/DataFixtures/StockFixtures.php), + `RecetteCourrierTest` et 2 tests dans `DepenseTest` ; frontend : [DepenseController.php](Frontend-Transport/src/Controller/DepenseController.php), [DepenseFormType.php](Frontend-Transport/src/Form/DepenseFormType.php), [DepenseTable.tsx](Frontend-Transport/assets/react/controllers/Finance/DepenseTable.tsx). **239 tests verts**, README et plan de la caisse à jour.







## Plan


# Soldes et caisse — ce que la compagnie détient

> **Plan validé le 2026-09-23 · mise en œuvre EN ATTENTE** — l'utilisateur donnera le départ.
>
> Le chantier précédent (module Dépenses) est livré : backend, frontend et documentation. Ce plan
> porte sur A1 de la feuille de route, la clôture de caisse.

## Contexte

L'application sait au franc près ce qu'un guichet **aurait dû** encaisser : `RecetteGareService`
compose la recette par gare et par canal, et le dernier chantier a branché les dépenses en face.
Mais rien ne rapproche ce chiffre de **l'argent réellement dans le tiroir**. Pour une compagnie où
presque tout se paie en espèces, c'est le manque le plus coûteux de la feuille de route : un écart de
caisse ne se détecte aujourd'hui que par recoupement manuel, a posteriori, et sans rien d'opposable à
l'agent.

Trois trous rendent ce rapprochement impossible en l'état :

- **aucune recette ne dit comment elle a été payée** — un billet, un bagage, un courrier n'ont pas de
  mode de règlement ;
- **le remboursement d'un désistement n'est chiffré nulle part** — le billet passe `ANNULE`, la
  recette baisse rétroactivement, la sortie d'espèces ne laisse aucune trace ;
- **les frais de suivi d'un courrier** (`Courrier::$fraissuivi`) sont saisis et encaissés mais
  n'entrent dans **aucun** calcul de recette : aucun repository ne les somme.

Ce chantier pose d'abord le **SOLDE** — ce qu'une gare et l'entreprise détiennent réellement, à
partir d'un solde initial saisi à leur création. Puis la **session de caisse** par agent, qui n'est
pas un autre pot d'argent mais une période de responsabilité : elle dit si le compte de tel
guichetier tombe juste ce soir. Puis les **versements** d'espèces d'une gare vers le siège. Et il
corrige au passage l'absence des frais de suivi dans la recette.

Les deux notions répondent à deux questions distinctes, et aucune ne remplace l'autre : le
**résultat** (recettes − dépenses) dit si l'affaire est rentable, le **solde** dit ce qu'il y a dans
le coffre ce soir.

## Décisions actées

- **Tout encaissement au guichet est réputé espèces.** Aucun champ de mode de règlement n'est ajouté
  à `Ticket`, `Bagage`, `Courrier` : ce serait alourdir le geste le plus fréquent de l'application.
  Un paiement mobile exceptionnel produira un écart, que l'agent justifiera par un motif.
- **Aucune garde bloquante.** Un agent peut vendre sans avoir ouvert sa caisse : une session lui est
  alors ouverte automatiquement, fonds à zéro. Le guichet ne s'arrête jamais sur une procédure
  oubliée, et aucune vente ne reste orpheline.
- **Le commercial à bord est hors périmètre** — `commercialflutter` n'est pas touché. Sa remise
  d'espèces se traitera plus tard, probablement comme un versement à une gare.
- **Les versements gare → siège sont dans le périmètre** : sans eux la caisse du siège reste vide et
  le solde d'une gare ne baisse jamais.
- **Frais de suivi** : comptés dans la caisse **et** réparés dans la recette.
- **L'agent remet TOUT à la clôture.** Son tiroir repart à zéro ; le chef de gare lui avance un
  fonds à chaque prise de poste. Un écart appartient donc toujours à une personne et à une journée.
- **Une dépense de gare sort TOUJOURS du coffre du chef de gare**, jamais du tiroir d'un guichet.
  Conséquence directe : le module Dépenses livré n'est PAS modifié — aucune colonne de session à y
  ajouter. Une dépense pèse sur le solde de la gare, pas sur la caisse d'un agent.
- **L'annulation ne rembourse pas** — ni bagage, ni courrier, ni les bagages annulés en cascade avec
  un billet désisté. Seul le **désistement d'un billet** rend de l'argent. Le remboursement pour
  **perte** existe dans la vraie vie mais relève de B4 (réclamations et indemnisations), qui n'est pas
  modélisé : le mécanisme est posé, pas branché.

## Modèle

**`Sessioncaisse`** — `extends EntityBase implements EntrepriseOwnedInterface, GareOwnedInterface,
HasSoftDeleteGuard`, patron [Depense.php](Backend-Transport/src/Entity/Depense.php).

| champ | type | note |
|---|---|---|
| `agent` | FK `User`, non nul | le poste : c'est la seule identité d'une caisse |
| `gare` | FK `Gare`, non nul | `gareScopeField()` |
| `datedebut` / `datefin` | `datetime_immutable` (fin nullable) | |
| `fondsouverture` | `bigint`, défaut 0 | |
| `ouvertureautomatique` | `bool` | l'agent n'a pas ouvert sa caisse — alimente l'alerte |
| `montanttheorique` + 8 totaux par poste | `bigint` nullable | **figés à la clôture** |
| `montantcompte` | `bigint` nullable | l'espèce réellement comptée |
| `ecart` | `bigint` nullable, **signé** | `montantcompte − montanttheorique`, pas d'`Assert\Positive` |
| `motifecart` | string(255) nullable | **obligatoire dès que l'écart ≠ 0** |
| `statut` | string(20) | enum `OUVERTE → CLOTUREE`, pas de réouverture |
| `agentsessionouverte` | int nullable | porte l'index unique, remis à null à la clôture |

La caisse a **deux états seulement** : `OUVERTE`, puis `CLOTUREE` et c'est fini. Pas de visa, pas de
réouverture — si l'agent reprend la vente après avoir clôturé, sa première écriture lui ouvre une
NOUVELLE session et la clôture précédente reste intacte.

**Le théorique est PERSISTÉ**, contre la doctrine maison du « rien de dérivable stocké » : la clôture
est une pièce opposable. Un tarif corrigé ou une annulation tardive déplaceraient un chiffre
recalculé, et l'écart signé par l'agent ne voudrait plus rien dire. Même exception assumée que
`Ticket::$desistementImputableCompagnie`.

**`soldeinitial`** (`bigint`, défaut 0) sur **`Gare`** ET sur **`Entreprise`** : l'encaisse dont on
part à l'ouverture. Ni l'une ni l'autre n'a aujourd'hui le moindre champ de ce genre. C'est une
DONNÉE saisie, pas un compteur : le solde courant se recalcule à la lecture, sinon il dériverait à la
première dépense corrigée — même doctrine que `ProgrammeFidelite` (« aucun compteur stocké, aucune
dérive »).

**`Versement`** : `gare` (non nul), `montant`, `dateversement`, `statut` (`EMIS/ACCEPTE/REFUSE`), `accepteur`,
`dateacceptation`, `montantrecu`, `motifecart`, `motifrefus`, `justificatif`, `reference` unique par
entreprise.

**Aucune entité `Caisse`** : le solde d'une gare et celui du siège sont **dérivés**. Le siège n'a pas
de guichet — lui ouvrir des sessions créerait des caisses que personne ne compte jamais.

Index déclarés **dans le mapping** (la base de test est bâtie par `doctrine:schema:update`) :
`(identreprise, gare, datedebut)`, `(agent, statut)`, et l'unique `(identreprise, agentsessionouverte)`.

## Le rattachement — FK explicite

Chaque écriture d'espèces porte la session qui l'a vue passer, **posée à l'écriture** :

| entité | colonnes | posée par |
|---|---|---|
| `Ticket` | `sessioncaisse`, `sessioncaisseremboursement`, `montantrembourse` | [TicketProcessor.php](Backend-Transport/src/State/TicketProcessor.php), [DesistementProcessor.php](Backend-Transport/src/State/DesistementProcessor.php) |
| `Bagage`, `Courrier` | `sessioncaisse` | leurs processors |
| `Reservation` | `sessioncaisse`, `sessioncaisseregul` | `ConfirmerReservationProcessor`, `RegulariserReservationProcessor` |

`Depense` n'y figure PAS : une dépense sort du coffre, pas d'un tiroir de guichet. Le module livré
reste intact.

Le rattachement **dérivé** (agent + intervalle de temps) est écarté : il rendrait 0 ou 2 caisses
selon les bornes, casserait sur une vente antidatée — cas qui existe déjà en interne, `HorodatageTrait`
réécrit `createdAt` en DQL — et ne survivrait pas à une relecture des mois plus tard. « Un billet
appartient toujours à exactement une caisse » doit être une colonne, pas un calcul.

**`NULL` = hors caisse**, et c'est ce qui remplace tous les filtres du module Recette : la vente du
commercial, le billet émis depuis un bon, le billet de report (aucun argent ne bouge) et le paiement
mobile n'ont simplement pas de session. **Deux colonnes sur `Ticket` et `Reservation`** parce que ce
sont deux événements distincts sur la même ligne : la vente lundi, le remboursement jeudi.

**Ne poser aucun `Groups`** sur ces FK — avec `skip_null_values: false`, chaque billet traînerait
`sessioncaisse: null`, et la session d'un collègue fuiterait dans la réponse. Un `SearchFilter` sur
`sessioncaisse.id` suffit au frontend.

**Aucun backfill** : fabriquer un rattachement rétroactif serait une falsification sur une pièce de
preuve. Le module démarre le jour du déploiement.

## L'ouverture automatique

`SessioncaisseService::courante(User): ?Sessioncaisse`, source unique appelée par les processors :

- l'acteur **sans gare** (admin, central) → `null` : son écriture reste hors caisse ;
- mémoïsation par agent (une vente écrit un billet puis un bagage) ;
- `wrapInTransaction` + `lock($user, PESSIMISTIC_WRITE)` — le verrou porte sur la ligne `user`, donc
  seules les écritures du même agent s'attendent ;
- sinon création avec `fondsouverture = 0`, `ouvertureautomatique = true`, audit dédié.

**Piège de deadlock** : `TicketProcessor` verrouille déjà le voyage. La session doit être résolue
**avant** d'entrer dans ce `wrapInTransaction`, pour que l'ordre soit toujours `user → voyage`.

## Théorique et écart

```
théorique = fondsouverture
          + Σ Ticket.prix                                   (sessioncaisse)
          + Σ Bagage.montant                                (sessioncaisse)
          + Σ Courrier.montant + COALESCE(fraissuivi, 0)    (sessioncaisse)
          + Σ Reservation.prix                              (sessioncaisse)
          + Σ Reservation.penalitemontant + montantcomplement (sessioncaisseregul)
          − Σ Ticket.montantrembourse                       (sessioncaisseremboursement)

écart = montantcompte − théorique        (signé : négatif = manquant)
```

La caisse d'un agent ne connaît donc que des ENTRÉES et un seul type de sortie : le remboursement
qu'il rend de son tiroir à un client qui se désiste. Dépenses et versements sortent du coffre et
pèsent sur le solde de la gare, pas sur sa caisse.

**Aucun filtre sur `statut`** — c'est le point le plus contre-intuitif du module. Toutes les requêtes
existantes portent `t.statut = 'VALIDE'` ; ici c'est interdit. L'argent est entré à la vente ; une
annulation ne l'efface pas, elle le ressort par la ligne de remboursement. Filtrer sur `VALIDE`
ferait disparaître une vente et son annulation du même jour **des deux côtés**, masquant deux
mouvements réels. À écrire dans le docblock du service et à verrouiller par un test.

**La caisse ne s'ajoute à aucun total existant.** Elle ne crée ni recette ni charge : elle rapproche.
Ne jamais soustraire un écart du bénéfice — ce serait le double comptage technique que le README
interdit.

## Soldes et versements

**Le solde est le chiffre de tête**, celui que le chef de gare et le patron regardent en premier.
Il se dérive, il ne se stocke pas :

```
solde gare = Gare.soldeinitial
           + Σ montantcompte des sessions CLOTUREE        (ce que les agents ont remis)
           − Σ fondsouverture de TOUTES les sessions      (ce que le chef leur a avancé)
           + Σ fondsouverture des sessions CLOTUREE       (rendu avec le comptage)
           − Σ Depense.montant  (gare, ESPECES)
           − Σ Versement.montant  (EMIS ou ACCEPTE)

en transit = Σ Versement.montant  (EMIS)

solde entreprise = Entreprise.soldeinitial
                 + Σ Versement.montantrecu  (ACCEPTE)
                 − Σ Depense.montant  (gare NULLE, ESPECES)
```

Les deux lignes de `fondsouverture` se simplifient en « − fonds des sessions encore OUVERTES » : ce
que le chef a avancé aux guichets ouverts n'est plus dans le coffre, mais il est toujours dans la
gare. Le solde affiché est celui de la GARE (coffre + tiroirs en cours), parce que c'est la question
posée : « combien la gare détient-elle ? »

La gare **émet** un versement (imputation forcée à sa gare, `DepenseProcessor::imputer()` recopié),
le **siège accepte ou refuse**. Entre les deux, l'argent voyage physiquement et n'est chez personne :
un versement crédité d'office ferait apparaître au siège un solde qu'il ne détient pas, et le montant
**en transit** est précisément ce qu'on veut voir. `montantrecu ≠ montant` → motif obligatoire.

`/accepter` et `/refuser` sont gardés par **`ROLE_ADMIN` + garde de siège**, et non par une
permission d'entité : avec le bypass `ROLE_ADMIN_GARE`, un chef de gare accepterait ses propres
versements. Précédent exact : `Remove_Depense`.

**Deux pièges de double comptage** : une dépense payée par virement ou mobile money ne sort d'aucun
coffre (filtrer sur `ESPECES`) ; et une dépense de siège créée par un admin qui a une gare doit se
décider sur `Depense::$gare`, jamais sur la gare de son auteur.

**Le solde ne se confond pas avec le résultat.** Il ne remplace ni la recette ni le bénéfice : il dit
ce qu'on détient, pas ce qu'on a gagné. Ne jamais l'ajouter à `FinancierStatsProvider`.

## Sécurité

`Sessioncaisse` et `Versement` entrent dans `GareScopedEntities` — l'admin de gare vise les caisses
de sa gare, c'est son métier, et il peut déléguer à un chef de guichet. Les **trois listes
dupliquées** restent à tenir d'accord (backend, `ApiUser`, `commercialflutter`).

Une action dédiée, **`CLOTURER`** : sous `MODIFIER`, tout profil autorisé à rectifier une saisie
pourrait arrêter la caisse d'un collègue. Trois endroits à compléter, sinon l'action reste
inassignable : le catalogue du `PermissionVoter`, `RoleFormType::ACTIONS_SPECIFIQUES`, et
`ActionsDedieesTest::gardesAttendues()`.

**Un agent ne voit pas la caisse d'un collègue** : `CaisseScopeExtension` (patron
`AlerteAudienceExtension`) borne à `agent = moi` pour qui n'est ni admin, ni admin de gare, ni
central. `CaisseGuard` limite la clôture au titulaire de la caisse, à son admin de gare ou à un
admin. Et `Sessioncaisse`/`Versement` vont dans les exclusions de `CorbeilleRegistry` : on ne met pas
une preuve à la corbeille.

**Pas de visa.** La clôture arrête la caisse, définitivement — deux statuts, pas trois. Le contrôle
hiérarchique se fait par la LECTURE : l'écran des sessions se filtre sur les écarts, et l'alerte
`CAISSE_ECART_ELEVE` remonte en portée DIRECTION, comme les alertes anti-fraude existantes. Le ticket
imprimé garde en revanche **deux lignes de signature** : recevoir l'argent reste un geste physique,
même sans acte dans l'application.

## Frais de suivi — la réparation

`Courrier::$fraissuivi` entre dans le théorique **et** dans la recette : `CourrierRepository`
(`recetteParGare`, `recettesTotales`, `recettesParAgent`, `recetteParGareEtAgent`, séries par jour)
somme désormais `montant + COALESCE(fraissuivi, 0)`. Conséquence assumée à écrire au README : les
recettes courrier affichées augmentent, parce qu'elles étaient incomplètes.

## Ordre de livraison

**Palier 1 — les soldes.** `soldeinitial` sur `Gare` et `Entreprise` (migration + formulaires de
création), `SoldeService`, `GET /api/gares/me/solde` et `/api/caisse/entreprise`. Livrable seul et
déjà utile : le chef de gare voit ce que sa gare détient, à partir des ventes et des dépenses qui
existent déjà. *Vérifié : le solde d'une gare bouge d'exactement le montant d'une vente puis d'une
dépense en espèces.*

**Palier 2 — le rattachement.** Enums, `Sessioncaisse`, repository, migration,
`SessioncaisseService` avec son verrou, FK branchées dans les processors de vente. *Vérifié : une
vente au guichet crée et rattache une session, celle du commercial non.*

**Palier 3 — la clôture.** `CaisseTheoriqueService`, requêtes par poste, `/cloturer`, gel des totaux,
écart, motif obligatoire. Frais de suivi dans la caisse et dans la recette. *Vérifié : le théorique
égale la somme des pièces qu'on peut lister.*

**Palier 4 — remboursements et périmètre.** Remboursement du désistement, réservations et
régularisations, `CaisseScopeExtension`, `CaisseGuard`, permission `CLOTURER`, audit. *Vérifié :
vente + annulation le même jour laissent le théorique inchangé ; un agent ne voit pas la caisse d'un
collègue.*

**Palier 5 — versements.** `Versement`, `/accepter`, `/refuser`, branchement sur les deux soldes.
**Arrêt pour validation sur données réelles.**

**Palier 6 — frontend.** Écran de SOLDE en tête (gare et entreprise), puis `CaisseController`,
écrans « ma caisse », ouverture, clôture avec **grille
de dénominations** (locale au navigateur, seul le total part au serveur), liste des sessions avec
filtre sur les écarts, versements, caisse du siège. Menu « Finances » — sa condition d'affichage doit gagner
`SESSIONCAISSE_VOIR`, sinon un caissier sans droit Dépense ne verra pas le groupe. Impression : un
**ticket thermique** signé à la clôture (agent + chef de gare) et un récapitulatif A4 par gare.

**Palier 7 — alertes, fixtures, documentation.** `CAISSE_NON_CLOTUREE` (gare, avertissement),
`CAISSE_ECART_ELEVE` (direction, anti-fraude), `VERSEMENT_EN_TRANSIT_PROLONGE`. Fixtures
déterministes : une session close sans écart, une avec écart négatif motivé, une ouverte
automatiquement, un versement en transit, un accepté avec manquant.

## Vérification

```bash
php bin/console doctrine:migrations:migrate --no-interaction
make test-db && make test-api
```

Les sentinelles (`tests/Api/CaisseTest.php`, `VersementTest.php`, `tests/Domain/CaisseTheoriqueServiceTest.php`) :

1. une vente sans session ouverte en crée **une seule** ; deux ventes du même agent aussi ;
2. la vente du commercial — en ligne **et** via `/sync` — ne crée aucune session ;
3. le billet émis depuis un bon n'en a pas non plus (anti double comptage) ;
4. **vente + annulation le même jour → théorique inchangé** : la sentinelle du « pas de filtre statut » ;
5. annulation le lendemain → la session de vente garde son chiffre, celle du jour porte la sortie ;
6. une dépense `ESPECES` baisse le SOLDE de la gare sans toucher au théorique d'aucune caisse (elle
   sort du coffre) ; une `MOBILE_MONEY` ne baisse ni l'un ni l'autre ;
7. écart ≠ 0 sans motif → refus ; théorique figé insensible à une correction post-clôture ;
8. un agent ne voit pas la caisse d'un collègue, et ne clôture pas la sienne sans la permission ;
9. versement : le solde de la gare baisse à l'émission, celui de l'entreprise ne monte qu'à
   l'acceptation, et le montant en transit vaut la différence ;
10. le solde d'une gare part de son `soldeinitial` et non de zéro.

Puis, à la main : ouvrir une caisse, vendre, clôturer avec un écart, imprimer le ticket, relire la
session depuis le compte du chef de gare, émettre un versement et l'accepter en admin. **Tester avec
les deux profils** — agent de gare et admin —, la leçon du chantier précédent.

## Hors périmètre

- **Le commercial à bord** : aucune session, `commercialflutter` intouché.
- **Aucun mode de règlement** sur les ventes.
- **L'indemnisation pour perte** d'un bagage ou d'un courrier : c'est B4 de la feuille de route
  (réclamations), non modélisé. Les colonnes de remboursement du billet serviront de patron le jour
  venu.
- L'intégration de l'écart au bénéfice, la reprise de l'historique antérieur au déploiement, le
  rapprochement bancaire, et `Depense::$voyage` (frais de route).














- Concernant les prudences que tu as tenues :
    - Je ne suis pas d'accord avec le `Le chiffre est borné par ton périmètre. GareScopeExtension écrit d.gare = :id sur les dépenses : un agent de Bouaké qui ouvre un départ d'Abidjan voit zéro. En pratique le forfait est remis par la gare qui lance le car, donc c'est complet pour elle et pour un admin — mais l'écran le dit, plutôt que de laisser croire au coût complet du voyage` ! vu que ce sont les dépenses liées au voyage donc un agent de Bouaké devrait voir la même choses sur la fiche du voyage !

- Aussi, quand j'ai dit `On vas afficher les frais de routes du voyage sur la fiche de voyage` je faisait allusion aux dépenses liées au voyage, j'espère que tu as compris !

Aussi, je crois qu'il y'a un bug avec les filtres : Quand je me connecte en tant que `chef.abidjan@ira-transport.ci` et que je vais sur le voyage `LI-ABI-KOR-0001-V3` la recette c'est `38000 FCFA`, alors que quand je me connecte en tant que `chef.korhogo@ira-transport.ci` je vois `30000 FCFA` !






Les points 1 et 3 ont la même racine, et tu as raison sur le fond : la fiche d'un voyage doit montrer **le voyage**, pas la part du voyage qui appartient à ma gare. Je mesure avant de conclure.

La mesure confirme le bug et l'explique exactement.

Le calcul doit descendre côté API : c'est le seul endroit où on peut le sortir du périmètre de gare et le tester. Je regarde ce qui est réutilisable.

C'est clair. Je descends le calcul côté API, hors périmètre de gare, avec les règles canoniques de `RecetteGareService`.

Maintenant le service, le DTO et l'opération.

Il me faut aussi les lignes de dépense, sinon le tableau et son total ne se recouperont pas.

Je branche les lignes dans le provider.

Maintenant le test qui reproduit ton bug — c'est lui qui prouve le correctif.

5/5. Je verrouille en plus la raison d'être de l'endpoint : que le chemin scopé, lui, diverge bien.

Le backend est prouvé. Je rebranche la fiche voyage dessus.

La même divergence touche le remplissage. Je regarde si `CapaciteService` peut le servir aussi, sinon je laisserais un demi-correctif.

Backend prouvé. Je rebranche le frontend dessus.

Maintenant le gabarit, avec le nom que tu as précisé : les dépenses du voyage.

Je vérifie maintenant le résultat réel, avec la mesure qui a servi à trouver le bug.

Pour mesurer sur tes données sans saisir de mot de passe, je passe par une commande temporaire qui endosse chaque identité.

Mesuré sur tes données : **38 000 pour les trois comptes**, là où Korhogo lisait 30 000.

Tu avais raison sur les deux points, et le second était un bug plus large que les dépenses.

## Le bug de recette : trouvé, expliqué, corrigé

**38 000 − 30 000 = 8 000**, et je peux nommer les 8 000 : une **réservation payée Adjamé → Bouaké** sur ce départ. `Reservation` est de périmètre **multi-gares** (`['gare', 'garedescente']`) — Korhogo n'est ni la montée ni la descente, donc il ne la voit pas.

La cause n'était pas un filtre mal écrit, mais **l'endroit du calcul** : la fiche additionnait les collections d'API (`/api/tickets?voyage.id=`, `/api/reservations?voyage.id=`…), et `GareScopeExtension` filtre toute collection d'une entité de périmètre. La page ne montrait pas le voyage : elle montrait **la part du voyage qui appartient à ma gare**.

Et le même mal touchait le **remplissage** — tu l'aurais trouvé juste après.

## Ce que j'en ai tiré comme règle

La recette, l'occupation et les dépenses d'un départ sont des propriétés **du départ**. Deux personnes qui ouvrent la même fiche lisent le même chiffre. Le calcul descend donc côté API : `GET /api/voyages/{id}/resultat`, servi hors périmètre de gare.

Mesuré sur tes données, en endossant les trois identités :

```
admin@ira-transport.ci          recette 38 000 | resa 23 000 | dépenses 5 000 | pic 2
chef.abidjan@ira-transport.ci   recette 38 000 | resa 23 000 | dépenses 5 000 | pic 2
chef.korhogo@ira-transport.ci   recette 38 000 | resa 23 000 | dépenses 5 000 | pic 2
```

Quatre garde-fous, parce qu'ouvrir un périmètre n'est pas l'abandonner :

| | |
|---|---|
| **Visibilité du voyage** | toujours bornée — **404** sur un départ d'une ligne qui ne dessert pas sa gare, sinon la route servirait la recette de n'importe quel départ |
| **Permission** | sans `DEPENSE_VOIR`, l'API répond `depenses: null` — **null et non zéro**, un zéro ferait lire un résultat égal à la recette |
| **Liste nominative des billets** | reste bornée par la gare : ouvrir des *totaux* n'ouvre pas une liste de passagers |
| **Imputation** | inchangée — la charge reste portée par sa gare dans tous les résultats par gare, et la colonne « Imputation » l'affiche |

## Deux règles qui divergeaient en silence

En déplaçant le calcul j'ai trouvé que la fiche ne comptait **pas comme les repositories** :

- elle excluait les bagages **`PERDU`** de la recette — alors qu'ils ont été payés (l'indemnisation est une autre écriture, qui n'existe pas encore) ;
- elle oubliait les **frais de suivi** d'un courrier.

Tout suit maintenant `RecetteGareService`, y compris la règle anti-double-comptage : un billet né d'une réservation ne porte pas la recette, c'est le **paiement de la réservation** qui la porte. C'est pour ça que tu verras `billets 15 000` et `réservations 23 000` plutôt que l'inverse — même total, définition canonique.

L'occupation vient désormais de `CapaciteService`, avec une méthode `occupationParTroncon` : les barres par tronçon et le pic ne peuvent plus se contredire.

## Ta clarification sur le nom

Compris, et j'avais restreint à tort. Ce sont **les dépenses liées au voyage**, pas seulement un poste « frais de route » :

- la section s'appelle **« Dépenses du voyage »** ;
- le champ du formulaire s'appelle **« Départ concerné »**, avec l'aide « frais de route remis à l'équipage, péage, imprévu de la route » — le cas type, pas une restriction.

## Vérification

**249 tests verts.** Le nouveau [ResultatVoyageTest.php](Backend-Transport/tests/Api/ResultatVoyageTest.php) (6 tests, 47 assertions) reproduit exactement ton scénario et verrouille **les deux sens** : que les trois lecteurs obtiennent le même chiffre, **et** que la collection scopée, elle, diverge bien — sinon quelqu'un « simplifierait » un jour le provider en rappelant `/api/reservations?voyage.id=` et le bug reviendrait sans qu'aucun test ne bronche.

Fichiers : [VoyageResultatService.php](Backend-Transport/src/Domain/Service/VoyageResultatService.php), [VoyageResultatProvider.php](Backend-Transport/src/State/VoyageResultatProvider.php), [VoyageResultatDto.php](Backend-Transport/src/Entity/Output/Exploitation/VoyageResultatDto.php), `CapaciteService::occupationParTroncon`, cinq agrégats `…PourVoyage` dans les repositories, [VoyageController.php](Frontend-Transport/src/Controller/VoyageController.php) (la fiche perd ~90 lignes de calcul), [show.html.twig](Frontend-Transport/templates/voyage/show.html.twig).

Les serveurs tournent, le panneau est sur la connexion. Le contrôle qui compte : ouvrir `LI-ABI-KOR-0001-V3` avec **chef.abidjan** puis **chef.korhogo** — recette, remplissage et dépenses doivent être identiques, et le tableau « Dépenses du voyage » doit afficher les 5 000 F déjà rattachés, avec leur gare d'imputation.





Je passe à la classe dangereuse — les totaux composés à partir de collections scopées.










Deux problèmes distincts se cachent derrière « les filtres ». Je vais chercher les deux.

Mon extracteur a des faux positifs (il attrape des clés de tableaux de rendu). Je vérifie les candidats réels un par un.

Le cœur du sujet, c'est le `SearchController` : c'est là que vivait le bug. Je vérifie ses huit ressources une par une.

Deux trouvailles. Je les confirme par un test avant de conclure.

`Gare` a `paginationEnabled: false` — donc l'impact dépend de qui consomme le sélecteur. Je vérifie.

Classe A close : le seul paramètre non déclaré est sur une entrée morte. Je passe à la classe dangereuse — les totaux composés à partir de collections scopées.

Je mesure plutôt que de raisonner : y a-t-il vraiment des écarts sur tes données ?

Mesuré : l'écart sur les billets annulés est réel et partout ; les lignes supprimées sont à zéro sur ce jeu. Je regarde maintenant les autres fiches d'objets partagés.

Je vérifie aussi les trois apps mobiles — elles parlent à la même API.

Bilan de l'audit : deux choses à corriger, le reste vérifié sain. Je regarde ce que Twig peut filtrer.

`deletedAt` n'est pas exposé — Twig ne peut donc rien filtrer. Le correctif doit être à la source.

Le tableau des billets n'affiche même pas le statut : un désisté y est indistinguable d'un passager. Je corrige les deux bouts.

Le getter est bien sérialisé mais renvoie du vide. Je mesure plutôt que de deviner.

C'est un artefact de test, pas un bug : la collection est déjà chargée en mémoire, vide. Je vérifie comment `ApiTestCase` gère ça.

C'était bien l'identity map partagée. Je remets le getter simple et je rafraîchis dans le test.

Reste deux corrections. D'abord `Gare`, qui n'a aucun filtre déclaré.

Backend audité et réparé. Maintenant les deux incohérences visibles à l'écran.

Il me reste à vérifier les autres fiches qui sérialisent une collection — je ne veux pas laisser ça en suspens.

Huit autres collections sérialisées ont des enfants qui peuvent aller en corbeille. Je mesure d'abord s'il en existe.

Zéro ligne en corbeille sur tout le jeu — les huit sont donc latents. Je regarde les deux qui pourraient mordre le plus fort.

Faux positifs : mon heuristique attrapait le `Groups` de la propriété voisine. Je resserre.

Vérifié jusqu'au bout : ces trois-là ne risquent rien. Je corrige la mémoire qui disait le contraire.

J'ai cherché les **deux** problèmes que « les filtres » recouvrait, sur les 42 entités, le frontend et les trois apps mobiles.

## Piège 1 — le filtre non déclaré

Méthode : extraire les filtres **déclarés** (`#[ApiFilter]`) et les paramètres **envoyés** (`handleIndex`, `collection()`, URL en dur, `http_build_query`), puis croiser. 246 usages, 26 endpoints.

**Une seule vraie trouvaille : `Gare` ne déclarait aucun filtre.** Le `SearchController` cherchait pourtant sur `?libelle=`. Même bug que `provenance`, mais **pas encore visible** — aucun écran n'est branché sur cette ressource, et `paginationEnabled: false` renvoie toutes les gares que tom-select filtre alors localement. Le premier sélecteur **React** aurait cassé : `RemoteCombobox` pose `shouldFilter={false}` et s'en remet au serveur. Fermé avant qu'il ne serve.

Vérifié conforme par ailleurs :

| | |
|---|---|
| Les 6 autres ressources du sélecteur distant | `fournisseurs`, `pieces`, `cars`, `personnels`, `lignes`, `users` — tous déclarés |
| Les 3 apps mobiles | `/api/tarifs` (`garedepart.id`, `garearrivee.id`) déclarés ; `/api/sieges` lit ses 4 paramètres dans `SiegeStateProvider` |
| `personnel.id` | pris en charge par `PersonnelFilter` |
| `/api/gares/me/dashboard?periode=` | lu par son contrôleur |

Deux faux positifs à ne pas confondre avec des bugs : `Car` et `Personnel` ignorent `itemsPerPage`, mais ils ont `paginationEnabled: false` — rien n'est tronqué.

## Piège 2 — un troisième que je ne cherchais pas

En traquant les totaux composés, j'en ai trouvé un autre, de la même famille : **une association Doctrine ne connaît pas la corbeille.**

Aucun filtre SQL de softdelete n'existe dans ce projet — la suppression logique est tenue par les **extensions** d'API Platform, qui ne s'appliquent jamais à un `OneToMany` hydraté par Doctrine. `Voyage::$tickets` exposait donc des billets en corbeille, invisibles partout ailleurs, `getTicketsCount()` compris qui filtre lui.

Et ça produisait une incohérence **mesurable aujourd'hui** :

```
LI-ABI-KOR-0001-V11 :  liste des voyages → 41 billets
                       pastille de la fiche → 43
```

Les 2 de différence sont des **désistements**. Pire : le tableau des billets n'affichait **aucun statut** — un désisté y était indistinguable d'un passager qui monte.

**Corrigé** : les quatre collections de la fiche sont servies par des getters `get…Visibles()` + `#[SerializedName]` (clé JSON inchangée) qui filtrent `deletedAt`. Les annulés restent listés — un désistement fait partie de l'histoire du départ — mais avec leur statut affiché, et les pastilles et totaux ne comptent que ce que la recette compte. Le « total bagages » sommait les annulés : corrigé aussi.

## Jusqu'où j'ai poussé

Sur les 42 entités, trois autres sérialisent une collection dont l'enfant porte un `deletedAt`. **Aucune n'est exposée**, vérifié :

- `Detailcourrier` et `Detailpersonnel` sont explicitement **hors corbeille** (`CorbeilleRegistry::EXCLUSIONS`, détails en cascade) ;
- `UserProcessor` supprime les anciens `UserRole` **en dur**, pas en logique.

Mesuré : **zéro ligne en corbeille** dans tout le jeu de données. `Voyage` était donc le seul cas réel.

Un dernier point pour l'honnêteté : `User::$userRoles` porte aussi `write:User`. On ne pourrait pas y déplacer le groupe vers un getter sans casser la dénormalisation — noté, au cas où `UserRole` entre un jour dans la corbeille.

## Un piège de test, pas de production

En écrivant la sentinelle, la fiche rendait `[]` alors que la base contenait les billets. Le test et la requête HTTP **partagent l'EntityManager** : l'objet du `setUp` porte une collection déjà initialisée à vide, et `matching()` filtre alors en mémoire sur cet ensemble vide. Il faut `em->refresh()` avant la requête. Mesuré avec une commande temporaire sur les vraies données (`getTicketsVisibles()` = 43) pour être sûr de ne pas corriger un bug qui n'existait pas.

## Vérification

**253 tests verts** (+4). Trois sentinelles nouvelles, et j'ai vérifié qu'elles tombent bien quand on remet le défaut :

| sentinelle | ce qu'elle garde | prouvée |
|---|---|---|
| [RechercheVoyageTest](Backend-Transport/tests/Api/RechercheVoyageTest.php) | un filtre déclaré filtre vraiment | 2 échecs si on retire `provenance` |
| [FicheVoyageTest](Backend-Transport/tests/Api/FicheVoyageTest.php) | corbeille ≠ annulé ≠ valide | 1 échec si on réexpose la collection brute |
| [ResultatVoyageTest](Backend-Transport/tests/Api/ResultatVoyageTest.php) | tous les lecteurs, le même chiffre | + vérifie que la collection scopée diverge bien |

La forme qui compte, dans les trois : **demander ce qui n'existe pas et exiger le vide**. Un test « la requête filtrée répond 200 » serait resté vert pendant tout ce temps.

README et mémoire à jour, avec la méthode d'audit pour qu'elle soit rejouable. Un détail sans lien : `lint:container` échoue sur le backend à cause de `vich_uploader` qui réclame `symfony/form`, absent du `composer.json` d'une API — structurel, pas dû à ces changements.













Ce n'était pas une fatalité. Symfony sait connecter un utilisateur sans mot de passe (loginUser()), et le frontend avait déjà phpunit et browser-kit — personne ne s'en était servi pour les contrôleurs. J'ai donc construit ce qui manquait :

Deux pièges rencontrés en construisant le harnais

Je les note parce qu'ils feront perdre du temps à quiconque écrira le prochain test de rendu :

- ApiUserProvider::refreshUser() relit l'utilisateur depuis la session à chaque requête. Sans les clés user/token/refresh_token, le test reçoit une redirection vers /connexion et on croit à un problème de droits.
- Il faut passer par $client->getSession(), qui réouvre la session du cookie. Un session.factory->createSession() en crée une seconde dont le cookie écrase _security_main — même symptôme, cause inverse