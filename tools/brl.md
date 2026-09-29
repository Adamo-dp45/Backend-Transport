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










- Pourrait t'on affichés la somme des dépenses d'un voyage sur le listing des voyages ! si oui j'espère que ça ne pose pas de problème de performences !
- Aussi, on m'a parler d'une notion qui est : Les charges de main d'oeuvre externe de dépannage (du genre lors d'un dépannage, ils peuvent prendre des mains d'oeuvres externes pour les aidés) ! je me dis que ce sont aussi des dépenses ! Où est ce que je me trompe ?







Deux bonnes questions, et la seconde touche une doctrine déjà écrite. Je regarde le modèle avant de répondre.

Les deux réponses sont claires. Je livre la première (elle coûte une requête par page) et je te poserai une question sur la seconde.

Maintenant je mesure le coût réel — c'était ta question.

La mesure brute est noyée dans le bruit (41 puis 29 : le premier appel réchauffe les caches). Je cible les requêtes qui touchent `depense`.

Mesuré : **1 requête** que la page porte 1 ou 20 départs. Je vérifie que le test attrape bien un N+1.

La preuve est nette : **20 requêtes au lieu de 1** avec le N+1. Maintenant la colonne côté frontend.

**256 tests verts.** Voici les deux réponses.

## 1 · Les dépenses sur le listing — oui, et ça coûte une requête

C'est fait. La colonne « Dépenses » est sur le listing, et la performance était la bonne inquiétude : écrit naïvement, un `SUM` par ligne aurait donné **25 requêtes par page**.

Le provider rassemble d'abord les identifiants de la page, puis une **seule requête groupée** ramène tous les totaux. Le champ est dérivé et posé sur les entités, comme `horaires` et `Ticket::$evince` — rien n'est stocké.

**Mesuré**, parce que c'est la seule façon de le savoir :

| page | requêtes sur la table `depense` |
|---|---|
| 1 départ | **1** |
| 20 départs | **1** |
| *avec le N+1 réintroduit exprès* | *20* |

Un détail méthodologique qui vaut la peine d'être noté : le **total général** de requêtes ne mesure rien ici — il vaut 41 puis 29 sur deux appels identiques. Le premier appel réchauffe les caches de métadonnées, et la sérialisation charge paresseusement `ligne`/`car`/`gare` ligne à ligne — **un N+1 préexistant**, indépendant de ce champ. Le test compte donc uniquement les requêtes qui touchent `depense`.

Même règle que sur la fiche : sans `DEPENSE_VOIR`, l'API répond `null` et la colonne affiche un tiret — **pas un zéro**, qui laisserait croire à un départ sans frais.

Si tu veux aussi la **recette** sur le listing, c'est le même mécanisme mais 4 requêtes groupées de plus (billets, réservations, bagages, courriers). Dis-le si ça t'intéresse.

## 2 · La main d'œuvre externe : tu as raison sur le fond, pas sur le tiroir

**C'est bien une charge**, sans discussion : de l'argent qui sort. Là où je te contredirais, c'est sur *où l'enregistrer*.

Le README pose une doctrine que tu as validée en septembre : **trois postes disjoints**, `bénéfice = recettes − dépannages − appros − dépenses`, et « un dépannage ne produit JAMAIS de dépense ». Si la main d'œuvre part dans `Depense` :

- le poste « dépannages » **sous-estime** ce qu'une panne coûte réellement ;
- la fiche du car ment : un véhicule réparé par des mains externes paraît **bon marché** ;
- et les deux chiffres restent **individuellement corrects** — donc personne ne s'en aperçoit. C'est exactement le motif des trois pièges qu'on vient de traiter.

Ma lecture : la main d'œuvre externe fait partie du **coût de l'intervention**, donc du dépannage.

Une asymétrie qui semble bizarre mais qui tient : le personnel **interne** d'un dépannage (`Detailpersonnel`) ne porte aucun coût — c'est du salaire, déjà compté en `Depense` au siège. L'externe est une facture ponctuelle attachée à l'intervention. Charge fixe contre sous-traitance : c'est la distinction comptable normale.

**Et un piège technique à ne pas rater.** `Depannage::$couttotal` est **recalculé depuis les pièces à chaque écriture** (`DepannageProcessor`, deux endroits) :

```php
$total += $prixunitaire * $quantite;
$depannage->setCouttotal($total);
```

On ne peut donc pas y verser la main d'œuvre : la prochaine modification des pièces l'effacerait en silence. Il faut sa propre colonne, et `couttotal` devient `pièces + main d'œuvre`.

Il me manque deux décisions avant d'écrire quoi que ce soit.


Où enregistrer la main d'œuvre externe d'un dépannage ?

Dans le dépannage (Recommandé)

Quelle forme donner à la saisie ?

Une liste d'intervenants



Décisions notées. Je regarde le patron à suivre — `Detaildepannage` et la façon dont le processor reçoit ses lignes.

Le processor n'ajoute pas les lignes à la collection — il accumule un total local. Je garde ce fonctionnement et je fais retourner leur total à chaque gestionnaire.

Maintenant le processor — le point délicat : `couttotal` doit devenir pièces **plus** main d'œuvre, sans s'effacer quand on ne touche qu'à l'un des deux.

Maintenant la migration et la base de test.

Migration jouée. Maintenant la sentinelle qui garde la règle du coût total.

`ScenarioBuilder` n'a ni `referentiel()` ni `piece()`. Je les ajoute.

Les deux échecs sont une contrainte préexistante du DTO : le PATCH exige la charge utile complète, alors que le processor est écrit pour du partiel. J'adapte le test et je te le signale.

6/6. Je prouve que la sentinelle attrape la faute naïve.

Code rétabli, 6/6. Je lance la suite complète et je documente.

**262 tests verts.** Voici les deux réponses.

## 1 · Les dépenses sur le listing — oui, et le coût est mesuré

La colonne « Dépenses » est là. Ton inquiétude était la bonne : écrit naïvement, un `SUM` par ligne aurait donné **25 requêtes par page**. Le provider rassemble les identifiants de la page, puis **une seule requête groupée** ramène tous les totaux.

| page | requêtes sur la table `depense` |
|---|---|
| 1 départ | **1** |
| 20 départs | **1** |
| *avec le N+1 réintroduit exprès* | *20* |

Un point de méthode qui vaut d'être dit : le **total général** de requêtes ne mesure rien ici — 41 puis 29 sur deux appels identiques. Le premier réchauffe les caches, et la sérialisation charge paresseusement `ligne`/`car`/`gare` ligne à ligne — **un N+1 préexistant**, indépendant de ce champ. Le test compte donc uniquement ce qui touche `depense`.

Sans `DEPENSE_VOIR` la colonne affiche un tiret, pas un zéro.

## 2 · La main d'œuvre externe — tu as raison, mais pas sur le tiroir

**C'est bien une charge.** Là où je te contredisais, c'est sur l'endroit. Si elle part dans `Depense`, le coût d'une même panne se scinde en deux postes : les pièces sous « dépannages », la main d'œuvre sous « dépenses ». Le poste « dépannages » du bénéfice sous-estime ce qu'une panne coûte, et un car réparé par des mains externes passe pour **bon marché** — les deux chiffres restant individuellement corrects, donc invisibles.

Tu as tranché : **dans le dépannage, une ligne par intervenant.** C'est livré.

`Detailmaindoeuvre` porte `intervenant`, `montant` (bigint, `Assert\Positive`) et `prestation` en option. Texte libre et non un `Fournisseur` : celui-ci exige contact, adresse et pays, et imposer une fiche pour payer un soudeur au bord de la route produirait des fiches bidon — même raison que `beneficiaire` sur les dépenses.

### Le piège, et il était sérieux

`Depannage::$couttotal` est **recalculé à chaque écriture depuis les pièces**, à deux endroits du processor. Y verser naïvement la main d'œuvre suffisait pour qu'une simple correction de pièce l'**efface du chiffre**, sans erreur et sans trace.

Chaque gestionnaire rend donc son total, et **celui qu'on n'a pas touché est relu en base**. Mesuré avec la faute réintroduite :

```
couttotal attendu  : 81 000  (3 pièces × 12 000 + 45 000 de main d'œuvre)
couttotal obtenu   : 36 000  ← les 45 000 disparaissent en silence
```

Et une nuance dans le DTO : `null` n'est **pas** le tableau vide. `null` = « je ne touche pas à la main d'œuvre », `[]` = « supprime-la ». Sans elle, corriger une pièce effacerait les lignes.

### Deux choix que je note explicitement

`coutmaindoeuvre` est **dérivé** à la lecture, pas persisté : `couttotal` l'est parce que les stats de flotte en font un `SUM` SQL, ce qu'un getter PHP ne saurait servir. **Une** exception assumée à « rien de dérivable stocké », pas deux.

L'asymétrie avec le personnel **interne** (`Detailpersonnel`, sans coût) est voulue : un mécanicien de la maison est un **salaire**, déjà compté en `Depense` au siège. L'externe est une facture ponctuelle attachée à l'intervention — charge fixe contre sous-traitance.

## Une incohérence trouvée en passant

`DepannageInput` déclare `lieudepannage` et `car` non nuls, donc un **PATCH partiel est refusé en 422** par la validation — alors que `handlePatch` est écrit pour le supporter (`$data->lieudepannage ?? $depannage->getLieudepannage()`). Le client doit renvoyer la charge complète. **Je ne l'ai pas corrigé** : ça changerait le contrat de l'opération, et ce n'est pas ce que tu m'as demandé. Dis-moi si tu veux que je m'en occupe.

## Ce qui reste à faire

Le **frontend du dépannage** : le formulaire n'a pas encore les lignes de main d'œuvre, et la fiche ne les affiche pas. Migration `Version20260925100000` jouée sur la base de dev, base de test à jour. Six sentinelles dans [MaindoeuvreDepannageTest.php](Backend-Transport/tests/Api/MaindoeuvreDepannageTest.php), plus `typepanne()` et `piece()` ajoutés au `ScenarioBuilder`.

Je continue sur le frontend du dépannage, ou tu préfères regarder le backend d'abord ?











Deux choses. D'abord l'incohérence — je regarde comment le projet gère déjà « obligatoire à la création seulement ».

`ApprovisionnementInput` a exactement le même défaut — l'incohérence est un patron, pas un cas isolé. Je regarde ce que le frontend envoie réellement.

Le frontend envoie toujours la charge complète — le 422 n'était donc jamais atteint en pratique. C'est un défaut de contrat d'API. Je regarde si le processor d'approvisionnement tolère le partiel.

`ApprovisionnementProcessor::handlePatch` **ignore complètement** `$data->fournisseur` — il exige un champ qu'il n'utilise pas. Je mesure ça avant de conclure.

Confirmé : le formulaire permet de changer le fournisseur, l'API l'ignore. C'est le même motif — l'écran promet ce que le serveur ne fait pas. Je mesure d'abord.




La sauvegarde est en place. Je regarde la forme de la charge utile d'un approvisionnement avant d'écrire le test.

Les trois défauts sont mesurés. Le changement de fournisseur est même masqué derrière le 422. Je corrige — d'abord comment le fournisseur est résolu à la création.

Maintenant les opérations POST, et le fournisseur qui doit vraiment s'appliquer.

6/6. Mais mon changement expose un trou préexistant : un POST **sans** la clé `details` passerait la validation. Je vérifie.

Confirmé : **500** au lieu de 422. Je ferme le trou.

7/7. Je lance la suite complète et j'attaque le frontend du dépannage.

Le patron est clair. Je crée un partiel partagé plutôt que de dupliquer entre `new` et `edit`.

Maintenant le contrôleur, qui doit construire les lignes et les envoyer.

Maintenant la fiche du dépannage.

