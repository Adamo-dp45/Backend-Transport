<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\FideliteService;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Fidelite\FideliteStatistiqueOutput;
use App\Entity\Output\Fidelite\TopMembreDto;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistiques du programme de fidélité, calculées (comme l'état) à partir de l'historique des billets.
 */
class FideliteStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private FideliteService $fideliteService,
        private ClientRepository $clientRepository,
        private TicketRepository $ticketRepository,
        private UserRepository $userRepository
    )
    {
    }

    /** Seuils de détection des cartes « captées » : part d'un seul vendeur ≥ 80 % et au moins un seuil de tampons. */
    private const CONCENTRATION_MIN = 80;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($this->requestStack->getCurrentRequest());

        $programme = $this->fideliteService->getProgramme($identreprise);
        $seuil = max(1, $programme->getSeuil());

        $totalClients = $this->clientRepository->countActifs($identreprise);
        $totalMembres = $this->clientRepository->countMembres($identreprise);
        $adhesions    = $this->clientRepository->countAdhesions($debut, $fin, $identreprise);
        $recompenses  = $this->ticketRepository->statsRecompensesPeriode($debut, $fin, $identreprise);

        // Agrégats par membre (1 requête chacun) → on calcule tampons / récompenses en mémoire
        $voyages = [];
        foreach ($this->ticketRepository->voyagesParMembre($identreprise) as $r) {
            $voyages[(int) $r['clientid']] = (int) $r['nb'];
        }
        $utilisees = [];
        foreach ($this->ticketRepository->recompensesParMembre($identreprise) as $r) {
            $utilisees[(int) $r['clientid']] = (int) $r['nb'];
        }

        $recompensesDisponibles = 0;
        $top = [];
        foreach ($this->clientRepository->findMembres($identreprise) as $m) {
            $id = (int) $m['id'];
            $v = $voyages[$id] ?? 0;
            $u = $utilisees[$id] ?? 0;
            $stamps = max(0, $v - $u * $seuil);
            $dispo = $programme->isActif() && intdiv($stamps, $seuil) >= 1;
            if ($dispo) {
                $recompensesDisponibles++;
            }
            $top[] = new TopMembreDto(
                id: $id,
                nom: $m['nom'],
                contact: $m['contact'],
                voyages: $v,
                recompensesUtilisees: $u,
                progression: $stamps % $seuil,
                recompenseDisponible: $dispo
            );
        }
        // Top membres par voyages cumulés
        usort($top, fn(TopMembreDto $a, TopMembreDto $b) => $b->voyages <=> $a->voyages);
        $top = array_slice($top, 0, 10);

        // ── Récompenses PAR AGENT (qui applique/encaisse les récompenses) ──
        $rawRecAg = $this->ticketRepository->recompensesParAgent($debut, $fin, $identreprise);
        $recAgIds = array_filter(array_map(fn ($r) => (int) $r['agentid'], $rawRecAg));
        $nomsRecAg = empty($recAgIds) ? [] : $this->userRepository->findInfosByIds($recAgIds);
        $recompensesParAgent = array_map(fn ($r) => [
            'nom' => trim((($nomsRecAg[$r['agentid']]['prenom'] ?? '') . ' ' . ($nomsRecAg[$r['agentid']]['nom'] ?? ''))) ?: '—',
            'nb' => (int) $r['nb'],
            'valeur' => (int) $r['valeur'],
        ], $rawRecAg);

        // ── Cartes « captées » : un seul vendeur domine les tampons d'un membre ──
        // Agrège tampons par (membre, vendeur) ; on garde le vendeur dominant et sa part.
        $parMembre = []; // clientid => ['nom','contact','total','vendeurs'=>[agentid=>nb]]
        foreach ($this->ticketRepository->accumulateursParMembreVendeur($identreprise) as $r) {
            $cid = (int) $r['clientid'];
            $parMembre[$cid] ??= ['nom' => $r['nom'], 'contact' => $r['contact'], 'total' => 0, 'vendeurs' => []];
            $parMembre[$cid]['total'] += (int) $r['nb'];
            $parMembre[$cid]['vendeurs'][(int) $r['agentid']] = (int) $r['nb'];
        }
        // Récompenses par (membre, vendeur) → repérer « le même agent nourrit ET encaisse »
        $recVendeurs = []; // clientid => [agentid, ...]
        foreach ($this->ticketRepository->recompensesParMembreVendeur($identreprise) as $r) {
            $recVendeurs[(int) $r['clientid']][] = (int) $r['agentid'];
        }

        $cartesCaptees = [];
        foreach ($parMembre as $cid => $m) {
            if ($m['total'] < $seuil) {
                continue; // pas encore un carnet complet : peu significatif
            }
            arsort($m['vendeurs']);
            $agentDominant = (int) array_key_first($m['vendeurs']);
            $nbDominant = $m['vendeurs'][$agentDominant];
            $part = (int) round($nbDominant / $m['total'] * 100);
            if ($part < self::CONCENTRATION_MIN) {
                continue; // tampons répartis sur plusieurs vendeurs : normal
            }
            $cartesCaptees[] = [
                'nom' => $m['nom'] ?? '—',
                'contact' => $m['contact'],
                'tampons' => $m['total'],
                'vendeur' => '',            // nom résolu ci-dessous
                'part' => $part,
                'memeAgent' => in_array($agentDominant, $recVendeurs[$cid] ?? [], true),
                '_agentid' => $agentDominant,
            ];
        }
        // Résolution des noms de vendeurs + tri (part décroissante) + top 10
        $vendIds = array_filter(array_map(fn ($c) => $c['_agentid'], $cartesCaptees));
        $nomsVend = empty($vendIds) ? [] : $this->userRepository->findInfosByIds($vendIds);
        $cartesCaptees = array_map(function ($c) use ($nomsVend) {
            $c['vendeur'] = trim((($nomsVend[$c['_agentid']]['prenom'] ?? '') . ' ' . ($nomsVend[$c['_agentid']]['nom'] ?? ''))) ?: '—';
            unset($c['_agentid']);
            return $c;
        }, $cartesCaptees);
        usort($cartesCaptees, fn ($a, $b) => [$b['memeAgent'], $b['part'], $b['tampons']] <=> [$a['memeAgent'], $a['part'], $a['tampons']]);
        $cartesCaptees = array_slice($cartesCaptees, 0, 10);

        return new FideliteStatistiqueOutput(
            totalClients: $totalClients,
            totalMembres: $totalMembres,
            tauxAdhesion: $totalClients > 0 ? round($totalMembres / $totalClients * 100, 1) : 0.0,
            adhesionsPeriode: $adhesions,
            recompensesUtilisees: $recompenses['nb'],
            valeurRecompenses: $recompenses['valeur'],
            recompensesDisponibles: $recompensesDisponibles,
            seuil: $programme->getSeuil(),
            recompensePourcentage: $programme->getRecompensePourcentage(),
            programmeActif: $programme->isActif(),
            topMembres: $top,
            recompensesParAgent: $recompensesParAgent,
            cartesCaptees: $cartesCaptees
        );
    }
}
