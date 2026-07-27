<?php

namespace App\Domain\Service;

use App\Domain\Enum\AlerteStatut;
use App\Domain\Enum\AlerteType;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Alerte;
use App\Entity\Entreprise;
use App\Repository\AlerteRepository;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\DepannageRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\PieceRepository;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * BALAYEUR des alertes : réconcilie, entreprise par entreprise, l'état COURANT de l'application
 * avec les alertes persistées (à planifier en cron via app:alertes:generer).
 *
 * Principe idempotent (même esprit que ReservationExpirationService::traiter) :
 *   1. chaque évaluateur calcule les situations actuellement vraies → une clé stable par situation ;
 *   2. clé nouvelle            → on CRÉE l'alerte (ACTIVE) ;
 *      clé déjà présente       → on RAFRAÎCHIT (message/données), le statut est préservé ;
 *      clé disparue du calcul  → on AUTO-RÉSOUT (RESOLUE) → elle sort de la cloche.
 * Relancer le balayeur n'a donc aucun effet de bord (pas de doublon, pas d'accumulation).
 *
 * Les seuils sont des constantes (défauts raisonnables) ; leur externalisation par entreprise
 * (façon ParametreReservation) est une évolution ultérieure.
 */
class AlerteGenerationService
{
    // Exploitation
    private const FENETRE_DEPART_IMMINENT_MIN = 120; // « à l'approche » : personnel manquant

    // Réservation
    private const FENETRE_BON_EXPIRE_MIN = 60; // bon à retirer dans l'heure

    // Stock & flotte
    private const DEPANNAGE_ANCIENNETE_JOURS = 3;

    // Anti-fraude & incidents
    private const INCIDENT_ANCIENNETE_JOURS = 2;   // courrier/bagage non livré
    private const ANTIFRAUDE_PERIODE_JOURS = 30;   // fenêtre glissante d'analyse
    private const ANNULATION_TAUX_SEUIL = 0.25;    // 25 % d'annulations
    private const ANNULATION_VOLUME_MIN = 10;      // en deçà, l'échantillon n'est pas significatif
    private const REMISE_MONTANT_SEUIL = 100000;   // FCFA cumulés de remises sur la période

    public function __construct(
        private EntityManagerInterface $em,
        private EntrepriseRepository $entrepriseRepository,
        private AlerteRepository $alerteRepository,
        private VoyageRepository $voyageRepository,
        private PieceRepository $pieceRepository,
        private ReservationRepository $reservationRepository,
        private DepannageRepository $depannageRepository,
        private CourrierRepository $courrierRepository,
        private BagageRepository $bagageRepository,
        private TicketRepository $ticketRepository,
        private UserRepository $userRepository,
        private CapaciteService $capaciteService,
    ) {
    }

    /**
     * Balaie toutes les entreprises actives.
     *
     * @return array{creees:int, resolues:int, entreprises:int}
     */
    public function generer(): array
    {
        $creees = 0;
        $resolues = 0;
        $nb = 0;

        foreach ($this->entreprisesActives() as $entreprise) {
            $r = $this->genererPourEntreprise($entreprise);
            $creees += $r['creees'];
            $resolues += $r['resolues'];
            $nb++;
        }

        return ['creees' => $creees, 'resolues' => $resolues, 'entreprises' => $nb];
    }

    /**
     * Réconcilie les alertes d'UNE entreprise.
     *
     * @return array{creees:int, resolues:int}
     */
    public function genererPourEntreprise(Entreprise $entreprise): array
    {
        $ide = (int) $entreprise->getId();
        $now = new \DateTimeImmutable();

        // 1) Situations courantes, indexées par clé de déduplication.
        $desires = [];
        foreach ([
            $this->evaluerExploitation($ide, $now),
            $this->evaluerReservation($ide, $now),
            $this->evaluerStockFlotte($ide, $now),
            $this->evaluerAntifraude($ide, $now),
        ] as $lot) {
            foreach ($lot as $spec) {
                $desires[$spec['cle']] = $spec;
            }
        }

        // 2) Réconciliation avec les alertes non résolues existantes.
        $existants = $this->alerteRepository->indexParCle($ide);
        $creees = 0;
        $resolues = 0;

        foreach ($desires as $cle => $spec) {
            if (isset($existants[$cle])) {
                $this->rafraichir($existants[$cle], $spec);
            } else {
                $this->em->persist($this->creer($ide, $spec));
                $creees++;
            }
        }

        foreach ($existants as $cle => $alerte) {
            if (!isset($desires[$cle])) {
                $alerte->setStatut(AlerteStatut::RESOLUE->value)->setResolueLe($now);
                $resolues++;
            }
        }

        $this->em->flush();

        return ['creees' => $creees, 'resolues' => $resolues];
    }