Un point à corriger au passage : le pied du tableau des pièces affiche `couttotal`, qui inclut maintenant la main d'œuvre — il ne correspondrait plus à ses propres lignes.







Je reprends la fiche du dépannage. Seul alias : `ui` — mes paramètres de lambda sont donc libres.

Deux choses : le fichier est en CRLF, et mon écriture le convertirait en LF. Je vérifie si j'ai déjà abîmé des fins de ligne ailleurs.

Pas de dégât : le dépôt stocke en LF et Git normalise — les diffs restent minimaux. Je corrige juste l'ancre de fin.

Assez de Python qui écrit du Python. Je corrige le script directement.

Le test PHP (qui strip les commentaires) passe : 33 verts. Maintenant je rends les trois pages dans un test — c'était tout l'objet du harnais.

Le double a fait son travail : mon jeu de données est incomplet. Je relève tous les champs que la page lit.

4/4. Je vérifie que le test attrape bien la régression du pied de tableau.

Mon `sed` n'a rien changé — la preuve était vide. Je refais proprement.

La régression était bien posée et le test est **passé quand même** — mon assertion était trop faible (`24 000` figure aussi dans la ventilation). Je la cible sur la cellule.

Maintenant la vraie preuve : le test doit tomber si le pied affiche `couttotal`.

La preuve est réelle cette fois. Reste les fixtures, pour que la démo le montre.

269 verts. Je documente les deux correctifs de contrat.

Exactement les montants attendus, et `couttotal` = pièces + main d'œuvre partout. Je contrôle les alertes calibrées.

Tout est vert et rechargé. Voilà le bilan des deux chantiers.

## 1 · L'incohérence — il y en avait trois, pas une

Le défaut que j'avais signalé était un **patron**, pas un cas isolé. `DepannageInput` *et* `ApprovisionnementInput` déclaraient leurs champs non nuls, si bien qu'un PATCH partiel était refusé en **422 par la validation** alors que les processeurs étaient écrits pour le supporter. Le processeur acceptait ce que la validation interdisait.

Les contraintes « obligatoire » vivent désormais dans un groupe **`creation`** que seul le POST active. Ce qui reste dans `Default` s'applique aux deux : une valeur **fournie** doit rester valide — d'où `NotBlank(allowNull: true)` sur le lieu, parce que l'absence veut dire « je n'y touche pas » et le vide « efface-le ».

### Et un bug qui, lui, se voyait

`ApprovisionnementProcessor::handlePatch` **ignorait complètement le fournisseur**. Le formulaire de modification propose de le changer, l'envoie, l'écran annonce « modifié avec succès »… et rien ne changeait. Le DTO l'**exigeait** pourtant, même en PATCH — obligatoire *et* inutilisé, ce qui rendait le défaut d'autant plus crédible.

Le 422 le masquait : mon test échouait sur le statut avant même d'arriver à vérifier le fournisseur.

### Deux trous découverts en chemin

**`Count(min: 1)` ne rend pas un champ obligatoire.** La contrainte **ignore null** : elle refuse `[]` mais laisse passer l'absence de la clé. Un POST sans `details` répondait **500**, pas 422. Trou préexistant, rendu atteignable par mon passage en nullable — mesuré, puis fermé avec un `NotNull` dans `creation` à côté du `Count`.

Et le garde-fou anti-doublon de l'approvisionnement faisait un `array_map` sur `$data->details` sans le tester : avec un PATCH partiel il recevait `null` et rendait 500.

Sept sentinelles dans [ModificationPartielleTest.php](Backend-Transport/tests/Api/ModificationPartielleTest.php), toutes mesurées en échec avant correction.

## 2 · Le frontend du dépannage

Un **partiel partagé** — [_maindoeuvre.html.twig](Frontend-Transport/templates/depannage/_maindoeuvre.html.twig) — inclus par la création *et* la modification. Le tableau des pièces, lui, est dupliqué entre les deux gabarits ; je n'ai pas ajouté une seconde copie.

La main d'œuvre est **facultative**, contrairement aux pièces : zéro ligne au départ, et la suppression peut vider le tableau. Le formulaire est autoritaire — il envoie toujours la clé, vide comprise, et côté API `[]` supprime.

### Un piège que la fiche cachait

Le pied du tableau des pièces affichait `couttotal`. Depuis que la main d'œuvre existe, `couttotal` la comprend : **le pied ne totalisait plus son propre tableau**. Il affiche maintenant le total des pièces, et la carte de coût porte la ventilation « Pièces X · Main d'œuvre Y ».

### Une leçon sur ma propre sentinelle

Ma première assertion cherchait `24 000` dans le corps de la page. J'ai réintroduit le défaut : **le test est resté vert** — le nombre figure aussi dans la ventilation du haut. Elle lit maintenant la cellule, et la preuve tient :

```
Failed asserting that 'Total des pièces 69 000 FCFA' contains "24 000"
```

Une assertion qui ne tombe pas sur le défaut qu'elle prétend garder ne garde rien. C'est noté en mémoire.

## Vérification

**269 tests backend, 37 frontend.** Fixtures rechargées, et mesuré sur tes données :

| dépannage | pièces | main d'œuvre | `couttotal` |
|---|---|---|---|
| Système de freinage *(clôturé)* | 554 000 | 18 000 | **572 000** |
| Panne moteur *(en cours)* | 1 390 000 | 95 000 | **1 485 000** |
| Panne électrique *(annulé)* | 14 000 | 0 | 14 000 |

Le poste « dépannages » passe de 1 944 000 à **2 057 000**, dont **113 000 de main d'œuvre** — les annulés restant exclus des coûts. Le bénéfice baisse donc d'exactement ces 113 000, ce qui est le propos : ce coût existait, il n'était nulle part.

Et les deux alertes de stock calibrées sont intactes — batteries en rupture, plaquettes à 8 pour un seuil de 10, rien d'autre. La main d'œuvre ne touche aucun stock, mais je préférais le vérifier.

Reste à voir à l'écran : `/depannage/13` pour la ventilation et la section, et `/depannage/13/modifier` pour les lignes préremplies.











- Sur les formulaires de la partie approvisionnement et dépannage, indique si les champs sont requis ou optionnel
- Dans la partie darkMode du frontend on vas ajouter le choix du `système` en plus du mode `dark` et `light` !
- Vérifié si le hors courriers est bien pris en compte dans toutes les statistiques 

Aussi, concernant la partie caisse, explique moi selon ton plan à quel moment le solde de la gare diminue et à quel moment ça augmente, aussi pour l'entreprise !










La convention existe déjà : `*` rouge pour le requis. Je regarde si un formulaire marque aussi l'optionnel.

La convention est « `*` = requis, rien = optionnel ». J'ajoute un marqueur explicite pour l'optionnel plus une légende, sans inventer un autre langage visuel.

Le sélecteur de fournisseur n'a même pas de `<label>`. Je traite les quatre gabarits plus le partiel.

Formulaires faits. Maintenant le thème — je regarde le dispositif actuel.

Pas de `prefers-color-scheme` dans le CSS : tout passe par la classe `.dark`. Le mode système devra donc se résoudre en JS.

Maintenant le bouton, qui doit porter trois états.

Je vérifie le thème dans le navigateur — c'est observable, donc mesurable.

La page de connexion suit désormais le système — elle l'ignorait complètement avant. Je vérifie l'inverse et les trois états.

Le suivi en direct fonctionne dans les deux sens, sans rechargement. Je remets l'émulation à zéro et j'attaque l'audit.

`courriersHorsCa` n'est honoré qu'à quatre endroits. Je cherche toutes les surfaces qui somment des courriers.

Beaucoup de « NON » sont légitimes — un bordereau liste ce qu'il y a dans le car, pas un chiffre d'affaires. Je regarde comment les trois qui l'honorent s'y prennent.

La règle est nette : la recette courrier reste affichée à part, mais **tout total composite l'exclut**. Je regarde les quatre suspects.

Deux trouvailles nettes, plus la mienne. Je regarde les séries par jour et par agent des stats de gare.

Confirmé : sur la même page, la somme des séries journalières ne peut pas égaler le `recetteTotale` de la gare. Je vérifie si le drapeau est actif quelque part.

Le drapeau est **actif** sur l'entreprise 10 — la divergence est donc observable aujourd'hui, pas latente. Je mesure.

Le drapeau est sur Sahel Voyages, qui n'a **aucun courrier** : le défaut est donc réel mais invisible sur ce jeu. Je le mesure par un test.

Mesuré. La recette de gare est déjà correcte ; le résultat du voyage **inclut bien les courriers à tort** (19 500 au lieu de 15 000). Trois points à éclaircir dans mon test.

Un bug préexistant, indépendant : `/api/stats/agent` **plante en 500**. Je regarde.

La cause est claire : `agentId` arrive en chaîne et le DTO exige un `int`. Mais mon scénario crée des ventes **sans agent** — je le rends réaliste pour distinguer un vrai bug d'un artefact.

Le 500 venait bien de mon scénario. Je vérifie quand même si une vente peut réellement n'avoir aucun agent.

Zéro sur les données réelles — le 500 n'est atteignable que sans auteur de vente. Je le signalerai. Les trois divergences, elles, sont à corriger.

5/5. Je lance la suite complète des deux côtés.

Les trois chantiers sont livrés. Voilà le bilan, puis l'explication sur la caisse.

## 1 · Requis / optionnel sur les formulaires

La convention existait déjà (`*` rouge dans `bagage/_form.html.twig`) mais n'était appliquée ni au dépannage ni à l'approvisionnement — et le **sélecteur de fournisseur n'avait même aucun `<label>`**.

Chaque formulaire porte désormais une légende et les deux marqueurs : `*` rouge pour l'obligatoire, `(optionnel)` en gris pour le facultatif. La colonne « Total » de l'approvisionnement ne reçoit ni l'un ni l'autre : c'est un calcul, pas un champ à remplir.

Le partiel de main d'œuvre dit maintenant la nuance qui compte : **la section est facultative, mais une fois un intervenant ajouté, son nom et son montant deviennent obligatoires.**

## 2 · Le thème : trois choix

Un seul bouton les fait tourner — système → clair → sombre. L'icône et le libellé montrent le mode **courant** : le libellé annonçait avant la *cible* d'un clic (« Dark » quand on était en clair), ce qui laissait croire qu'on était déjà dans l'autre mode.

Deux choses que j'ai corrigées dans la même passe, parce que sans elles « système » n'aurait été qu'une demi-promesse :

**L'éclair de thème.** `theme.js` s'exécute au `turbo:load`, donc *après* le premier rendu : la page apparaissait en clair avant de basculer, à chaque navigation. Un partiel inline dans le `<head>` pose la classe avant le premier pixel.

**La page de connexion ignorait le choix.** Le bouton ne vit que dans `base.html.twig` : on choisissait sombre, on se déconnectait, et l'application repassait en blanc. Le partiel est inclus dans les **deux** gabarits racines, comme `pwa.html.twig`.

Vérifié dans le navigateur, sur la page de connexion :

| situation | classe `<html>` | fond |
|---|---|---|
| rien en stockage, poste **sombre** | `dark` | `rgb(23,23,23)` |
| `light` forcé, poste sombre | *(vide)* | `rgb(255,255,255)` |
| `dark` forcé | `dark` | `rgb(23,23,23)` |
| valeur inconnue en stockage | `dark` | repli sur système |

Et la bascule du poste change la page **sans rechargement**, dans les deux sens.

Un changement de comportement à assumer : le défaut passe de « clair » à « système ». Un poste en sombre verra donc l'application en sombre sans avoir rien choisi — c'est l'objet de la fonction.

## 3 · Le hors-courriers : trois surfaces sur six l'ignoraient

