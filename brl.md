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
- SEO







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


prompts.chat
test de montée en charge localhost
session local changer email


**K6 - Test de montée en charge**
Je vais vérifier les outils et pratiques actuels avant de répondre, certains outils évoluant rapidement.# Tests de montée en charge dans Symfony

L'objectif : vérifier que l'appli tient la charge attendue, trouver le point de rupture, et repérer les goulots d'étranglement avant que les utilisateurs ne le fassent à ta place. Voici comment structurer ça.

## 1. Préparer un environnement représentatif de la prod

C'est l'étape la plus souvent bâclée, et elle fausse tout le reste si elle est mal faite :

- **Environnement `prod`, jamais `dev`** : `APP_ENV=prod` et `APP_DEBUG=0`. Le profiler Symfony et la Web Debug Toolbar ajoutent un overhead énorme et rendent les mesures inutilisables.
- **Cache "chaud"** avant de lancer quoi que ce soit :
  ```bash
  composer install --no-dev --optimize-autoloader
  php bin/console cache:clear --env=prod
  php bin/console cache:warmup --env=prod
  ```
- **OPcache activé et réglé pour la prod** (`opcache.validate_timestamps=0`, mémoire et `max_accelerated_files` suffisants).
- **Un jeu de données réaliste en volume**, dans une base dédiée aux tests — jamais la prod.
- **Le même runtime que la prod.** Si tu es passé sur FrankenPHP en mode worker (nativement supporté depuis Symfony 7.4 LTS, plus besoin d'installer de package séparépuisque Symfony 7.4 supporte nativement ce mode dès l'installation), les gains sont réels : les benchmarks montrent des temps de réponse divisés par trois à cinq par rapport à PHP-FPM sur des applications Symfony. Mais ce mode change la donne pour tes tests de charge : l'application reste chargée en mémoire entre les requêtes, donc tout service qui garde un état lié à la requête doit implémenter `ResetInterface` pour être réinitialisé proprement par le kernel — sinon tu peux avoir des fuites d'état ou de mémoire qui n'apparaissent qu'après plusieurs minutes de charge soutenue. C'est exactement le genre de bug qu'un test de charge est censé révéler, donc autant le savoir avant.

## 2. Choisir l'outil

PHPUnit/WebTestCase servent aux tests fonctionnels, pas à simuler des centaines d'utilisateurs simultanés — il te faut un outil dédié :

- **k6** (Grafana) — le standard actuel du marché. Outil open source où les scripts s'écrivent en JavaScript ou TypeScript, pensé développeur. Tu définis des seuils de type SLO (des thresholds qui fixent les critères de succès/échec du test, et des checks pour valider les réponses de l'API), des montées progressives d'utilisateurs virtuels, des paliers. S'intègre bien en CI.
- **Gatling** — alternative solide (Scala), rapports HTML détaillés. Un consultant qui audite des applications Symfony l'utilise pour simuler des charges importantes et identifier les goulots d'étranglement quand les tests de performance basiques (navigateur, Postman) ne suffisent plus.
- **Apache Bench / hey / wrk** — pour un check rapide et brutal sur un seul endpoint, sans scénario.
- **Blackfire (Player + Scenarios)** — spécifique à l'écosystème PHP, très intégré à Symfony : les scénarios Blackfire peuvent déclencher un profil pour chaque requête et lancer des tests de performance via le flag --blackfire. Ça permet de voir directement dans le code où ça coince pendant la montée en charge, plutôt que de mesurer en boîte noire.

D'ailleurs, la documentation officielle de FrankenPHP recommande k6 ou Gatling pour calibrer le nombre de workers et de threads en fonction de tests simulant un trafic réel — c'est un bon signal que ce sont les outils à privilégier dans ce contexte.

## 3. Écrire un scénario réaliste

Ne tape pas juste un endpoint en boucle — simule un vrai parcours (connexion, navigation, recherche, action métier), avec différents types de test selon l'objectif : **smoke** (ça tourne, faible charge), **load** (charge nominale attendue), **stress** (monter jusqu'à la casse), **spike** (pic brutal), **soak/endurance** (charge modérée mais prolongée, pour détecter fuites mémoire et connexions DB non libérées).

Exemple avec k6 contre une API Symfony :

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  scenarios: {
    montee_en_charge: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 50 },   // montée à 50 utilisateurs virtuels
        { duration: '3m', target: 50 },   // palier stable
        { duration: '1m', target: 200 },  // pic
        { duration: '2m', target: 200 },
        { duration: '1m', target: 0 },    // retour au calme
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<300'],  // 95 % des requêtes sous 300 ms
    http_req_failed: ['rate<0.01'],    // moins de 1 % d'échecs
  },
};

export default function () {
  const res = http.get('https://staging.monapp.fr/api/produits');
  check(res, {
    'statut 200': (r) => r.status === 200,
    'temps correct': (r) => r.timings.duration < 500,
  });
  sleep(1);
}
```

Lancement : `k6 run test-charge.js`

## 4. Surveiller le serveur pendant le test

Le résultat de l'outil de charge (temps de réponse, taux d'erreur) ne dit pas *pourquoi* ça casse. Pendant le run, garde un œil sur :

- CPU / RAM du serveur applicatif
- Process PHP-FPM occupés (ou workers FrankenPHP saturés) — souvent le vrai plafond
- Connexions actives et requêtes lentes côté base de données (slow query log)
- Hit rate du cache (Redis/Memcached)
- La queue Messenger si tu as des traitements asynchrones, pour vérifier qu'elle ne s'accumule pas

## 5. Pièges classiques côté Symfony

- **Requêtes N+1 avec Doctrine** : invisibles à faible charge, elles explosent le nombre de connexions DB dès que le trafic monte. Le profiler Symfony ou Blackfire les repèrent bien.
- **Sessions sur fichiers** (comportement par défaut) : ça devient un goulot d'étranglement dès qu'il y a plusieurs instances ou une forte concurrence — passe sur Redis ou une base pour le stockage de session.
- **Absence de pagination** sur des listes qui grossissent avec le temps.
- **Traitements lourds synchrones** dans le contrôleur (envoi d'e-mail, appel à une API externe) qui devraient passer par Messenger pour ne pas monopoliser un worker PHP pendant tout ce temps.

Le test n'a de valeur que si tu boucles : tester → identifier le goulot avec un profiler → corriger → retester dans les mêmes conditions pour comparer objectivement. Garde les mêmes scénarios k6/Gatling d'un run à l'autre pour que la comparaison soit valable.Si tu veux, je peux aussi t'aider à écrire un scénario k6 complet adapté à tes vrais endpoints, ou à diagnostiquer un goulot d'étranglement précis que tu as déjà observé.









- Pour le B4 (Réclamations et indemnisations), je me dis que vu qu'on la notion de dépense ce n'est pas nécéssaire ! est ce que j'ai raison ?

- Passer sur PostgreSQL

D2(sans archive les alertes résolues au-delà d'une durée de conservation) mais sans A4 mais sans que le les lignes partent dans une table d'archive interrogeable mais hors du chemin chaud
    - Commande pour sauvagarder la bd, aussi pour vider les tables refresh etc.. (Ne pas oublié de prévoir pour postgre)





Je pérfère avoir une page pour changer l'email
- On vas mettre en place les tests de montée en charge


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