    // ─────────────────────────── Évaluateurs ─────────────────────────── //

    /**
     * Exploitation : départ en retard, voyage sans personnel à l'approche, passagers évincés.
     * Piloté par les voyages OUVERTS (non clôturés, à venir) de l'entreprise.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evaluerExploitation(int $ide, \DateTimeImmutable $now): array
    {
        $specs = [];
        $limiteImminent = $now->modify('+' . self::FENETRE_DEPART_IMMINENT_MIN . ' minutes');

        foreach ($this->voyageRepository->findOuvertsPourEntreprise($ide) as $voyage) {
            $vid = (int) $voyage->getId();
            $idgare = $voyage->getGareprovenance()?->getId();
            $code = $voyage->getCodevoyage() ?? ('#' . $vid);
            $trajet = trim(($voyage->getProvenance() ?? '') . ' → ' . ($voyage->getDestination() ?? ''));
            $prevue = $voyage->getDatedepartprevue();
            $nonParti = $voyage->getDatedepartreelle() === null;

            // Départ en retard : heure prévue dépassée, car pas encore parti.
            if ($nonParti && $prevue !== null && $prevue < $now) {
                $retard = (int) round(($now->getTimestamp() - $prevue->getTimestamp()) / 60);
                $specs[] = $this->spec(
                    AlerteType::VOYAGE_DEPART_EN_RETARD,
                    'VOYAGE_DEPART_EN_RETARD:' . $vid,
                    $idgare,
                    'Départ en retard',
                    sprintf('Le voyage %s (%s) aurait dû partir depuis %d min et n\'est pas encore parti.', $code, $trajet, $retard),
                    'VOYAGE',
                    $vid,
                    ['retardMinutes' => $retard]
                );
            }

            // Sans personnel à l'approche : départ proche, aucun personnel affecté.
            if ($nonParti && $prevue !== null && $prevue <= $limiteImminent && $voyage->getDetailpersonnels()->isEmpty()) {
                $specs[] = $this->spec(
                    AlerteType::VOYAGE_SANS_PERSONNEL,
                    'VOYAGE_SANS_PERSONNEL:' . $vid,
                    $idgare,
                    'Voyage sans personnel',
                    sprintf('Le voyage %s (%s) part bientôt et aucun personnel n\'est affecté.', $code, $trajet),
                    'VOYAGE',
                    $vid
                );
            }

            // Passagers évincés (surbooking amont) à reloger.
            $nbEvinces = count($this->capaciteService->billetsEvinces($voyage, $ide));
            if ($nbEvinces > 0) {
                $specs[] = $this->spec(
                    AlerteType::PASSAGERS_EVINCES,
                    'PASSAGERS_EVINCES:' . $vid,
                    $idgare,
                    'Passagers évincés',
                    sprintf('%d passager(s) évincé(s) sur le voyage %s (%s) — à reloger.', $nbEvinces, $code, $trajet),
                    'VOYAGE',
                    $vid,
                    ['nombre' => $nbEvinces]
                );
            }
        }

        return $specs;
    }

    /**
     * Réservation & billetterie : bons près d'expirer, no-show à régulariser.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evaluerReservation(int $ide, \DateTimeImmutable $now): array
    {
        $specs = [];
        $limite = $now->modify('+' . self::FENETRE_BON_EXPIRE_MIN . ' minutes');

        foreach ($this->reservationRepository->findBonsExpirantBientot($ide, $now, $limite) as $r) {
            $rid = (int) $r->getId();
            $exp = $r->getDateexpiration();
            $reste = $exp ? max(0, (int) round(($exp->getTimestamp() - $now->getTimestamp()) / 60)) : null;
            $specs[] = $this->spec(
                AlerteType::BON_EXPIRE_BIENTOT,
                'BON_EXPIRE_BIENTOT:' . $rid,
                $r->getGare()?->getId(),
                'Bon bientôt expiré',
                sprintf(
                    'Réservation %s à retirer%s : le client doit se présenter au guichet.',
                    $r->getCode() ?? ('#' . $rid),
                    $reste !== null ? sprintf(' dans %d min', $reste) : ''
                ),
                'RESERVATION',
                $rid,
                ['resteMinutes' => $reste]
            );
        }

        foreach ($this->reservationRepository->findARegulariserPourEntreprise($ide) as $r) {
            $rid = (int) $r->getId();
            $specs[] = $this->spec(
                AlerteType::NO_SHOW_A_REGULARISER,
                'NO_SHOW_A_REGULARISER:' . $rid,
                $r->getGare()?->getId(),
                'No-show à régulariser',
                sprintf('Réservation %s payée non honorée : à régulariser (report + pénalité).', $r->getCode() ?? ('#' . $rid)),
                'RESERVATION',
                $rid
            );
        }

        return $specs;
    }

    /**
     * Stock & flotte : rupture, stock faible, dépannage ouvert prolongé.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evaluerStockFlotte(int $ide, \DateTimeImmutable $now): array
    {
        $specs = [];

        foreach ($this->pieceRepository->stockParPiece($ide) as $p) {
            $pid = (int) $p['id'];
            $stock = (int) $p['stockinitial'];
            $seuil = (int) $p['seuilstock'];

            if ($stock <= 0) {
                $specs[] = $this->spec(
                    AlerteType::STOCK_RUPTURE,
                    'STOCK_RUPTURE:' . $pid,
                    null,
                    'Rupture de stock',
                    sprintf('La pièce « %s » est en rupture (stock 0).', $p['libelle']),
                    'PIECE',
                    $pid,
                    ['stock' => $stock, 'seuil' => $seuil]
                );
            } elseif ($stock <= $seuil) {
                $specs[] = $this->spec(
                    AlerteType::STOCK_FAIBLE,
                    'STOCK_FAIBLE:' . $pid,
                    null,
                    'Stock faible',
                    sprintf('La pièce « %s » est sous le seuil (%d ≤ %d).', $p['libelle'], $stock, $seuil),
                    'PIECE',
                    $pid,
                    ['stock' => $stock, 'seuil' => $seuil]
                );
            }
        }

        $limiteDep = $now->modify('-' . self::DEPANNAGE_ANCIENNETE_JOURS . ' days');
        foreach ($this->depannageRepository->findOuvertsAnterieursA($ide, $limiteDep) as $d) {
            $did = (int) $d->getId();
            $specs[] = $this->spec(
                AlerteType::DEPANNAGE_OUVERT_PROLONGE,
                'DEPANNAGE_OUVERT_PROLONGE:' . $did,
                null,
                'Dépannage prolongé',
                sprintf('Dépannage sur le car %s ouvert depuis plus de %d jours.', $d->getCar()?->getMatricule() ?? '—', self::DEPANNAGE_ANCIENNETE_JOURS),
                'DEPANNAGE',
                $did
            );
        }

        return $specs;
    }

    /**
     * Anti-fraude & incidents : agent au taux d'annulation / montant de remises anormal,
     * courriers/bagages non livrés.
     *
     * @return array<int, array<string, mixed>>
     */
    private function evaluerAntifraude(int $ide, \DateTimeImmutable $now): array
    {
        $specs = [];
        $debut = $now->modify('-' . self::ANTIFRAUDE_PERIODE_JOURS . ' days');

        $annulations = $this->ticketRepository->tauxAnnulationParAgent($debut, $now, $ide);
        $remises = $this->ticketRepository->remisesParAgent($debut, $now, $ide);
        $noms = $this->nomsAgents(array_values(array_unique(array_merge(
            array_map(static fn ($r) => (int) $r['agentid'], $annulations),
            array_map(static fn ($r) => (int) $r['agentid'], $remises),
        ))));

        foreach ($annulations as $r) {
            $nbemis = (int) $r['nbemis'];
            $nbannules = (int) $r['nbannules'];
            if ($nbemis < self::ANNULATION_VOLUME_MIN || $nbannules === 0) {
                continue;
            }
            $taux = $nbannules / $nbemis;
            if ($taux < self::ANNULATION_TAUX_SEUIL) {
                continue;
            }
            $aid = (int) $r['agentid'];
            $specs[] = $this->spec(
                AlerteType::AGENT_ANNULATION_ELEVEE,
                'AGENT_ANNULATION_ELEVEE:' . $aid,
                null,
                'Taux d\'annulation élevé',
                sprintf('%s : %d%% d\'annulations (%d/%d) sur %d jours.', $noms[$aid] ?? ('Agent #' . $aid), (int) round($taux * 100), $nbannules, $nbemis, self::ANTIFRAUDE_PERIODE_JOURS),
                'USER',
                $aid,
                ['taux' => round($taux, 2), 'nbannules' => $nbannules, 'nbemis' => $nbemis]
            );
        }

        foreach ($remises as $r) {
            $total = (int) $r['total'];
            if ($total < self::REMISE_MONTANT_SEUIL) {
                continue;
            }
            $aid = (int) $r['agentid'];
            $specs[] = $this->spec(
                AlerteType::AGENT_REMISE_ELEVEE,
                'AGENT_REMISE_ELEVEE:' . $aid,
                null,
                'Remises élevées',
                sprintf('%s : %s FCFA de remises sur %d billets (%d jours).', $noms[$aid] ?? ('Agent #' . $aid), number_format($total, 0, ',', ' '), (int) $r['nb'], self::ANTIFRAUDE_PERIODE_JOURS),
                'USER',
                $aid,
                ['montant' => $total, 'nb' => (int) $r['nb']]
            );
        }

        $limiteInc = $now->modify('-' . self::INCIDENT_ANCIENNETE_JOURS . ' days');

        foreach ($this->courrierRepository->findReceptionnesAnterieursA($ide, $limiteInc) as $c) {
            $idgare = $c->getGarearrivee()?->getId();
            if ($idgare === null) {
                continue;
            }
            $cid = (int) $c->getId();
            $specs[] = $this->spec(
                AlerteType::COURRIER_NON_LIVRE,
                'COURRIER_NON_LIVRE:' . $cid,
                $idgare,
                'Courrier non livré',
                sprintf('Un courrier est réceptionné depuis plus de %d jours sans être livré.', self::INCIDENT_ANCIENNETE_JOURS),
                'COURRIER',
                $cid
            );
        }

        foreach ($this->bagageRepository->findEmbarquesNonLivresAnterieursA($ide, $limiteInc) as $b) {
            $idgare = $b->getGaredescente()?->getId();
            if ($idgare === null) {
                continue;
            }
            $bid = (int) $b->getId();
            $specs[] = $this->spec(
                AlerteType::BAGAGE_NON_LIVRE,
                'BAGAGE_NON_LIVRE:' . $bid,
                $idgare,
                'Bagage non livré',
                sprintf('Un bagage est arrivé depuis plus de %d jours sans être remis.', self::INCIDENT_ANCIENNETE_JOURS),
                'BAGAGE',
                $bid
            );
        }

        return $specs;
    }