La règle est posée par `RecetteGareService` : la recette courrier reste **affichée à part** mais ne compte dans **aucun total composite**. Elle n'était appliquée qu'à moitié.

| surface | défaut |
|---|---|
| `GareStatsController` | `recetteTotale` les excluait, mais la **série journalière** et la **ventilation par agent** les comptaient — sur le même écran, la somme de la courbe ne tombait pas sur le total affiché juste au-dessus |
| `AgentStatsProvider` | le total d'un agent les additionnait toujours : le classement des vendeurs ne s'accordait ni avec la recette des gares ni avec le bénéfice |
| `VoyageResultatService` | **le mien** — la recette d'un départ les comptait, donc plus généreuse que le bénéfice auquel elle contribue |

**Mesuré** : 19 500 au lieu de 15 000 sur chacune des trois.

Invisible sur tes données, et j'ai cherché pourquoi : le drapeau est actif sur **Sahel Voyages**, la seule compagnie qui n'a aucun courrier. Zéro d'un côté comme de l'autre. Le test monte donc le cas que les données ne montraient pas.

Un point de méthode : le test vérifie **les deux sens du drapeau**. Un correctif qui exclurait les courriers *toujours* aurait passé les quatre tests « drapeau levé » en cassant le cas par défaut.

**Ne sont pas concernés**, et c'est vérifié, pas supposé : les bordereaux et le manifeste (ils listent ce qui est chargé), les écrans propres au module Courrier (les exclure y serait absurde), les alertes, et `GareDashboardController` qui prend son total au service.

### Une fragilité que je n'ai pas corrigée

`AgentPerformanceDto` déclare `int $id` alors que `created_by` est **nullable** : une vente sans auteur fait répondre **500** à `/api/stats/agent`. Mesuré : zéro vente sans auteur en base, le chemin n'est pas atteignable aujourd'hui. Mais une écriture automatique — synchronisation hors ligne, réservation publique — pourrait en créer. Dis-moi si tu veux que je la blinde.

**274 tests backend, 37 frontend.**

---

## La caisse : quand le solde monte, quand il descend

Trois choses ne bougent **jamais** le solde, et c'est ce qui surprend le plus : l'ouverture d'une caisse, la clôture sans écart, et une dépense payée autrement qu'en espèces.

### Le solde d'une GARE

```
solde gare = Gare.soldeinitial
           + Σ montantcompte des sessions CLOTURÉES
           − Σ fondsouverture des sessions encore OUVERTES
           − Σ Depense (gare, ESPÈCES)
           − Σ Versement (ÉMIS ou ACCEPTÉ)
```

**Il MONTE** — une seule fois : à la **clôture** d'un guichet, quand l'agent remet son tiroir. Le montant compté rentre au coffre.

**Il DESCEND** dans trois cas :

| geste | effet |
|---|---|
| Le chef **avance un fonds** à un guichet qui ouvre | l'argent quitte le coffre… mais reste dans la gare |
| Une **dépense en espèces** | elle sort du coffre du chef |
| Un **versement émis** vers le siège | dès l'émission, pas à l'acceptation |

**Il ne bouge PAS** quand un client achète un billet. C'est le point le plus contre-intuitif du dispositif : la vente entre dans le **tiroir de l'agent**, et le solde de la gare ne l'enregistre qu'au moment de la clôture. Le solde affiché est celui de la gare entière — coffre plus tiroirs en cours — parce que la question posée est « combien la gare détient-elle ? ».

C'est d'ailleurs pourquoi les deux lignes de `fondsouverture` de mon plan se simplifient en une seule : ce que le chef a avancé aux guichets ouverts n'est plus dans le coffre, mais il est toujours dans la gare.

### Le solde de l'ENTREPRISE

```
solde entreprise = Entreprise.soldeinitial
                 + Σ Versement.montantrecu (ACCEPTÉ)
                 − Σ Depense (siège, ESPÈCES)
```

**Il MONTE** à l'**acceptation** d'un versement, jamais à son émission. **Il DESCEND** sur une dépense du siège en espèces.

### Le trou entre les deux — et c'est voulu

Entre l'émission et l'acceptation, l'argent est **parti de la gare** et **pas encore arrivé au siège**. Il n'est chez personne :

```
en transit = Σ Versement (ÉMIS)
```

Créditer le siège d'office lui ferait afficher un solde qu'il ne détient pas — et le montant en transit est précisément ce qu'on veut voir. C'est là qu'un sac d'argent se perd.

### Ce que le solde n'est pas

Une **vente** monte la recette, pas le solde. Un **versement** baisse le solde d'une gare sans toucher à sa recette. Un **écart de caisse** ne se soustrait d'aucun bénéfice — la caisse ne crée ni recette ni charge, elle **rapproche**.

Et un dernier point, celui que ta question sur le mode de règlement avait soulevé : une dépense par **virement** baisse le résultat du montant plein mais ne touche pas le solde, parce qu'elle sort d'un compte en banque que l'application ne suit pas. C'est le point que nous avons laissé ouvert pour le Mobile Money.













- Blinde la fragilité de `/api/stats/agent`.

Lors d'une discussion avec un autre développeur, voici son explication ou sa logique concernant la partie solde :

```
Solde de la gare :
- Ce qui incrémente : tickets, bagages, courriers, réservations payé, etc...
- Ce qui décrémente : toutes les dépenses de la gare (tout ce qui fait sortir de l'argent décrémente)

Solde de l'entreprise :
- Ce qui incrémente : versement venant des gares accepté..
- Ce qui décrémente : toutes les dépenses de l'entreprise, approvisionnement, dépannages, etc... (tout ce qui fait sortir de l'argent décrémente)
```

Analyse pour voir si c'est cohérent et logique, aussi si c'est la meilleur façon de procédé !









Deux choses. D'abord le blindage — je reproduis le 500 avant de le corriger.

Le `INNER JOIN` protège les billets, mais courriers et bagages lisent `createdBy` directement. Je regarde ce que renvoie le fournisseur.

Le `(int)` des actions critiques transformerait aussi un `null` en **agent n° 0** — un vendeur fantôme dans un tableau anti-fraude. Je reproduis les deux.

Reproduit : **500, TypeError sur `$id`**. Je corrige les deux, et je rends visible ce qui serait sinon jeté en silence.

3/3 passent, mais trois dépréciations subsistent : le même défaut une couche plus bas, dans le repository.

Plus de dépréciation. Je vérifie que le test attrape bien la régression.

La garde est bien ce qui tient. Je surface le montant côté écran, sinon il reste silencieux pour l'utilisateur.

277 verts. Maintenant l'analyse du modèle de solde — je vérifie deux choses dans le code avant de me prononcer.

## 1 · `/api/stats/agent` blindé

J'ai d'abord **reproduit** le 500 : un courrier sans `created_by` suffit.

```
AgentPerformanceDto::__construct(): Argument #1 ($id) must be of type int, string given
```

La mécanique : `null` en clé de tableau devient la **chaîne vide** en PHP, et le DTO exige un `int`. Tout l'écran des agents tombait pour une seule ligne mal attribuée.

Les billets y échappaient **par accident** — leur requête passe par un `INNER JOIN` sur `User`, qui écarte les lignes sans auteur. Ce n'est pas une protection, c'est un effet de bord, et il ne couvrait ni les courriers ni les bagages.

J'ai trouvé un second piège du même sang : les sept boucles du tableau **anti-fraude** faisaient `(int) $r['agentid']`, et `(int) null` vaut **0** — un vendeur fantôme nommé « — » avec des annulations à son nom. Dans un écran qui sert à repérer les abus, c'est la dernière chose à inventer.

**Trois correctifs :**

| | |
|---|---|
| Une garde nommée `$agentValide` | écarte `null`, `''` et `0` des dix boucles |
| `CourrierRepository` | n'indexe plus sur `null` (c'était la source des dépréciations) |
| `recetteNonAttribuee` | le montant écarté est **servi et affiché**, pas jeté |

Ce dernier point est volontaire : écarter du classement une vente sans auteur est juste, mais la faire disparaître sans un mot reproduirait exactement le défaut que cette série de correctifs traque — un total qui ne totalise pas ce qu'il annonce. Le bloc n'apparaît que si le montant est non nul, et il dit quoi en faire.

**277 tests backend, 37 frontend.** Régression vérifiée : désactiver la garde fait tomber le test.

---

## 2 · Son modèle de solde : l'analyse

### Ce qu'il voit et que mon plan avait manqué

**Il a raison sur les approvisionnements et les dépannages.** Mon plan ne soustrayait du solde de l'entreprise que les `Depense` du siège. Or un lot de pneus et une facture de garage font bel et bien sortir de l'argent. C'est un trou dans mon plan, pas dans le sien.

**Et son solde de gare bouge immédiatement.** Le mien n'avance qu'à la clôture : à 15 h, il sous-estime ce que la gare détient de toute la journée de vente. C'est une faiblesse réelle de ma proposition.

**Son invariant est le bon** : « tout ce qui fait sortir de l'argent décrémente ». C'est exactement ce qu'un solde doit respecter.

### Ce qui ne tient pas

**Il oublie les versements côté gare.** Son énumération les met en incrément de l'entreprise, jamais en décrément de la gare. Le même argent existerait donc **deux fois**. Son principe le couvre, sa liste non — c'est un oubli d'énumération, pas de raisonnement.

**Les réservations payées ne sont pas de l'argent de la gare.** Vérifié dans le code : le paiement est **en ligne**, par webhook du prestataire (`ReservationConfirmationService`), et le bon payé reste distinct du billet émis plus tard au guichet. Cet argent est sur le compte d'un prestataire Mobile Money — il n'est jamais entré dans un tiroir. L'inclure gonflerait le solde d'une gare d'un cash qu'elle n'a jamais vu.

**Les ventes du commercial à bord posent le même problème.** `RecetteGareService` les fond dans la recette de sa gare d'affectation. Son modèle créditerait donc la gare d'un argent encore dans la poche du commercial, jusqu'à sa remise — que mon plan a explicitement mise hors périmètre.

**Appros et dépannages n'ont aucun mode de règlement.** Vérifié : zéro occurrence de `modereglement` sur les deux entités. Les décompter en bloc, c'est traiter un virement de 3 M de pneus comme un retrait du coffre. Le principe est juste, la **donnée manque**.

### Le point qui décide

En incrémentant à la vente, son solde devient `recette − dépenses` — c'est-à-dire un **résultat**, pas un solde. Deux conséquences :

- il ne peut plus être rapproché d'un **comptage physique** ;
- **l'écart de caisse devient indétectable** — ce qui est le but même de A1.

C'est le retour exact de la confusion flux / stock qu'on avait démêlée : son modèle mesure ce que la gare **devrait** détenir, le mien ce qu'elle **détient**.

### La meilleure façon de procéder : ni l'un ni l'autre seul

**Deux soldes, nommés, et leur écart.**

```
solde THÉORIQUE  = soldeinitial + ventes espèces − sorties espèces   ← son modèle, corrigé
solde CONSTATÉ   = soldeinitial + Σ comptages − sorties espèces      ← le mien
écart cumulé     = constaté − théorique                              ← le manquant
```

Le théorique répond « combien devrait-il y avoir, là, maintenant ? » — immédiat, sans attendre une clôture. Le constaté répond « combien y a-t-il eu, vérifié ? ». **Leur différence est le chiffre que personne ne peut produire aujourd'hui**, et c'est celui qui attrape un agent qui se sert.

Et la règle qui tranche tous les cas litigieux, y compris le Mobile Money resté ouvert :

> **Un mouvement n'entre dans un solde que si l'argent a physiquement changé de coffre.**

Elle range d'elle-même les trois cas douteux : la réservation payée en ligne entre dans une **troisième poche** (le compte du prestataire) et dans aucun solde de gare ; la remise du commercial entre au moment de la remise, pas de la vente ; un appro ne sort du coffre que s'il est payé en espèces.

