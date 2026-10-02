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
- K6 test..


## Points ouverts que j'ai relevés

- Mobile Money réel : toujours simulé, en attente d'un agrégateur souscrit.
- commercialflutter « à venir » : bilan de recette par période (nécessite un endpoint dédié, `/api/stats/commercial` étant admin) et fidélité à la vente.
- Alertes : seuils encore en constantes de service, externalisation par entreprise non faite. Pas de notifications côté mobile.
- Piste notée mais non entamée : visite interactive à la première connexion (Driver.js / Shepherd.js).
**Au déploiement en production**
- Rendre `public/documents` inscriptible, le **sauvegarder**, et vérifier que `curl -I https://<hôte>/documents/sonde.txt` répond **403**. Sous Nginx, il faut ajouter une règle, décrite dans le README.
- Glide tourne sans signature : c'est ton choix, et le risque d'abus de redimensionnement reste ouvert.
- 

- 
- Vérifié les permissions donnée aux utilisateur en faisant les actions
- Vérifier si 2 utilisateurs de même ligne voient les même infos
- dédié annuler à l'admin !! Voir qui peut annuler et à quel moment



- Pour le B4 (Réclamations et indemnisations), je me dis que vu qu'on la notion de dépense ce n'est pas nécéssaire ! est ce que j'ai raison ?
D2(sans archive les alertes résolues au-delà d'une durée de conservation) mais sans A4 mais sans que le les lignes partent dans une table d'archive interrogeable mais hors du chemin chaud
    - Commande pour sauvagarder la bd, aussi pour vider les tables refresh etc.. (Ne pas oublié de prévoir pour postgre)

- Passer sur PostgreSQL









Bonne question, et je préfère y répondre sur pièces plutôt que de mémoire. Je relève ce qui existe déjà.

19 pages, 28 endpoints — c'est déjà très fourni. Je vérifie ce que les données permettent et que personne n'expose encore.

L'application en expose déjà beaucoup : **19 pages, 28 endpoints**. La question utile n'est donc pas « qu'est-ce qu'on peut sortir de plus » — on peut toujours sortir plus — mais **qu'est-ce qui manque et qui changerait une décision**. J'ai vérifié les angles morts plutôt que de lister ce qui existe.

## Ce qui manque vraiment

**1. La fiabilité de caisse par agent — l'angle mort complet**

Aucune statistique ne touche `Sessioncaisse` : les écarts ne se voient que caisse par caisse, jamais agrégés. C'est paradoxal, parce que c'est la **seule donnée de contrôle** du projet — tout le reste mesure l'activité, elle seule mesure la véracité. Vous avez déjà le nécessaire : écart signé, motif, agent, gare, date.

La décision qu'elle sert : qui former, qui surveiller, quelle gare dérive. Un agent à ±200 F de moyenne et un agent à ±5 000 F ne demandent pas la même chose, et aujourd'hui rien ne les distingue à l'œil.

Attention au piège déjà documenté : il faudra écarter les attendus négatifs, sinon le classement sera pollué par de faux excédents.

**2. Ce que coûtent les désistements, en argent**

Les désistements sont comptés partout — annulés, reportés, dont imputables à la compagnie. Mais `montantrembourse` **n'est exposé dans aucune statistique**. On sait combien de billets sont annulés, jamais combien d'argent est ressorti.

La décision : chiffrer ce que votre propre désorganisation vous coûte. Un départ avancé produit un report imputable à la compagnie ; le compte existe, le montant non. C'est la différence entre « 42 reports ce mois-ci » et « 380 000 F remboursés dont 120 000 de notre fait ».

**3. Le résultat par ligne**

`VoyageResultatService` calcule le résultat d'**un** voyage, pour sa fiche. Rien ne l'agrège par ligne sur une période. Vous avez pourtant le résultat par gare (`DepenseGareService`).

La décision : quelle ligne mérite un car de plus, laquelle on arrête. C'est sans doute la statistique la plus structurante pour une compagnie, et c'est un assemblage de briques existantes.

**4. L'entonnoir des réservations par canal**

`Reservation` porte `source` (guichet / mobile), `etatpaiement`, le statut, la pénalité et le complément. Les statuts sont comptés ; la **conversion par canal** ne l'est pas.

La décision : le canal mobile rapporte-t-il réellement, ou produit-il surtout des bons payés que personne ne vient honorer ? Émises → payées → présentées → régularisées → perdues, par canal : quatre chiffres qui tranchent la question.

## Mon classement

**La caisse d'abord** : angle mort total, données déjà là, et c'est du contrôle, pas du confort. **Le résultat par ligne ensuite** : plus de travail, mais c'est la décision la plus lourde que vous prenez. Les désistements chiffrés sont rapides à ajouter aux pages existantes. L'entonnoir des réservations peut attendre d'avoir du volume mobile réel.

## Une réserve

Vous aviez vous-même dit « on peut centraliser les statistiques de recette pour que l'admin ne se perde pas ». Avec 19 pages, le risque n'est plus le manque d'information mais sa dispersion — une statistique que personne n'ouvre ne vaut rien. Avant d'en ajouter quatre, il y a peut-être plus à gagner à ranger ce qui existe. Je peux faire l'inventaire de ce qui est réellement consulté si l'activité est tracée, plutôt que de supposer.









- - 
Moyen de paiement, on vas mettre plusieurs, mais on vas commencer par Stripe
Le Mobile Money reste hors périmètre en attendant l'agrégateur
- Mobile money -> Frais payé par le client + un plus pour les timbres du genre au lieu de 100F on ajoute 200F

- Générer l'impression côté Backend et donner la possibilités au Frontend de choisir le modèle qu'il souhaite, donc on aura plusieurs formats de tickets(aussi, courriers, bagage, bordereau..) :: Regarde d'autres endroit de l'application dans lequel il est logique de dédié toutes les tâches lourdes au backend, c'est qui est logique dans une architecture séparé
- B2 Contrôle d'embarquement par QR => Aussi peut on doit on le faire côté serveur :: mais met en commentaire le code existant


- Le polling du alerte

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

- Revoire le cas de désistement qui se fait que sur un voyage de la même ligne ! Je pense que je fais les choses bien ici => Vérifié si ça va sur un autre départ de la même ligne
    > Si un client désiste pour un voyage est ce que le nouveau voyage doit être forcément sur la même ligne ou sur n'importe quel ligne
    > Ou.. Un client peut être reporté sur n'importe quel voyage ouvert qui dessert sa gare de départ et sa gare d'arrivée, même si ce voyage appartient à une autre ligne. Ce qui compte est la possibilité réelle d'effectuer le trajet acheté.
- Auth client réservation via numéro de téléphone
- Retour client et agent vi codeqr et peut envoyer des images, vidéos etc...
- Prévenir les alertes via une baffe ou aussi ce qui se fait dans les aéroports qui averti le départ..
- Quand tu regarde l'appli comment je pourrais écrire une explication ou guide pour les endroits éssenciel de l'application
- La possibilités d'activer ou désactiver des modules dans l'application