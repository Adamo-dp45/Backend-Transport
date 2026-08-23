<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Socle des tests d'API : requêtes HTTP réelles à travers le noyau, pare-feu et sérialisation
 * compris.
 *
 * Ce que ces tests protègent, et que les tests de service ne peuvent pas voir : le pare-feu JWT,
 * les expressions 'security:' des opérations API Platform, les extensions Doctrine de périmètre
 * (entreprise et gare) branchées sur le pipeline, et la forme réellement sérialisée des réponses.
 *
 * LE REDÉMARRAGE DU NOYAU EST DÉSACTIVÉ : le client de test recrée sinon le conteneur entre deux
 * requêtes, donc un nouvel EntityManager sur une nouvelle connexion — la transaction d'isolation
 * serait perdue et les données construites par le test invisibles côté serveur.
 */
abstract class ApiTestCase extends WebTestCase
{
    use BaseDeDonneesTestTrait;

    protected KernelBrowser $client;

    protected EntityManagerInterface $em;

    protected ScenarioBuilder $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->ouvrirTransactionDeTest($this->em->getConnection());

        $this->reinitialiserLimiteurDeConnexion();

        $this->scenario = new ScenarioBuilder($this->em);
    }

    /**
     * Le compteur anti-force-brute ('login_throttling') vit dans le CACHE, pas en base : il échappe
     * donc à l'annulation de transaction et survit même d'une exécution de la suite à l'autre. Sans
     * cette remise à zéro, quelques tests de connexion suffisent à déclencher un 429 qui fait
     * échouer des tests sans rapport — et le suivant hérite du blocage pendant quinze minutes.
     */
    protected function reinitialiserLimiteurDeConnexion(): void
    {
        $conteneur = static::getContainer();
        if ($conteneur->has('cache.rate_limiter')) {
            $conteneur->get('cache.rate_limiter')->clear();
        }
    }

    protected function tearDown(): void
    {
        $this->annulerTransactionDeTest($this->em->getConnection());
        $this->em->clear();

        parent::tearDown();
    }

    /**
     * Jeton JWT valide pour cet utilisateur.
     *
     * On le forge par le gestionnaire du bundle plutôt qu'en passant par '/api/login_check' : le
     * parcours de connexion est testé pour lui-même dans 'AuthentificationTest', et le refaire
     * avant chaque test d'autorisation coûterait un hachage de mot de passe par requête sans rien
     * vérifier de plus.
     */
    protected function jetonPour(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    /**
     * Requête authentifiée (ou anonyme si $user est null).
     *
     * @param array<string, mixed>|null $corps charge utile JSON
     */
    protected function requete(string $methode, string $uri, ?User $user = null, ?array $corps = null): void
    {
        $entetes = ['HTTP_ACCEPT' => 'application/ld+json'];
        if ($user !== null) {
            $entetes['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->jetonPour($user);
        }
        if ($corps !== null) {
            // ApiPlatform n'accepte QUE 'application/merge-patch+json' sur un PATCH : envoyer du
            // 'ld+json' fait répondre 415 avant même d'atteindre l'opération, et le test croirait
            // à un refus métier.
            $entetes['CONTENT_TYPE'] = $methode === 'PATCH'
                ? 'application/merge-patch+json'
                : 'application/ld+json';
        }

        $this->client->request($methode, $uri, server: $entetes, content: $corps === null ? null : json_encode($corps));
    }

    /**
     * Relit une entité depuis la base APRÈS une requête HTTP.
     *
     * Symfony réinitialise les services entre deux requêtes du client de test, ce qui VIDE
     * l'EntityManager : les objets construits avant la requête se retrouvent DÉTACHÉS. Les
     * réutiliser tels quels donne « A new entity was found through the relationship… » — un message
     * qui ne dit rien du vrai problème. On relit donc par identifiant, ce qui rend au passage
     * l'assertion plus honnête : on vérifie ce qui est EN BASE, pas ce qu'on a en mémoire.
     *
     * @template T of object
     * @param class-string<T> $classe
     * @return T
     */
    protected function relire(string $classe, int $id): object
    {
        $entite = $this->em->find($classe, $id);
        self::assertNotNull($entite, sprintf('%s #%d introuvable en base.', $classe, $id));

        /*
            'find()' peut rendre l'objet de l'IDENTITY MAP sans toucher la base — et sur une requête
            REFUSÉE, cet objet est déjà MUTÉ : API Platform dénormalise le corps sur l'entité gérée
            AVANT que le processor ne lève. Une assertion « le billet n'a pas bougé » passait donc au
            vert par accident. On force la relecture pour tenir la promesse ci-dessus.
        */
        $this->em->refresh($entite);

        return $entite;
    }

    /** @return array<string, mixed> */
    protected function reponseJson(): array
    {
        $contenu = (string) $this->client->getResponse()->getContent();

        return json_decode($contenu, true) ?? [];
    }

    /**
     * Libellés des membres d'une collection Hydra — de quoi affirmer « il voit ceci, pas cela »
     * sans dépendre des identifiants, qui varient d'un test à l'autre.
     *
     * @return list<string>
     */
    protected function libellesRetournes(string $champ = 'libelle'): array
    {
        $membres = $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];

        return array_values(array_map(
            static fn (array $item): string => (string) ($item[$champ] ?? ''),
            $membres
        ));
    }

    protected function assertStatut(int $attendu): void
    {
        $reelle = $this->client->getResponse()->getStatusCode();

        self::assertSame($attendu, $reelle, sprintf(
            "Statut HTTP inattendu.\nRéponse : %s",
            mb_substr((string) $this->client->getResponse()->getContent(), 0, 500)
        ));
    }
}