Deux décisions m'arrêtent avant de reprendre le plan.



Faut-il les DEUX soldes, ou un seul ?

On vas resté sur ton plan, mais ferme le trou du approvisionnements et les dépannages que tu as contacté, etc...

Comment traiter les sorties d'argent qui n'ont pas de mode de règlement (appros, dépannages) ?

Au faite, concernant le solde, il n'y a pas de modes de règlement spécifique pour faire le baisser car le solde n'est pas forcement un coffre, ça peut être un compte etc... ! Quelque soit d'où l'argent est sorti ! donc toutes les dépenses d'une gare ou d'une entreprise sortent de leur solde



Ta seconde réponse change le cadre, et pour le mieux : **l'axe n'est pas espèces contre non-espèces, c'est « à qui appartient cet argent »**. Je regarde ce que ça invalide dans le plan et dans le code.

Le premier des trois textes est corrigé. Les deux autres, côté frontend.

Deux textes à corriger, via `sed`/python en ligne :

Les trois textes sont corrigés. Avant de réécrire les formules du plan, je vérifie les faits qu'elles vont affirmer — statuts et imputation des approvisionnements et dépannages, et le canal de paiement des réservations.

Un point important : l'approvisionnement n'a **pas** de `couttotal` sur l'entête — il est sur les détails. Je lis les deux agrégats existants pour reprendre leurs règles exactes.

Les deux règles canoniques sont : `statut != 'ANNULE'`, et **aucun** filtre sur la corbeille. Maintenant le point de symétrie — comment une réservation est-elle payée ?

Il existe bien un canal `source = GUICHET | MOBILE`. Reste à savoir si une réservation mobile est **payée en ligne** ou payée au guichet au retrait — c'est tout l'enjeu du point de symétrie.

Le champ décisif : `// -- Paiement (en ligne, simulé pour l'instant) --` avec `etatpaiement`/`referencepaiement`, et un commentaire qui parle de « prélèvement **chez le prestataire** ». Je mesure ce que ça représente dans les données, plutôt que de le déduire.

Mesuré : **2 réservations MOBILE payées, 30 000 FCFA** — dont une `A_REGULARISER`. Ce n'est pas un cas théorique. Je vérifie si la recette d'une gare les compte déjà, ce qui rendrait le déséquilibre visible à l'écran.

`recettePayeeParGare` filtre sur `etatpaiement = PAYE` **sans regarder la source** : ces 30 000 sont donc déjà comptés dans la recette d'une gare. Je regarde laquelle, pour chiffrer l'écart.

Les deux trous sont chiffrés. Avant d'écrire les formules, je vérifie le point qui peut les invalider : les trois postes de charges sont-ils bien disjoints (pas d'appro déjà saisi comme dépense) ?

Les trois postes sont disjoints, écrit noir sur blanc : `Depense` ne porte que les charges saisies. Soustraire les trois est donc légitime. Dernier point avant d'écrire — la corbeille, côté dépenses :

Une asymétrie réelle à consigner : `Depense` exclut la corbeille de ses totaux, `Approvisionnement`/`Depannage` non (ils ont un `ANNULE` dédié). Je lis l'argument exact avant de l'inscrire au plan.

Tout est vérifié. J'écris la mise à jour du plan — formules, décision sur le mode de règlement, et les règles d'exclusion qui diffèrent d'un poste à l'autre.

Le heredoc a été coupé. J'écris le script dans le scratchpad et je l'exécute.

Aucun filtre `ESPECES` ne subsiste dans une formule ; les mentions restantes sont du contexte ou les explications neuves. Reste à inscrire le point de symétrie que sa propre définition fait apparaître.

Le plan est complet. Je vérifie que les trois textes n'ont rien cassé — un test pourrait asserter un libellé. Séquentiellement, jamais deux suites phpunit en parallèle.

Tout est vert : **277 tests backend, 37 frontend** (les `[error]` sont les journaux de refus attendus par les tests). Je vérifie qu'une mémoire existante ne porte pas l'ancienne règle.

Cette mémoire porte trois affirmations que le plan a depuis invalidées (le crochet `ESPECES`, une FK de session sur `Depense`, un visa). Je la corrige.

Les deux décisions sont appliquées, et vérifiées sur les données.

## Le trou fermé — chiffré

Ta correction sur les approvisionnements et les dépannages valait plus que je ne pensais. Mesuré en base :

| entreprise | approvisionnements | dépannages | **ignoré par l'ancien plan** |
|---|---|---|---|
| IRA Transport | 4 966 000 | 2 289 200 | **7 255 200 FCFA** |
| Sahel Voyages | 126 000 | 0 | 126 000 |

