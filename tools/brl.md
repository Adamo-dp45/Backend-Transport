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







