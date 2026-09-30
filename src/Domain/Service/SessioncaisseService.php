<?php

namespace App\Domain\Service;

use App\Domain\Enum\SessioncaisseStatut;
use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Repository\SessioncaisseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * SOURCE UNIQUE de la caisse courante d'un agent : « à quelle session rattacher cet encaissement ? »
 *
 * Appelée par les processors de vente (billet, bagage, courrier), et par eux seuls. Le rattachement
 * est une COLONNE posée à l'écriture, jamais un calcul rejoué après coup : un rattachement dérivé
 * (agent + intervalle de temps) rendrait 0 ou 2 caisses selon les bornes, casserait sur une vente
 * antidatée — cas qui existe déjà en interne, 'HorodatageTrait' réécrit 'createdAt' en DQL — et ne
 * survivrait pas à une relecture des mois plus tard. « Un billet appartient toujours à exactement
 * une caisse » doit être une colonne, pas une déduction.
 */
class SessioncaisseService
{
    /**
     * Identifiants des sessions déjà résolues dans CETTE requête, par agent.
     *
     * Une vente écrit souvent un billet PUIS un bagage : sans cela, chaque écriture reprendrait le
     * verrou pessimiste. On mémoïse l'IDENTIFIANT et non l'objet, et on revalide le statut à chaque
     * appel — le noyau de test ne redémarre pas entre deux requêtes ('disableReboot'), ce service y
     * survit donc à une clôture faite entre-temps et resservirait une caisse fermée.
     *
     * @var array<int, int>
     */
    private array $resolues = [];

    public function __construct(
        private EntityManagerInterface $em,
        private SessioncaisseRepository $sessioncaisseRepository,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    /**
     * La caisse où ranger ce que cet agent encaisse — ouverte à la volée s'il n'en a pas.
     *
     * !! REND NULL POUR UN ACTEUR SANS GARE (administrateur d'entreprise, utilisateur central) :
     * son écriture reste HORS CAISSE. C'est ce 'null' qui remplace tous les filtres qu'il aurait
     * fallu semer dans le module Recette — la vente du commercial à bord, le billet émis depuis un
     * bon et le billet de report (aucun argent ne bouge) n'ont simplement pas de session.
     *
     * !! À APPELER AVANT D'ENTRER DANS LA TRANSACTION DE VENTE. 'TicketProcessor' verrouille le
     * VOYAGE ; cette méthode verrouille l'UTILISATEUR. Résolue après, l'ordre des verrous
     * s'inverserait entre deux agents vendant sur le même départ, et c'est exactement la recette
     * d'un interblocage.
     */
    public function courante(User $user): ?Sessioncaisse
    {
        if ($user->getGare() === null) {
            return null;
        }

        $agentId = $user->getId();
        $identreprise = $user->getEntreprise()->getId();

        if (isset($this->resolues[$agentId])) {
            $session = $this->em->find(Sessioncaisse::class, $this->resolues[$agentId]);

            if ($session !== null && $session->getStatut() === SessioncaisseStatut::OUVERTE->value) {
                return $session;
            }

            unset($this->resolues[$agentId]);
        }

        /*
            VERROU SUR LA LIGNE 'user', et non sur la table des sessions : deux ventes simultanées du
            même agent doivent s'attendre, deux agents différents jamais. L'index unique
            ('identreprise', 'agentsessionouverte') est la seconde barrière — le verrou évite d'en
            arriver à une violation de contrainte, qui refermerait l'EntityManager et ferait échouer
            la vente au lieu de la sérialiser.
        */
        $session = $this->em->wrapInTransaction(function () use ($user, $agentId, $identreprise): Sessioncaisse {
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);

            $existante = $this->sessioncaisseRepository->ouvertePourAgent($agentId, $identreprise);

            if ($existante !== null) {
                return $existante;
            }

            return $this->ouvrirAutomatiquement($user, $agentId, $identreprise);
        });

        $this->resolues[$agentId] = $session->getId();

        return $session;
    }