Un détail qui aurait fait échouer la première implémentation : **l'approvisionnement n'a aucun champ de montant sur son entête**. Le coût est sur ses détails — `SUM(da.couttotal)` avec une jointure. Le dépannage, lui, porte bien `couttotal` (main d'œuvre externe comprise). Le plan l'écrit maintenant.

Et une asymétrie que j'ai trouvée en vérifiant : **les trois postes n'excluent pas par le même champ**. `Depense` filtre `deletedAt IS NULL` (sa corbeille *est* son annulation), tandis qu'`Approvisionnement` et `Depannage` filtrent `statut != 'ANNULE'` et ignorent la corbeille. Un `SoldeService` qui uniformiserait ferait diverger le solde du bénéfice sur les mêmes données.

## Le filtre `ESPECES` retiré

Ta seconde réponse referme d'un cran plus haut le « point laissé ouvert » que le plan portait : je m'inquiétais d'un Mobile Money payé avec l'argent du tiroir et proposais d'allonger la liste des modes qui sortent du coffre. Si aucun mode ne sort du lot, il n'y a plus de liste à calibrer — et le jour où la compagnie encaisse par un moyen qui n'existe pas encore dans l'enum, le solde est déjà juste.

Trois textes annonçaient l'inverse, corrigés : le docblock de [Modereglement.php](../Backend-Transport/src/Domain/Enum/Modereglement.php) (qui présentait `ESPECES` comme « le crochet de la future caisse »), l'aide du [formulaire de dépense](../Frontend-Transport/src/Form/DepenseFormType.php:73) et la note sous la ventilation par mode. La sentinelle n° 6 du plan boucle désormais sur les **quatre** modes : écrite sur `ESPECES` seul, elle resterait verte avec le défaut réintroduit.

277 tests backend, 37 frontend, tous verts.

## Ce que ta définition fait apparaître en face — à trancher

Si un solde suit l'argent où qu'il soit, il doit aussi suivre l'argent qui **arrive ailleurs qu'à un guichet**. `Reservation` porte un bloc « Paiement (en ligne, simulé pour l'instant) », et le commentaire du hold de paiement parle de « prélèvement **chez le prestataire** ». Cet argent n'a jamais vu un tiroir.

Mesuré : **2 réservations mobiles payées, 30 000 FCFA** sur la Gare d'Adjamé — contre une seule réservation guichet à 8 000. Mes formules ne les créditent nulle part : pas de session de caisse (normal, aucun agent ne les a encaissées), et le solde de l'entreprise ne monte que par les versements des gares.

Ce qui rend la chose visible : `recettePayeeParGare()` filtre sur `etatpaiement = PAYE` **sans regarder `source`**. Ces 30 000 sont donc déjà dans la recette d'Adjamé. Dès le palier 1, la même gare afficherait une recette qui les contient et un solde qui ne peut pas les contenir.

Je ne l'implémente pas sans ton accord : ça tient en une requête, mais la question « un encaissement en ligne devient de l'argent de la compagnie au prélèvement, ou au reversement du prestataire ? » est métier, pas technique — le même doute que pour les versements gare → siège, où le plan a tranché pour le transit. La section « Le trou symétrique » du plan pose les trois options.

Le plan est révisé, l'en-tête porte les deux décisions, et j'ai corrigé une mémoire qui portait encore le crochet `ESPECES` et une FK de session sur `Depense`.












- Concernant le `Un détail qui aurait fait échouer la première implémentation : **l'approvisionnement n'a aucun champ de montant sur son entête**. Le coût est sur ses détails — `SUM(da.couttotal)` avec une jointure. Le dépannage, lui, porte bien `couttotal` (main d'œuvre externe comprise). Le plan l'écrit maintenant.`, je me dis qu'on devrait mettre le `couttotal` sur l'approvisionnement pour être cohérent !

- Concernant le `Et une asymétrie que j'ai trouvée en vérifiant : les trois postes n'excluent pas par le même champ. Depense filtre deletedAt IS NULL (sa corbeille est son annulation), tandis qu'Approvisionnement et Depannage filtrent statut != 'ANNULE' et ignorent la corbeille. Un SoldeService qui uniformiserait ferait diverger le solde du bénéfice sur les mêmes données.` :
```
D'abord :
- Montre moi sur quoi ce base le calcule des recettes au claire à l'état actuelle
- Aussi, montre moi qu'est ce qui rentre dans la recette et qu'est ce qui ne rentre pas du genre selon leur état (annuler, ou deletedAt, etc) !
- Aussi, dit moi ce qui est logique entre le faite de passer `ANNULER` ou `deletedAt` pour le calcule des recettes et coût, etc... !
```


D'accord. L'état des lieux d'abord — et mesuré, pas déduit. Je relève les règles de chaque agrégat.

Maintenant les agrégats de recette eux-mêmes, méthode par méthode, avec leurs deux clauses.

Point capital avant de conclure : un filtre Doctrine global pourrait appliquer `deletedAt IS NULL` partout sans qu'on l'écrive. Je vérifie.

**Aucun filtre Doctrine global** — donc `deletedAt` ne joue que là où il est écrit à la main. Je mesure ce que ça vaut en argent sur les données réelles.

Le vérificateur d'action est en panne passagère. Je raccourcis la requête.

Je poursuis l'audit par la lecture, qui ne dépend pas de lui. Les statuts de chaque entité, et le mécanisme de corbeille :

Le commentaire du garde de suppression est décisif — il parle de l'incohérence « billet supprimé absent du manifeste mais compté en recette ». Je vérifie où les 20 `deletedAt` du `TicketRepository` sont posés, et si un champ `isEtatdelete` sert de filtre ailleurs.

Point sérieux à cerner : certains agrégats de billets filtrent la corbeille, d'autres non. Je dresse le tableau exact, méthode par méthode.

La lecture est exhaustive et le constat est net. Je réessaie la mesure.

Bash reste bloqué. Je continue en lecture : les fixtures me diront ce qui existe en corbeille sans interroger la base.

Aucune fixture ne crée de ligne en corbeille — le défaut serait donc invisible sur le jeu de démonstration. Je lis maintenant le service qui compose les recettes, pour répondre exactement à la première question.

Il me manque un axe pour répondre proprement : la date sur laquelle chaque poste est daté.

Dernier contrôle avant de conclure : que devient le **stock** quand on met un approvisionnement à la corbeille ? La réponse change la recommandation.

Voilà ce qui change tout : l'approvisionnement a une action `/annuler` qui **retire les pièces du stock**. Je vérifie les deux derniers points — la méthode qui diverge, et si `Depense` a un statut.

Audit terminé par lecture exhaustive des sept dépôts. La mesure en base m'est refusée ce tour-ci — le vérificateur d'actions de l'environnement est en panne passagère, je te dirai clairement ce qui reste à chiffrer.

# 1. Sur quoi se base le calcul des recettes

Un seul point d'entrée, [RecetteGareService](Backend-Transport/src/Domain/Service/RecetteGareService.php:37) — quatre sources additionnées, chacune rattachée à une gare par une règle différente :

| source | rattachée à | montant retenu | daté sur |
|---|---|---|---|
| billets guichet | `t.gare` (gare de **montée**) | `prix` | `createdAt` |
| billets commerciaux | gare **d'affectation** du commercial | `prix` | `createdAt` |
| réservations | `r.gare` (**provenance**) | `prix + penalitemontant` | **`datepaiement`** |
| bagages | `garedepart` (ou gare du commercial) | `montant` | `createdAt` |
| courriers | `garedepart` | `montant + COALESCE(fraissuivi,0)` | `createdAt` |

Deux mécanismes se superposent à ça : l'**anti-double-comptage** (`t.reservation IS NULL` — la recette d'une réservation est reconnue à son paiement, le billet émis ensuite ne recompte pas) et le **hors-CA** (les courriers restent affichés mais quittent tous les totaux composites).

Première asymétrie au passage : **les recettes sont datées à la saisie (`createdAt`), les charges à la date du geste** (`datedepense`, `dateappro`, `datedepannage`). Seule la réservation est datée sur son paiement.

# 2. Ce qui entre et ce qui n'entre pas, par état

| poste | statut qui compte | statut exclu | corbeille (`deletedAt`) |
|---|---|---|---|
| **Ticket** | `VALIDE` seul | REPORTE, ANNULE | **ignorée** (sauf 4 méthodes) |
| **Reservation** | *aucun filtre de statut* — seul `etatpaiement = PAYE` | rien | **exclue partout** |
| **Bagage** | ENREGISTRE, EMBARQUE, LIVRE, **PERDU** | ANNULE seul | **ignorée** (sauf 3) |
| **Courrier** | tout `!= ANNULE` (y compris PERDU) | ANNULE seul | **ignorée** (sauf 2) |
| **Depense** | *l'entité n'a aucun champ statut* | — | **exclue partout** |
| **Approvisionnement** | `!= ANNULE` | ANNULE | **ignorée** |
| **Depannage** | `!= ANNULE` | ANNULE | **ignorée** |

Deux points vérifiés qui expliquent la colonne de droite : **il n'existe aucun filtre Doctrine global** (pas de `SQLFilter`, rien dans `doctrine.yaml`) — `deletedAt` ne joue donc que là où il est écrit à la main. Et **aucune fixture ne crée de ligne en corbeille**, ce qui veut dire que le jeu de démonstration ne peut pas révéler l'écart — même configuration que le hors-CA.

`PERDU` qui compte en recette est juste, et documenté : le transport a eu lieu, le client a payé.

# 3. Le trou réel — deux chiffres pour la même chose, aujourd'hui

Le défaut n'est pas l'asymétrie entre entités. Il est **à l'intérieur du billet**, dans un seul fichier :

- [TicketRepository:60](Backend-Transport/src/Repository/TicketRepository.php:60) `findRecapDestinations` (bordereau du chauffeur) filtre `deletedAt IS NULL`, avec un `// !!` ;
- [TicketRepository:432](Backend-Transport/src/Repository/TicketRepository.php:432) `recetteParGare` — celui qui alimente le tableau de bord — ne le filtre pas.

Donc un billet `VALIDE` mis à la corbeille **compte dans la recette de la gare et disparaît de son bordereau**. Idem pour la fiche du voyage, qui passe par `recettePourVoyage` (filtré). Même chose sur les bagages et les courriers.

Le code connaît le problème : le garde de suppression dit en propres termes qu'il ferme l'incohérence « billet supprimé absent du manifeste mais compté en recette ». Mais il ne la ferme qu'**après embarquement** — avant le départ réel, « la suppression est libre », et le billet reste dans la recette. Un bagage `ENREGISTRE` et un courrier `EN_ATTENTE` sont dans le même cas : supprimables *et* comptés.

# 4. Ce qui est logique — `ANNULE` ou `deletedAt`

Les deux répondent à des questions différentes, et il faut les deux :

- **`ANNULE` = « ça a existé, puis on l'a défait. »** Un fait métier : le client s'est désisté, le courrier n'est pas parti. Ça laisse une trace, un motif, un auteur, et ça doit **rester visible** — c'est opposable.
- **`deletedAt` = « ça n'aurait jamais dû être saisi. »** Une faute de frappe, un doublon. Rien à raconter, donc on le sort des listes.

**Et la règle qui décide laquelle exclut un total n'est pas arbitraire : c'est « y a-t-il un effet physique à défaire ? »**

| cas | pourquoi la règle actuelle est juste |
|---|---|
| appro, dépannage | leur `/annuler` **retire les pièces du stock** (mouvement SORTIE, refusé si une pièce est déjà consommée). Si la corbeille excluait leur coût, on aurait du **stock entré sans coût** — des pièces gratuites en inventaire. Seul `ANNULE` défait les deux ensemble. ✅ |
| dépense | l'entité **n'a aucun statut**. Aucun stock, aucun imprimé. La corbeille est son seul geste d'annulation possible. ✅ |
| billet, bagage, courrier | ils ont les deux, et la suppression est déjà bornée aux états sans effet physique. C'est **là** qu'il y a un trou, pas ailleurs. ❌ |

**Ma recommandation : ne pas uniformiser, corriger le trou.** Filtrer `deletedAt IS NULL` dans les agrégats de recette de `Ticket`, `Bagage` et `Courrier`, et laisser appro/dépannage exactement comme ils sont.

Le principe qui tranche : **un total doit être réconciliable avec une liste qu'on peut afficher.** Si la recette d'une gare contient 15 000 qu'aucun écran ne montre, personne ne peut retrouver l'erreur — c'est le défaut que tout le chantier précédent a passé son temps à traquer.

L'objection sérieuse, et pourquoi elle ne tient pas : « alors supprimer un billet fera baisser la recette » — c'est la vente hors-livre. Mais c'est déjà couvert trois fois : le garde interdit la suppression d'un billet embarqué, `suppressionsParAgent` compte exprès les billets `VALIDE` supprimés par agent, et l'alerte anti-fraude existe. Et `Depense` comme `Reservation`, les deux modules les plus récents, filtrent déjà la corbeille — la direction est prise.

Ce qu'il reste à chiffrer, dès que l'outil de mesure répond : combien de lignes sont réellement en corbeille avec un statut comptable, et de combien la recette bougerait. Je ne corrige rien avant d'avoir ce nombre.

# 5. `couttotal` sur l'approvisionnement — d'accord

Ton instinct est bon, et pour une raison que je n'avais pas vue : `coutTotal()` fait `->join('a.detailapprovisionnements', 'da')`, un **INNER JOIN**. Un approvisionnement sans aucune ligne de détail est donc **absent du coût total**, silencieusement. Le champ sur l'entête supprime la jointure et le risque avec elle.

Le patron est déjà là — celui de `Depannage` : le champ est **recomposé par le processor** à chaque écriture, jamais saisi. Ça reste un dérivé stocké, comme `Depannage.couttotal` et `Ticket::$desistementImputableCompagnie`, donc l'exception est déjà assumée dans le projet.

Le chantier : champ `bigint` + migration **avec backfill** des lignes existantes, recomposition à la création et au PATCH (la réconciliation par différence est déjà en place), `coutTotal()` réécrit sans jointure, exposition en lecture, et deux tests — l'entête égale la somme des détails après POST *et* après modification partielle, puis le coût total de l'entreprise inchangé avant/après migration.

Une migration touche ta base, qui est un banc d'essai actif : **dis-moi quand je peux la jouer** et je l'enchaîne. Je peux écrire le code et la migration sans l'exécuter, si tu préfères regarder d'abord.












On vas aller sur ta recommandation.
    - Aussi, on m'avais parler d'un truc du genre `tout ce qui concerne l'argent on ne supprime pas` ! est ce vrai que c'est fait ainsi dans d'autres systèmes
    - Aussi, je me dis que si on vas sur ta recommandation, pour le Approvisionnement, Depannage, etc... on vas filtrer sur `deletedAt IS NULL` en plus du `!= ANNULE` ! où est ce que j'ai tort ?

Tu peux jouer la migration puis enchaîner le `couttotal` sur l'approvisionnement !




Sur ton second point tu n'as pas tort — mais l'ordre compte, et il y a un fait à vérifier avant. Je regarde si un approvisionnement peut être mis à la corbeille aujourd'hui, et ce que devient son stock.

Bash est revenu. Les deux exposent un `DELETE` via `SoftDeleteProcessor` **sans aucun garde**. Je vérifie l'effet sur le stock, puis je mesure tout ce que j'ai laissé en suspens.

Le commentaire du code dit déjà tout : « suppression d'un **document comptable** : admin d'entreprise UNIQUEMENT (la sortie normale est l'annulation, tracée) ». Je trouve le modèle de stock.

Confirmé : le stock est un **compteur stocké** sur `Piece.stockinitial`, incrémenté par les mouvements. Mettre un appro à la corbeille ne le touche pas. Maintenant je mesure l'état réel avant de changer quoi que ce soit.

Mesuré : **zéro ligne en corbeille dans toute la base** — donc le correctif ne changera aucun chiffre aujourd'hui, comme le hors-CA. Ce sont les tests qui devront le prouver. Je vérifie le piège de l'INNER JOIN et je relève les totaux de référence.

Aucun appro sans détail aujourd'hui — le risque de l'INNER JOIN est structurel, pas réalisé. Et les 4 966 000 se recomposent exactement (160 000 + 2 976 000 + 1 830 000, l'annulé de 118 000 exclu).

Maintenant je vais **mesurer le défaut** sur les données réelles, avec une commande de diagnostic en transaction annulée.

Je l'écris — elle mettra un billet à la corbeille dans une transaction annulée, pour mesurer l'écart réel entre le tableau de bord et le bordereau.

Le défaut est mesuré, sur tes données : un billet de 15 000 mis à la corbeille laisse la recette de l'entreprise et de la gare **inchangées**, mais retire 15 000 de la fiche du voyage et un passager du bordereau. Deux chiffres pour la même chose, confirmé.

Je dresse l'inventaire exact des méthodes à corriger avant de toucher quoi que ce soit.

~55 méthodes concernées. Je restreins le périmètre à l'argent et à l'activité, en laissant intactes les surfaces de contrôle anti-fraude — un contrôle doit voir large, y compris ce qu'on a tenté d'effacer.

48 méthodes corrigées. La mesure immédiatement — le même scénario ne doit plus produire d'écart.

Les cinq surfaces bougent maintenant du même montant. Je documente la règle dans les trois dépôts.

277 verts, mais rien ne couvre encore le nouveau comportement. J'écris la sentinelle. Je regarde le harnais disponible.

La règle de la maison : prouver que la sentinelle tombe. Je réintroduis le défaut temporairement.

La sentinelle tombe sur les deux surfaces. Je restaure.

Maintenant ton second point. J'implémente le garde d'abord — sans lui, ton filtre créerait l'incohérence stock/argent. Je vérifie les statuts du dépannage.

Confirmé : l'annulation d'un dépannage restaure le stock, et seul un dépannage **EN COURS** peut être annulé. J'inventorie les agrégats de coût avant de les toucher.

12 méthodes, dont les détails qui n'ont pas de corbeille (pas d'`EntityBase`). J'implémente le garde puis les filtres.

281 verts, le garde ne casse rien. Maintenant sa sentinelle — et celle du filtre, qui demande une précaution particulière. Je regarde les builders disponibles.

4 tests verts du premier coup. J'enchaîne sur le `couttotal`. Je lis l'entité et le processor.

Je vérifie la ligne de détail, puis j'écris l'ensemble.

Chaque ligne stocke déjà son `couttotal = quantité × prix` — l'entête est exactement le même patron un cran plus haut. J'écris le chantier.

Le patron est clair. J'écris le chantier complet.

La migration maintenant — tu m'as donné le feu vert. Je relève d'abord les totaux de référence, pour prouver l'invariance après backfill.

Backfill exact sur les cinq lignes, et les totaux sont **identiques** à la référence (4 966 000 / 126 000). Je vide le cache ApiPlatform — un nouveau champ exposé l'exige — et je lance la suite.

La base de test est bâtie par `schema:update`, pas par les migrations. Je la mets à jour.

285 verts. J'ajoute les sentinelles du `couttotal`. Je regarde le format de PATCH utilisé par le test existant.

Je prouve que les sentinelles tombent — d'abord en retirant le recalcul au PATCH, puis en remettant la jointure.

Ma sentinelle sur la jointure n'est pas tombée — et c'est moi qui avais tort, pas le test. Je restaure et je vérifie ce que la jointure changeait vraiment.

Mesuré : aucun orphelin aujourd'hui, et surtout mon raisonnement était faux — un appro sans ligne contribue zéro, donc son absence ne change pas la somme. La jointure ne faussait que le **compteur** `nbappros`. Je corrige le test et les trois textes qui affirment le contraire.

Je prouve que la sentinelle corrigée tombe, cette fois.

La sentinelle tombe (1 au lieu de 2). Suite complète, puis je regarde si le frontend resomme les lignes.

Le vérificateur flanche à nouveau. Je regarde le frontend en lecture pendant ce temps.

Le frontend resomme bien les lignes — et pire, il recalcule `quantité × prix` au lieu de lire le total de la ligne. Je trouve la source React.

Trois endroits côté frontend. Je les aligne sur la colonne unique.

Le vérificateur bloque toutes les écritures. J'utilise ce temps pour repérer où documenter la règle dans le README.

Le vérificateur d'écritures reste en panne. Je tente une dernière fois, sinon je te fais le point.

Le vérificateur d'actions de l'environnement refuse toute écriture depuis plusieurs minutes. Je te fais le point sur ce qui est livré, et je réponds à ta question — elle ne demande aucun outil.

## Livré et mesuré

**Les recettes** — 48 agrégats de `Ticket`, `Bagage` et `Courrier` filtrent maintenant la corbeille. Preuve par la mesure, sur tes données, avec le même billet de 15 000 :

| surface | avant le correctif | après |
|---|---|---|
| recette entreprise | **inchangée** | −15 000 |
| recette de la gare | **inchangée** | −15 000 |
| fiche du voyage | −15 000 | −15 000 |
| bordereau chauffeur | −1 billet | −1 billet |

**Les charges** — ton point appliqué : `deletedAt IS NULL` ajouté aux 11 agrégats de coût des approvisionnements et dépannages, **précédé du garde** qui le rend sûr. Un appro ou un dépannage non annulé refuse désormais la corbeille, avec la marche à suivre dans le message. Sans ce garde, ton filtre aurait produit du stock entré sans coût — des pièces gratuites en inventaire.

**`couttotal` sur l'approvisionnement** — migration jouée, backfill vérifié ligne par ligne (les 5 approvisionnements ont un entête égal à la somme de leurs lignes, l'annulé de 118 000 conservé pour l'audit), et les totaux sont **identiques** à la référence : 4 966 000 et 126 000.

**14 tests neufs**, dont 5 prouvés tombants en réintroduisant le défaut.

## Une correction : j'avais tort sur l'INNER JOIN

L'argument que je t'ai donné pour justifier le champ — « un approvisionnement sans ligne disparaît du coût » — est **faux pour le montant**. Sa contribution vaut zéro : son absence de la somme ne la déplace pas. Vérifié en remettant la jointure, le total ne bouge pas d'un franc.

Ce qu'elle faussait réellement : le `COUNT(DISTINCT a.id)` de la ventilation par fournisseur, qui sous-comptait cet approvisionnement. Ma première sentinelle ne tombait pas — c'est ça qui m'a mis sur la piste. Elle vise maintenant le compteur, et elle tombe (1 au lieu de 2). Les trois textes qui affirmaient le faux sont corrigés.

Le champ reste justifié, pour les vraies raisons : la cohérence avec `Depannage` que tu demandais, des agrégats sur une table au lieu de deux, un coût lisible sans resommer côté client, et ce compteur juste.

## « Tout ce qui concerne l'argent, on ne supprime pas »

C'est vrai, et c'est un des principes les plus anciens de la comptabilité. Un journal est **append-only** : on ne retire pas une écriture, on en passe une seconde qui l'annule — la **contre-passation** (ou extourne), et côté commercial l'**avoir** plutôt que la suppression d'une facture. Les ERP appliquent ça strictement : une facture validée ne se supprime pas, on émet un avoir. La raison est la **piste d'audit** — il faut pouvoir reconstituer la séquence des opérations, et une suppression y fait un trou invisible. Les obligations de conservation et d'inaltérabilité des registres, selon les pays, vont dans le même sens.

Ce qui est intéressant : **ton code appliquait déjà ce principe à moitié**, sans que ce soit dit nulle part. Le garde de suppression d'un billet embarqué, le courrier supprimable seulement `EN_ATTENTE`, le bagage seulement `ENREGISTRE`, et ce commentaire que j'ai trouvé mot pour mot sur l'approvisionnement : « suppression d'un **document comptable** : admin d'entreprise UNIQUEMENT (la sortie normale est l'annulation, tracée) ». La règle était là, mais rien ne l'appliquait sur les appros et dépannages. C'est ce trou que ton intuition a désigné.

**Il en reste un, et il est sur `Depense`** : cette entité n'a **aucun** champ statut. Sa seule façon d'être annulée est donc la corbeille — exactement ce que le principe interdit pour une sortie d'argent. C'est la dernière entorse, et la fermer voudrait dire lui donner un `ANNULE` avec motif, la corbeille redevenant réservée aux erreurs de saisie. Je ne le fais pas sans ton accord : ça touche un module livré et l'écran de dépenses.

## Ce qui reste à finir

Quatre choses, toutes bloquées par l'outil :

- le **frontend** resomme les lignes à trois endroits (`ApprovisionnementTable.tsx`, `FournisseurApprovisionnementTable.tsx`, `approvisionnement/show.html.twig`) — et il recalcule `quantité × prix` au lieu de lire le `couttotal` de la ligne, donc deux chemins pour le même nombre ;
- la règle à écrire au **README** ;
- la **commande de diagnostic temporaire** à supprimer (`TmpAuditCorbeilleCommand`) ;
- la **suite complète** à relancer (291 tests attendus ; elle était à 285 verts avant les 6 derniers, qui passent isolément).

Dis-moi quand relancer et je termine — ou si tu veux que j'enchaîne sur le statut d'annulation de `Depense`.













Relance et termine, puis vérifie si tout est cohérent.

Pour le plan, le `Tout ce qui concerne l'argent, on ne supprime pas`, etc.. on vas continuer dans dans la session suivante.




L'outil répond à nouveau. J'enchaîne les deux tableaux React.

Maintenant le README et la suppression de la commande de diagnostic.

Maintenant les deux suites complètes, séquentiellement.

Il reste à recompiler les assets — le bundle contient encore l'ancien calcul.

Les deux `reduce` restants somment des **quantités** (colonne « Nbre de pièces »), pas de l'argent — je les laisse.

Passons à la vérification de cohérence. D'abord : reste-t-il des agrégats d'argent qui auraient échappé au correctif, dans les repositories que je n'ai pas touchés ?

Dix restants à justifier un par un. Je vérifie si les lignes de détail ont une corbeille, et si les remises entrent dans un calcul de recette.

Deux points à trancher : `Detailcourrier` **a** une corbeille, et les remises sont affichées sur l'écran de recette d'une gare. Je regarde.

Le premier est justifié — le commentaire dit « signal anti-abus », c'est une surface de contrôle. Mais le second est un **oubli réel** : `parTrancheValeur` sert une recette (`AS recette`) et ignore la corbeille, alors que la recette courrier la filtre désormais. J'inventorie ce dépôt.

Je corrige l'oubli, sur les deux méthodes et les deux niveaux (le courrier et sa ligne, qui a sa propre corbeille).

Les 9 restants sont justifiés : six sont des surfaces de contrôle, trois portent sur des entités de détail sans corbeille. Je passe aux vérifications de cohérence globale.

Schéma en phase, aucun désaccord entête/lignes. Je vérifie maintenant que les chiffres réels n'ont pas bougé — puisqu'aucune ligne n'est en corbeille, ils doivent être identiques aux références relevées avant les changements.

Les quatre chiffres sont identiques aux références. Je relance le backend après la correction `Detailcourrier`.

La fiche d'approvisionnement est un écran observable. Plutôt qu'un navigateur (qui exigerait une connexion), j'utilise le harnais de rendu FT — ça laisse une sentinelle permanente. Je lis le patron.

J'écris la sentinelle de rendu. Je vérifie d'abord la route et les appels d'API de l'écran.

Mon jeu de données est incomplet — le gabarit lit d'autres champs du fournisseur. Je regarde lesquels.

Deux choses à comprendre : le « 30 000 FCFA » est le total de la **ligne** (légitime, mon assertion est mal construite), mais surtout « Montant total » semble suivi de **rien**. Je lis le gabarit.

Je ne peux pas trancher par la lecture. Je dumpe le texte rendu.

Tout fonctionne : « Montant total **130 000 FCFA** ». Le message d'erreur affichait la chaîne *après* mon `str_replace` — c'était mon assertion qui était mal conçue, elle attrapait le total de la ligne. Je la cible proprement.

Erreur d'échappement Python (`\x` dans une chaîne non brute). Je passe en chaîne brute.

Je prouve que la sentinelle tombe en remettant la resomme dans le gabarit.

La sentinelle tombe proprement. J'ajoute un garde-fou sur le pied du tableau — le piège exact déjà rencontré sur le dépannage.

La ligne 82 recalcule aussi, alors que la ligne porte son `couttotal`. Je l'aligne et j'ajoute le garde-fou au pied.

39 tests frontend, 285 gabarits. Dernier point de cohérence : les trois apps mobiles consomment la même API — je vérifie si l'une d'elles resomme les lignes d'un approvisionnement.

Aucune app ne touche aux approvisionnements. Mais elles pourraient sommer des recettes côté client — je vérifie, puisque `commercialflutter` a une vente hors-ligne.

Le mobile somme des valeurs **servies par l'API** (`v.maRecette`), il ne recalcule rien depuis les lignes — il hérite donc du correctif. Et un détail intéressant : `TicketRepository::recetteCommercialeParVoyage` ne filtrait pas la corbeille alors que son équivalent bagage le faisait déjà. L'écran « ma recette » du commercial était donc incohérent entre ses deux colonnes ; c'est réparé.

Je note ce qui est reporté à la session suivante.

Dernière vérification d'ensemble, les deux suites d'affilée.

Terminé et vérifié.

## Ce qui est livré

**La corbeille exclut désormais de tout total d'argent** — 48 agrégats de recette (`Ticket`, `Bagage`, `Courrier`), 11 de coût (`Approvisionnement`, `Depannage`), plus 2 découverts pendant la vérification finale.

**Le garde qui rend ton filtre sûr** : un approvisionnement ou un dépannage non annulé refuse la corbeille, avec la marche à suivre dans le message. Sans lui, ton filtre aurait produit du stock entré sans coût.

**`Approvisionnement::$couttotal`** : migration jouée, backfill exact sur les 5 lignes, recomposé en entier à chaque écriture, et les trois surfaces du frontend qui resommaient lisent maintenant la colonne.

**16 tests neufs** (14 backend, 2 frontend), dont **6 prouvés tombants** en réintroduisant le défaut.

## Deux choses trouvées pendant la vérification

J'ai balayé *tous* les dépôts à la recherche d'agrégats d'argent non filtrés. Il en restait deux vrais oublis dans `DetailcourrierRepository` : `parTrancheValeur` sert une recette (son alias le dit) et ignorait la corbeille — la ventilation par tranche de valeur aurait donc dépassé la recette courrier dont elle est le détail. Corrigé à **deux niveaux**, car `Detailcourrier` étend `EntityBase` : une ligne peut partir à la corbeille sans son courrier.

Et une incohérence sur l'écran du commercial : `TicketRepository::recetteCommercialeParVoyage` ne filtrait pas la corbeille alors que son équivalent bagage le faisait déjà. Les deux colonnes de « ma recette » ne suivaient donc pas la même règle. Le mobile somme des valeurs servies par l'API, il hérite du correctif sans changement.

Les 9 agrégats restants sans filtre sont justifiés un par un : 6 sont des surfaces de contrôle anti-fraude — un tableau qui traque la vente hors-livre doit voir ce qu'on a tenté d'effacer — et 3 portent sur des entités de détail qui n'ont pas de corbeille.

## Une erreur que j'ai corrigée

Mon argument pour justifier `couttotal` était faux : « un approvisionnement sans ligne disparaît du coût » ne tient pas, sa contribution vaut zéro. Je l'ai découvert parce que **ma sentinelle ne tombait pas** quand j'ai remis la jointure. Ce qu'elle faussait vraiment, c'est le `COUNT(DISTINCT a.id)` du compteur. Le test vise maintenant le compteur et tombe (1 au lieu de 2), et les trois textes qui affirmaient le faux sont rectifiés.

Même chose sur le test de rendu : ma première assertion cherchait « 30 000 » dans toute la page et tombait sur le total de la ligne de détail, qui vaut légitimement 30 000 — elle échouait sur du code juste. Elle cible maintenant le chiffre qui suit « Montant total ».

## Cohérence vérifiée

| contrôle | résultat |
|---|---|
| mapping ↔ base | en phase, aucune migration en attente |
| entête `couttotal` ↔ lignes | 5 approvisionnements, **0 désaccord** |
| billets IRA (réf. 9 346 600) | **9 346 600** |
| appros (réf. 4 966 000 / 126 000) | **4 966 000 / 126 000** |
| dépannages (réf. 2 289 200) | **2 289 200** |
| tests | **291 backend + 39 frontend**, 285 gabarits lintés |
| bundle recompilé | ancien calcul absent, 4 lectures de la colonne |
| fichier temporaire | commande de diagnostic supprimée |

Aucun chiffre réel n'a bougé — normal, il n'y a **aucune ligne en corbeille** dans ta base. C'est un correctif préventif, comme le hors-CA : invisible aujourd'hui, et c'est exactement pourquoi il fallait des sentinelles plutôt que des fixtures.

Le principe « l'argent ne se supprime pas » est écrit au README, avec le raisonnement qui décide entre `ANNULE` et corbeille : *y a-t-il un effet physique à défaire ?* J'ai noté en mémoire les deux sujets reportés — `Depense` sans statut, qui reste la dernière entorse, et le plan caisse avec son trou symétrique non tranché.










Oui, corrige les deux passages de doc.

Ensuite, concernant les risques connus, non corrigés :
- Corrige le `/api/stats/agent répond 500 sur une vente sans auteur (aucun cas en base aujourd'hui)`
- Corrige le `MediaObject n'a pas de rattachement à l'entreprise : qui devine un id lit le fichier (justificatifs, bulletins de salaire).`
- Propose moi des icônes pour l'application






Je commence par les deux corrections de doc, puis j'attaque les deux défauts (en mesurant d'abord), et je finirai par les icônes.