    // ─────────────────────────── Aides ─────────────────────────── //

    /**
     * Fabrique une « spec » d'alerte (données brutes) — la sévérité/portée/famille sont dérivées
     * du type au moment de la création.
     *
     * @param array<string, mixed>|null $donnees
     * @return array<string, mixed>
     */
    private function spec(
        AlerteType $type,
        string $cle,
        ?int $idgare,
        string $titre,
        string $message,
        ?string $sourcetype,
        ?int $sourceid,
        ?array $donnees = null
    ): array {
        return [
            'type' => $type,
            'cle' => $cle,
            'idgare' => $idgare,
            'titre' => $titre,
            'message' => $message,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'donnees' => $donnees,
        ];
    }

    /** @param array<string, mixed> $spec */
    private function creer(int $ide, array $spec): Alerte
    {
        /** @var AlerteType $type */
        $type = $spec['type'];

        return (new Alerte())
            ->setIdentreprise($ide)
            ->setType($type->value)
            ->setSeverite($type->severite()->value)
            ->setPortee($type->portee()->value)
            ->setFamille($type->famille())
            ->setIdgare($spec['idgare'])
            ->setCle($spec['cle'])
            ->setTitre($spec['titre'])
            ->setMessage($spec['message'])
            ->setSourcetype($spec['sourcetype'])
            ->setSourceid($spec['sourceid'])
            ->setDonnees($spec['donnees'])
            ->setStatut(AlerteStatut::ACTIVE->value);
    }

    /**
     * Rafraîchit une alerte déjà existante (le libellé/les données peuvent avoir évolué : nombre
     * d'évincés, minutes de retard…). Le STATUT est préservé — une alerte déjà LUE le reste.
     *
     * @param array<string, mixed> $spec
     */
    private function rafraichir(Alerte $alerte, array $spec): void
    {
        $alerte
            ->setIdgare($spec['idgare'])
            ->setTitre($spec['titre'])
            ->setMessage($spec['message'])
            ->setSourcetype($spec['sourcetype'])
            ->setSourceid($spec['sourceid'])
            ->setDonnees($spec['donnees']);
    }

    /**
     * Noms lisibles des agents (id => « Prénom Nom »), résolus en une passe pour les messages.
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    private function nomsAgents(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $noms = [];
        foreach ($this->userRepository->findBy(['id' => $ids]) as $user) {
            $noms[(int) $user->getId()] = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) ?: ('Agent #' . $user->getId());
        }

        return $noms;
    }

    /** @return Entreprise[] */
    private function entreprisesActives(): array
    {
        return $this->entrepriseRepository->findBy([
            'statut' => ReferenceStatus::ACTIF->value,
            'deletedAt' => null,
        ]);
    }
}