    /**
     * Ouvre la caisse À LA MAIN, avec le fonds avancé par le chef de gare à la prise de poste.
     *
     * C'est le SEUL apport de l'ouverture explicite : sans elle, toute caisse naîtrait d'une vente
     * avec un fonds à zéro, et la monnaie remise au guichet le matin ressortirait le soir comme un
     * excédent inexpliqué. Elle REFUSE, elle, quand une caisse est déjà ouverte — et c'est la
     * seule garde bloquante du module : le geste est volontaire, un agent qui le répète se trompe,
     * alors qu'une vente, elle, ne doit jamais s'arrêter.
     */
    public function ouvrirManuellement(User $user, int $fonds): Sessioncaisse
    {
        if ($user->getGare() === null) {
            throw new BadRequestHttpException(
                "Vous n'êtes rattaché à aucune gare : une caisse appartient à un guichet."
            );
        }

        $agentId = $user->getId();
        $identreprise = $user->getEntreprise()->getId();

        /*
            !! ON NE LÈVE PAS DEPUIS L'INTÉRIEUR DE LA TRANSACTION. 'wrapInTransaction' attrape tout
            'Throwable' et FERME l'EntityManager avant de relancer : un refus métier — qui n'est
            pourtant pas une panne — laisserait derrière lui un gestionnaire inutilisable pour le
            reste de la requête. Le verrou rend donc 'null' pour dire « déjà ouverte », et le refus
            est levé une fois dehors. Mesuré : sans cela, l'écriture suivante meurt sur un
            « The EntityManager is closed » qui ne désigne pas la cause.
        */
        $session = $this->em->wrapInTransaction(function () use ($user, $agentId, $identreprise, $fonds): ?Sessioncaisse {
            $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);

            if ($this->sessioncaisseRepository->ouvertePourAgent($agentId, $identreprise) !== null) {
                return null;
            }

            return $this->creer($user, $agentId, $identreprise, $fonds, automatique: false);
        });

        if ($session === null) {
            throw new BadRequestHttpException(
                'Votre caisse est déjà ouverte : clôturez-la avant d\'en ouvrir une nouvelle.'
            );
        }

        $this->resolues[$agentId] = $session->getId();

        return $session;
    }

    /**
     * Ouvre la caisse que l'agent n'a pas ouverte, fonds à ZÉRO.
     *
     * AUCUNE GARDE BLOQUANTE : le guichet ne s'arrête jamais sur une procédure oubliée, et aucune
     * vente ne reste orpheline. Refuser la vente reviendrait à perdre l'encaissement pour un geste
     * administratif — exactement ce qu'un module de contrôle ne doit pas coûter.
     */
    private function ouvrirAutomatiquement(User $user, int $agentId, int $identreprise): Sessioncaisse
    {
        $session = $this->creer($user, $agentId, $identreprise, fonds: 0, automatique: true);

        $this->activiteLogger->log(
            ActiviteLogger::CAISSE_OUVERTE_AUTO,
            sprintf(
                'Caisse ouverte automatiquement pour %s à la première vente (aucun fonds de caisse)',
                trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))
            ),
            'Sessioncaisse',
            $session->getId()
        );

        return $session;
    }

    /**
     * L'écriture, commune aux deux ouvertures — elles ne diffèrent que par le fonds et le drapeau.
     *
     * La GARE est recopiée depuis l'agent et FIGÉE là : muter un agent d'une gare à l'autre
     * déplacerait sinon ses caisses passées, y compris celles qu'il a déjà signées.
     */
    private function creer(User $user, int $agentId, int $identreprise, int $fonds, bool $automatique): Sessioncaisse
    {
        $session = (new Sessioncaisse())
            ->setAgent($user)
            ->setGare($user->getGare())
            ->setDatedebut(new \DateTimeImmutable())
            ->setFondsouverture($fonds)
            ->setOuvertureautomatique($automatique)
            ->setStatut(SessioncaisseStatut::OUVERTE->value)
            ->setAgentsessionouverte($agentId);

        $session
            ->setIdentreprise($identreprise)
            ->setCreatedBy($agentId);

        /*
            FLUSH ICI, et pas plus tard : l'audit de l'ouverture automatique cible la session par son
            IDENTIFIANT, qui n'existe qu'une fois l'insertion faite. Une trace sans la ligne qu'elle
            décrit vaudrait moins que pas de trace du tout.
        */
        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }
}