Je vais vérifier la règle actuelle dans les repositories avant de modifier le plan, ces éléments datant d'avant le 28/09.

Confirmé : les coûts excluent bien les éléments annulés ET supprimés, et la corbeille est refusée sur un appro/dépannage non annulé. Je mets à jour le tableau et la sentinelle n°7 du plan.

Le plan caisse est corrigé ; je corrige maintenant la ligne du README backend, puis je passe à `/api/stats/agent`.

Je trouve un test `StatsAgentSansAuteurTest.php` non commité et un docbloc dans `AgentStatsProvider` mentionnant déjà ce TypeError — le correctif a peut-être été appliqué en fin de session précédente sans mise à jour de la doc. Je vérifie avant toute modification.

Le correctif est déjà présent dans le code (non commité) et son test passe parmi les 291, mais le README et la mémoire indiquent encore « non corrigée ». Je vérifie maintenant le diff d'origine et confirme que la sentinelle échoue bien si on réintroduit le défaut.

Je vais confirmer que le test échoue bien avec le code d'origine : je remets temporairement la version commitée du provider, relance le test, puis restaure le correctif.

Le test confirme que l'ancien code plantait bien avec un 500 sur /api/stats/agent, corrigé dans la version actuelle. En revanche le 3e test sur l'absence d'agent n°0 restait vert même sur l'ancien code, donc je vais comprendre pourquoi il ne détecte rien.

J'ai trouvé que la requête `annulationsParAgent` filtre déjà `c.updatedBy IS NOT NULL` en SQL, donc les lignes sans auteur n'atteignent jamais le PHP. Je vérifie maintenant les six autres sources du tableau anti-fraude pour voir si l'une d'elles laisse passer un auteur nul.

Je vérifie si le 3e test échoue réellement quand on retire les deux protections (SQL et PHP), car son commentaire décrit un bug qui n'a jamais pu se produire vu que les 7 sources filtrent déjà `IS NOT NULL`.

Confirmé : le 3e test échoue seulement si les deux protections (SQL et PHP) sont retirées, ce qui montre une double sécurité plutôt qu'un vrai défaut. Je vais réécrire le commentaire en conséquence, puis vérifier si le FT affiche la recette non attribuée.

Le FT affiche déjà ce montant (bandeau dans `home/agent.html.twig`). Le correctif est donc complet de bout en bout ; il ne reste que le README, qui dit encore « non corrigée ».

Je passe à `MediaObject` : le vrai risque dépend de si les fichiers sont servis en statique depuis `public/`, ce qui rendrait la protection de la route d'API insuffisante. Je vérifie l'entité, la config Vich et ses usages.

J'ai identifié que Vich stocke les fichiers dans `public/images/media`, servis en statique sans authentification — sécuriser uniquement la route API ne suffira donc pas. Je vais maintenant examiner comment ces fichiers sont consommés (FT, apps, PDF) avant de choisir la correction.

La base ne contient aucun média pour l'instant : aucune donnée à migrer. Je lis le processeur qui pose `identreprise` pour m'y brancher proprement.

Je vérifie qui consulte les médias — FT, apps mobiles, et les deux chemins non filtrés passant par le repository (`MeEntrepriseProcessor`, inscription) — avant de décider comment corriger le processeur d'`identreprise`.

Je vérifie deux points : l'appel à la route backend `/media…` pour redimensionner les images, et l'utilisation directe de `contentUrl` pour le lien du justificatif, qui pourrait pointer vers le mauvais hôte.

J'ajoute deux constats à la correction : les fichiers sous `public/` restent accessibles sans authentification (statique ou via Glide), et le lien « voir le justificatif » du FT semble cassé car `contentUrl` pointe vers l'hôte du FT sans préfixe d'API. Je vais maintenant examiner les chemins qui rattachent un média par id sans passer par le filtre de périmètre.

Diagnostic terminé : je vais rattacher les MediaObject à l'entreprise via `identreprise`, corriger le filtre manquant dans `MeEntrepriseProcessor`, passer les justificatifs de dépenses en stockage privé (accès via une route dédiée respectant permissions et périmètre de gare), ajouter une route proxy côté FT, et garder les images publiques via Glide. Je vais maintenant lire les fichiers concernés pour implémenter tout ça.

Le docbloc de `DepenseProcessor` confirme qu'un média portant `identreprise` deviendra introuvable via la résolution d'IRI. Je consulte maintenant le harnais de test pour voir comment lui envoyer un fichier.

J'utiliserai `autoriser()` dans le `ScenarioBuilder` pour gérer les droits. Avant de coder, je vais vérifier comment ce projet connecte un contrôleur personnalisé à une opération API Platform, ce qui détermine la route de téléchargement.

Je note que `use_symfony_listeners: true` permet à une opération `Get` avec contrôleur de recevoir la dépense déjà chargée et validée, et que le patron `NotFoundAction` de `Permission` bloque la lecture directe d'un média tout en gardant la résolution de son IRI. Je passe maintenant à la lecture du côté FT.

J'ai tout ce qu'il faut ; je passe à l'écriture côté backend, en commençant par l'entité `MediaObject`.

Maintenant le registre de la corbeille, puis la règle d'accrochage dans `DepenseProcessor`.

Je branche la route de téléchargement `GET /api/depenses/{id}/justificatif` sur la ressource `Depense`, avec son contrôleur.

Au tour de `MeEntrepriseProcessor`, qui accroche le logo par un `find()` sans aucun filtre.

Le logo se modifie par l'administrateur de SA compagnie (`/api/me/entreprise`) : le média qu'il vient de téléverser porte donc bien son entreprise. Je borne le `find()`.

Côté code backend, tout est en place. Je génère la migration par `doctrine:migrations:diff`, qui ne fait que **lire** la base de dev, puis je la relis.

Le diff ne concerne que `media_object` : je le renomme selon la convention du projet, ajoute le docbloc, et prévois un rattrapage pour d'éventuels médias déjà rattachés.

Je vérifie en lecture seule que les colonnes de jointure du rattrapage existent bien sous ces noms.

Colonnes confirmées. Je passe aux tests : je regarde d'abord l'opération « ma compagnie » (pour le cas du logo) et les propriétés accessibles du harnais.

Je mets à jour le schéma de la base de **test** (`make test-db`, base `bk_transport_test` réservée à la suite), puis j'écris les tests.

8 sur 9 passent. L'échec vient de mon assertion sur la forme de la réponse (le justificatif n'y porte pas de `contentUrl`), pas de la protection. Je regarde ce que la fiche renvoie réellement.

Je confirme que `contentUrl` vaut bien `null`, mais mon assertion était erronée car `??` traite `null` comme absent. Je la corrige pour vérifier séparément la présence de la clé et sa nullité.

Mon remplacement scripté a laissé un résidu (`");`) sur une ligne. Je le retire.

Je crée les deux lecteurs supplémentaires directement dans le `setUp`, avant toute requête, pour éviter le piège connu où l'EntityManager est vidé après une requête HTTP et détache les entités du `setUp`.

Les 9 tests passent. Je vérifie maintenant que **chaque sentinelle tombe** quand on réintroduit le défaut qu'elle garde : sept mutations, appliquées puis annulées une par une.

Les 7 sentinelles tombent chacune sur le défaut qu'elles gardent, et l'état est restauré. Je lance toute la suite backend pour vérifier qu'il n'y a pas de régression.

Backend : **300 tests verts** (291 + 9 nouveaux). Je passe au FT : upload en privé, route proxy de consultation, et les trois liens (fiche, formulaire, tableau React).

Je modifie `ApiHelper::postMediaObject` pour qu'il sache demander un stockage privé.

Les liens, maintenant : fiche, formulaire et tableau React.

Je vérifie ce que lit la fiche dépense, pour écrire un test FT qui la **rend** avec un justificatif privé (`contentUrl: null`).

42 tests passent (39 + 3). Je vérifie que les erreurs `tsc` viennent bien de `node_modules` (préexistantes, hors nos fichiers), puis que la sentinelle du lien pointe correctement vers l'ancien gabarit.

Le fichier est restauré et les 2 erreurs `tsc` viennent uniquement de `node_modules` (types Radix), sans impact sur notre code. Je reconstruis maintenant les assets du FT suite au changement du tableau React.

Les assets sont reconstruits. Attention : l'entité `MediaObject` référence des colonnes absentes de ta base de dev, ce qui causerait des erreurs « Unknown column » sur toute page chargeant un média — je te demande donc ton accord avant de lancer la migration en base.

Migration jouée : le schéma est synchronisé avec le mapping. Je prépare la vérification dans le navigateur. Je regarde d'abord comment les serveurs de dev se lancent.

Les deux serveurs sont opérationnels. Je vais utiliser les comptes de démo pour tester le parcours : un admin de gare qui dépose, puis l'admin d'entreprise et le chef d'une autre gare qui consultent.

Je me connecte au FT (localhost:8002) avec le compte du chef de gare d'Abidjan, tiré des fixtures.

Toujours sur la page de connexion. Je regarde ce qu'elle affiche.

Aucun message d'erreur affiché. Je regarde les journaux du FT pour voir si le formulaire a bien été soumis.

La connexion échoue : le mot de passe des fixtures ne correspond plus au compte, probablement changé sur le banc d'essai. Je vérifie donc le parcours au niveau de l'API avec des jetons générés via `lexik:jwt:generate-token`, sans réessayer de mot de passe pour éviter l'anti-force-brute.

L'upload privé fonctionne (`contentUrl: null`, fichier hors de `public/`), mais la création de la dépense a répondu **400**. Je lis la réponse avant de conclure quoi que ce soit.

C'est un artefact d'encodage du shell Windows sur le « é », pas un bug de l'application. Je réécris le corps en UTF-8 via un fichier et je reprends à l'étape 3 avec le même média.

J'ai vérifié le parcours complet sur l'API réelle : le dépôt privé reste inaccessible statiquement, l'auteur et l'admin lisent bien le PDF, les autres profils reçoivent 404/401, et l'accès à un document d'autrui renvoie 403. Je supprime maintenant ma dépense de test (1 000 FCFA, Adjamé) pour qu'elle ne fausse plus tes totaux.

La commande a été bloquée par une vérification de sécurité à cause d'un `rm`, donc rien n'a été exécuté. Je la relance sans ce nettoyage, les fichiers resteront simplement dans le dossier temporaire.

La dépense 593 est bien à la corbeille ; il reste deux médias de test orphelins sans impact sur les totaux, que je signalerai. J'arrête les deux serveurs lancés, puis je passe à la documentation.

Je termine la documentation : il reste à signaler en production que `var/documents` contient des pièces comptables à sauvegarder.

Documentation à jour dans les deux README. Je mets à jour la mémoire : le point « non corrigé » de l'audit, et une leçon pratique pour les prochaines vérifications.

Mémoire mise à jour. Je passe aux icônes : je vérifie d'abord le manifeste, les icônes actuelles et la couleur principale de l'application pour que mes propositions restent cohérentes avec l'identité visuelle.

La couleur de marque est l'ambre `#f59e0b` (le `--primary` du thème). Et le favicon actuel est encore celui de Symfony (« sf » sur rond noir). Je regarde l'icône PWA actuelle.

Mascotte de démo (un raton laveur) : ça confirme la note du README. Je prépare des propositions visuelles d'icônes, dessinées dans l'ambre de la marque et directement comparables.

Je vais dessiner quatre aperçus : icône d'application, variante maskable Android, et favicons 32/16 px, pour voir où l'icône reste lisible.

Les deux passages de doc sont corrigés, les deux risques sont traités, et quatre pistes d'icônes sont affichées ci-dessus. Tout est vert : **300 tests backend** (9 nouveaux) et **42 tests frontend** (3 nouveaux). Rien n'est commité.

## 1. Doc corrigée
- **README backend** : la ligne sur le « crochet ESPECES » et la « FK nullable sur `Depense` » dit maintenant l'inverse et renvoie aux décisions du 28/09.
- **Plan caisse** : en plus du coût d'appro (désormais sur l'en-tête), deux autres passages étaient périmés, je les ai corrigés aussi. Le tableau des exclusions disait que la corbeille d'un appro « ne gère que la visibilité », et la sentinelle n° 7 attendait l'ancien comportement. Règle retenue : le solde exclut exactement ce que le bénéfice exclut.

## 2. `/api/stats/agent` et la vente sans auteur
Le correctif **existait déjà** dans le code (non commité). Seuls le README et la mémoire disaient « non corrigé ».
- **Vérifié** : sur le code d'origine, le test reproduit bien le 500 ; sur le code actuel, il passe.
- **Un test ne gardait rien** : celui du « pas d'agent n° 0 » restait vert même sur le code d'origine. Les 7 requêtes anti-fraude filtrent déjà l'auteur nul en SQL. J'ai réécrit son commentaire pour dire ce qu'il protège vraiment.

## 3. `MediaObject`
Le risque était plus large qu'annoncé : les fichiers vivaient sous `public/`, lisibles **sans aucun compte** (en direct ou via Glide). Quatre accès sont fermés :
- **Lecture directe** d'un média par son id : fermée.
- **Média d'une autre compagnie** : il porte maintenant `identreprise` et ne peut plus être accroché à une fiche (400).
- **Justificatifs** : stockés hors du web (`var/documents`), sans URL. On ne les lit que par `GET /api/depenses/{id}/justificatif`, avec les droits de la dépense. Un agent ne peut accrocher que ce qu'il a lui-même déposé ; sans ça, il pouvait rattacher un bulletin du siège à sa propre dépense et le lire.
- **Logo** (`MeEntrepriseProcessor`) : le `find()` sans filtre est maintenant limité à la compagnie.

Le lien « voir le justificatif » du FT était **cassé** (404 à chaque clic) ; il passe maintenant par un relais du FT.

**Vérification :**
- Chaque test tombe quand on remet le défaut qu'il garde (7 défauts remis un à un).
- Parcours réel sur l'API : l'auteur et l'admin reçoivent le PDF à l'identique ; le chef de Korhogo et l'admin de Sahel obtiennent 404 ; un appel sans compte, 401.
- **Pas vérifié dans le navigateur** : le mot de passe des fixtures est refusé sur ta base (tu l'as dû changer). Le relais du FT est couvert par son test.

**À savoir :**
- Migration `Version20260928180000` jouée avec ton accord.
- Il reste des données de test dans ta base : la dépense 593 (1 000 FCFA, à la corbeille) et deux médias privés (ids 1 et 2, sans effet sur aucun total).
- **Toujours ouvert** : les images restent lisibles par qui connaît leur adresse, et Glide tourne sans signature (n'importe qui peut remplir son cache).
- **En production**, `var/documents` contient des pièces comptables : il faudra le sauvegarder. C'est noté dans le README.

## 4. Icônes
Je recommande **B, la ligne et ses arrêts**. Elle reprend le cœur de l'application (origine, arrêt, terminus), elle se distingue du car que toutes les compagnies affichent, et elle reste lisible à 16 px. **A** est l'option la plus sûre si tu veux qu'on reconnaisse le métier au premier coup d'œil. **C** se lit mal en petit.

Au passage, le favicon actuel est encore celui de Symfony (« sf »). Une fois ton choix fait, je génère les PNG 192/512, la variante ronde pour Android (`maskable`), l'icône Apple et un favicon SVG. Je remplace ensuite le favicon dans les deux gabarits de base et je mets à jour le manifeste. Le même dessin pourra servir aux trois apps mobiles.









Les justificatifs sont désormais dans public/documents (fichiers ignorés par git, .htaccess versionné). Je passe maintenant à la signature Glide, en vérifiant d'abord son interface exacte.




## 1. Icônes (concept B)
Tout est dans `Frontend-Transport/public/icons/` :
- icônes d'application 192 et 512 (coins arrondis) ;
- variantes `maskable` pour Android ;
- `apple-touch-icon` ;
- `favicon.svg`, un dessin agrandi et au trait épaissi pour rester lisible à 16 px, plus un `favicon.ico` (16, 32, 48 px).

Les concepts A, C et D sont rangés dans `public/icons/propositions/` (SVG + PNG 512). Le manifeste est à jour. Le favicon « sf » de Symfony et la mascotte de démo sont retirés ; les deux images de la mascotte sont passées par `git rm`, donc récupérables. Les rendus ont été faits par Chrome en local, sans rien télécharger.

## 2. Justificatifs dans `public/documents`
- Le dossier est interdit par un `.htaccess` (versionné). J'ai ajouté une seconde barrière : des noms aléatoires de 128 bits au lieu de `uniqid()`, qui se déduit de l'heure du dépôt.
- **Limite mesurée** : `symfony serve` ignore le `.htaccess` et sert ces fichiers (200). En dev, seul le nom aléatoire les protège ; sous Nginx, il faut ajouter une règle, que j'ai notée dans le README.
- J'ai versionné `public/documents/sonde.txt`. En production, `curl -I …/documents/sonde.txt` doit répondre **403**. Tester le `.htaccess` lui-même ne prouverait rien : Apache refuse toujours les fichiers `.ht*`.
- J'ai déplacé les 3 fichiers existants depuis `var/documents`. Le justificatif de ta dépense 594 est toujours servi (vérifié). Ces 3 fichiers gardent leur ancien nom, devinable en dev ; je peux les renommer (avec mise à jour en base) si tu veux.

Une chose conservée volontairement : la garde qui limite Glide aux dossiers images/. Ce n'est pas la signature. Sans elle, /media/documents/<nom> ferait lire un justificatif par PHP et contournerait ton .htaccess. Elle est couverte par un test, qui échoue bien si on retire la garde







2. Thème : refonte annulée, deux corrections gardées
On est revenu au mécanisme d'avant (préchargement + theme.js), avec deux changements :
Doublon supprimé : l'ancien script en ligne a disparu des deux gabarits racines. Un test vérifie qu'il ne reste qu'un seul script de thème, placé avant la feuille de style.
color-scheme déclaré sur :root (clair) et .dark (sombre). Sélecteurs de date, listes natives et barres de défilement de Firefox suivent désormais le thème. Chrome, Edge et Safari affichaient déjà tes barres personnalisées aux couleurs du thème.
Vérifié dans le navigateur dans les quatre cas (mode système sur poste clair puis sombre, clair forcé, sombre forcé), sur la page et sur un champ date.




Le seul défaut : lint:container échoue sur le backend parce que VichUploader déclare un type de formulaire alors que symfony/form n'est pas installé. C'est sans effet à l'exécution, et antérieur : l'échec est identique avec la configuration de git. La correction tient en une ligne, form: false dans vich_uploader.yaml. Je ne l'ai pas appliquée, puisque tu m'as demandé une vérification.


Confirmé — dans la configuration officielle de VichUploaderBundle, il y a bien une clé racine dédiée à ça :

```yaml
# config/packages/vich_uploader.yaml
vich_uploader:
    db_driver: orm
    twig: true
    form: false    # désactive l'intégration form
```

Réglez simplement form: true # set to false to disable form integration sur `false`.

**Ce que ça désactive concrètement** : ça retire du conteneur de services l'intégration de Vich avec le composant Form de Symfony — notamment les types de formulaire `VichFileType` et `VichImageType`. Si votre code utilise encore `Vich\UploaderBundle\Form\Type\VichImageType` (ou `VichFileType`) dans un de vos `FormType`, ça provoquera une erreur au chargement du formulaire une fois désactivé.

**Avant de le désactiver**, demandez-vous pourquoi : la plupart des projets gardent `form: true` par défaut, car c'est ce qui permet d'avoir le champ d'upload avec case "supprimer le fichier" directement dans vos formulaires Symfony. On ne le désactive généralement que si vous gérez les uploads entièrement par un autre moyen (API séparée, upload manuel hors formulaire Symfony, etc.).

Dans votre cas précis, qu'est-ce qui vous pousse à vouloir le désactiver — un conflit avec un autre bundle, ou vous gérez les uploads autrement ?