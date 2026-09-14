<?php

namespace App\Domain\Service;

use App\Entity\Bagage;
use App\Entity\Dto\BagageInput;
use App\Entity\Gare;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\BagageRepository;
use App\Repository\GareRepository;
use App\Repository\SiegeRepository;
use App\Repository\TicketRepository;
use App\State\BagageProcessor;
use App\State\TicketProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Rejeu des opérations encaissées HORS LIGNE par le vendeur à bord.
 *
 * Le commercial vend depuis le car, souvent sans réseau : il encaisse en espèces, imprime le reçu et
 * met l'opération en file sur son téléphone. Ce service rejoue cette file à la reconnexion.
 *
 * TROIS PRINCIPES, qui expliquent presque tout le code ci-dessous :
 *
 *  1. IDEMPOTENCE. Chaque opération porte une référence générée par l'appareil. Une référence déjà
 *     connue rend le billet existant au lieu d'en créer un second — le lot peut donc être rejoué
 *     autant de fois qu'il le faut (coupure en plein envoi, reprise après échec). Même principe que
 *     la clé de déduplication du balayeur d'alertes ({@see App\Entity\Alerte::$cle}).
 *
 *  2. ON N'ANNULE PAS UNE VENTE DÉJÀ FAITE. Le passager est assis, l'argent est dans la sacoche.
 *     Refuser un billet ici ne déferait rien : cela le ferait seulement disparaître du système. Les
 *     conflits d'occupation ne sont donc PAS des refus — ils sont acceptés, et la doctrine d'éviction
 *     (priorité à la gare amont) arbitre ensuite qui monte réellement. Seules les incohérences de
 *     données, celles qu'aucune synchronisation ne peut réparer, sont refusées.
 *
 *  3. LE PRIX VIENT TOUJOURS DU SERVEUR. Le téléphone calcule un montant depuis sa grille pour
 *     encaisser, mais c'est la grille au moment de la synchronisation qui fait foi. Un écart est
 *     consigné pour que la gare régularise — jamais appliqué en silence. C'est ce qui empêche un
 *     appareil modifié de dicter ses propres prix.
 *
 * Chaque opération rend son sort : le lot n'échoue pas en bloc, il rapporte élément par élément.
 */
class SynchronisationHorsLigneService
{
    /** L'opération a été enregistrée maintenant. */
    public const ACCEPTE = 'ACCEPTE';

    /** Référence déjà connue : rien n'a été écrit, on rend ce qui existe déjà. */
    public const DEJA_SYNCHRONISE = 'DEJA_SYNCHRONISE';

    /** Incohérence de données qu'aucun rejeu ne réparera (voyage clôturé, siège inconnu…). */
    public const REFUSE = 'REFUSE';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketProcessor $ticketProcessor,
        private readonly TicketRepository $ticketRepository,
        private readonly GareRepository $gareRepository,
        private readonly SiegeRepository $siegeRepository,
        private readonly ActiviteLogger $activiteLogger,
        private readonly AvanceePositionService $avanceePosition,
        private readonly DepartGareService $departGare,
        private readonly BagageProcessor $bagageProcessor,
        private readonly BagageRepository $bagageRepository,
        private readonly ValidatorInterface $validateur,
        private readonly Security $security
    )
    {
    }

    /**
     * Rejoue un lot, DANS L'ORDRE D'ÉMISSION.
     *
     * L'ordre compte : un bagage suit le billet auquel il se rattache, une avance de position suit
     * les ventes faites depuis la gare précédente. Le téléphone envoie sa file telle qu'elle s'est
     * constituée, on ne la réordonne pas.
     *
     * @param list<array<string, mixed>> $operations
     * @return list<array<string, mixed>> un résultat par opération, dans le même ordre
     */
    public function rejouer(array $operations, Voyage $voyage, User $vendeur): array
    {
        $resultats = [];

        foreach ($operations as $operation) {
            $reference = is_string($operation['reference'] ?? null) ? $operation['reference'] : null;

            if ($reference === null || $reference === '') {
                $resultats[] = $this->refus(null, 'Référence d\'opération manquante');
                continue;
            }

            /*
                LE LOT NE DISPENSE PAS DES PERMISSIONS. Le contrôleur a vérifié que l'appelant est le
                commercial DU VOYAGE — c'est un bornage, pas un droit. Les opérations en ligne passent
                par 'is_granted' sur l'opération API Platform ; celles-ci sortent de ce pipeline, et
                sans ce contrôle un vendeur privé du droit de créer un billet l'obtiendrait en
                passant hors ligne. L'avance de position n'y figure pas : elle n'est gardée, en ligne
                comme ici, que par la qualité de commercial du voyage.
            */
            $requise = match ($operation['type'] ?? null) {
                'VENTE' => ['CREER', 'Ticket'],
                'BAGAGE' => ['CREER', 'Bagage'],
                default => null,
            };
            if ($requise !== null && !$this->security->isGranted($requise[0], $requise[1])) {
                $resultats[] = $this->refus($reference, sprintf(
                    'Droit manquant : %s sur %s',
                    $requise[0],
                    $requise[1]
                ));
                continue;
            }

            try {
                $resultats[] = match ($operation['type'] ?? null) {
                    'VENTE' => $this->rejouerVente($operation, $reference, $voyage, $vendeur),
                    'BAGAGE' => $this->rejouerBagage($operation, $reference, $voyage, $vendeur),
                    'POSITION' => $this->rejouerPosition($operation, $reference, $voyage),
                    'DEPART' => $this->rejouerDepart($operation, $reference, $voyage),
                    default => $this->refus($reference, 'Type d\'opération inconnu : ' . var_export($operation['type'] ?? null, true)),
                };
            } catch (HttpExceptionInterface $e) {
                /*
                    Une garde métier a parlé (tronçon incohérent, tarif absent, remise au-delà du
                    plafond…). On la rapporte SANS interrompre le lot : les opérations suivantes sont
                    peut-être parfaitement valides, et le vendeur doit récupérer tout ce qui peut
                    l'être. Le détail du refus remonte tel quel pour qu'il soit affiché sur le
                    téléphone plutôt que deviné.
                */
                $resultats[] = $this->refus($reference, $e->getMessage());
            }
        }

        return $resultats;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function rejouerVente(array $operation, string $reference, Voyage $voyage, User $vendeur): array
    {
        // 1. Déjà passée ? On rend le billet existant sans rien réécrire.
        $existant = $this->ticketRepository->findOneBy(['referenceOffline' => $reference]);
        if ($existant !== null) {
            return $this->resultat(self::DEJA_SYNCHRONISE, $reference, $existant);
        }

        $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];

        $montee = $this->gare($payload['gare'] ?? null, $voyage);
        $descente = $this->gare($payload['garedescente'] ?? null, $voyage);
        $siege = $this->siege($payload['siege'] ?? null, $voyage);

        if ($montee === null) {
            return $this->refus($reference, 'Gare de montée inconnue ou hors de cette compagnie');
        }
        if ($siege === null) {
            return $this->refus($reference, 'Siège inconnu ou hors de cette compagnie');
        }

        $codeticket = is_string($payload['codeticket'] ?? null) ? trim($payload['codeticket']) : '';
        if ($codeticket === '') {
            return $this->refus($reference, 'Code de billet manquant : il a été imprimé sur le reçu du client, il doit remonter tel quel');
        }

        $ticket = (new Ticket())
            ->setVoyage($voyage)
            ->setSiege($siege)
            ->setGare($montee)
            ->setGaredescente($descente)
            ->setNomclient($this->texte($payload['nomclient'] ?? null))
            ->setContactclient($this->texte($payload['contactclient'] ?? null))
            ->setRemisetype($this->texte($payload['remisetype'] ?? null))
            ->setRemisevaleur(isset($payload['remisevaleur']) ? (int) $payload['remisevaleur'] : null)
            ->setCodeticket($codeticket)
            ->setReferenceOffline($reference)
            ->setMontantEncaisse(isset($payload['montantEncaisse']) ? (int) $payload['montantEncaisse'] : null);

        $ticket = $this->ticketProcessor->emettreHorsLigne($ticket);

        $this->tracer($ticket, $vendeur);

        return $this->resultat(self::ACCEPTE, $reference, $ticket);
    }

    /**
     * Rejeu d'un bagage enregistré sans réseau.
     *
     * LE BILLET EST DÉSIGNÉ PAR SON CODE, pas par son identifiant. C'est ce qui rend l'opération
     * possible : hors ligne, le billet vient souvent d'être vendu par le même téléphone et n'a
     * strictement aucun identifiant serveur — il attend dans la même file, quelques lignes plus haut.
     * Le code, lui, existe dès l'impression et ne change jamais. L'ordre d'émission fait le reste : la
     * vente est rejouée avant, donc le billet est en base quand le bagage le cherche.
     *
     * Le billet doit appartenir à CE voyage. Un bagage se charge dans la soute du car où monte son
     * propriétaire ; le rattacher au départ d'à côté n'aurait aucun sens physique.
     *
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function rejouerBagage(array $operation, string $reference, Voyage $voyage, User $vendeur): array
    {
        $existant = $this->bagageRepository->findOneBy(['referenceOffline' => $reference]);
        if ($existant !== null) {
            return $this->resultatBagage(self::DEJA_SYNCHRONISE, $reference, $existant);
        }

        $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];

        $codeticket = $this->texte($payload['codeticket'] ?? null);
        if ($codeticket === null) {
            return $this->refus($reference, 'Code du billet manquant : sans lui, le bagage ne peut être rattaché à personne');
        }

        $ticket = $this->ticketRepository->findOneBy([
            'codeticket' => $codeticket,
            'identreprise' => $voyage->getIdentreprise(),
            'deletedAt' => null,
        ]);

        if ($ticket === null) {
            /*
                Le billet manque à l'appel. Presque toujours : sa propre vente a été REFUSÉE quelques
                lignes plus haut dans le même lot. Le motif de ce refus-là est le vrai diagnostic ;
                celui-ci n'en est que la conséquence, et on le dit pour que le vendeur ne cherche pas
                deux problèmes là où il n'y en a qu'un.
            */
            return $this->refus($reference, sprintf('Billet %s introuvable — sa vente a-t-elle été refusée ?', $codeticket));
        }

        if ($ticket->getVoyage()?->getId() !== $voyage->getId()) {
            return $this->refus($reference, sprintf('Le billet %s n\'appartient pas à ce voyage', $codeticket));
        }

        $codebagage = $this->texte($payload['codebagage'] ?? null);
        if ($codebagage === null) {
            return $this->refus($reference, 'Code de bagage manquant : il a été imprimé sur l\'étiquette, il doit remonter tel quel');
        }

        $entree = new BagageInput();
        $entree->ticket = $ticket->getId();
        $entree->nature = (string) ($this->texte($payload['nature'] ?? null) ?? '');
        $entree->type = (string) ($this->texte($payload['type'] ?? null) ?? '');
        $entree->poids = isset($payload['poids']) ? (int) $payload['poids'] : 0;
        $entree->montant = isset($payload['montant']) ? (int) $payload['montant'] : null;

        /*
            Le lot court HORS du pipeline API Platform : personne n'a validé ce DTO avant nous. On le
            valide donc explicitement, avec les contraintes déjà portées par le DTO — plutôt que de
            réécrire ici des vérifications qui divergeraient de celles du guichet.
        */
        $fautes = $this->validateur->validate($entree);
        if (count($fautes) > 0) {
            return $this->refus($reference, (string) $fautes->get(0)->getMessage());
        }

        $encaisse = isset($payload['montantEncaisse']) ? (int) $payload['montantEncaisse'] : null;

        try {
            $bagage = $this->bagageProcessor->enregistrerHorsLigne($entree, $ticket, $codebagage, $reference, $encaisse);
        } catch (BadRequestHttpException $e) {
            return $this->refus($reference, $e->getMessage());
        }

        $this->tracerBagage($bagage, $vendeur);

        return $this->resultatBagage(self::ACCEPTE, $reference, $bagage);
    }

    /**
     * Rejeu d'une avance de position déclarée sans réseau.
     *
     * Le commercial a franchi des arrêts pendant la coupure. L'ORDRE du lot compte ici plus que
     * partout ailleurs : les ventes faites depuis Bouaké précèdent l'arrivée à Korhogo, et les
     * rejouer dans le désordre poserait la position trop loin avant d'enregistrer les billets.
     *
     * L'horodatage est celui du TÉLÉPHONE : prendre l'heure de la synchronisation ferait croire à un
     * car immobile pendant des heures puis téléporté, et fausserait le recalage des durées de tronçon.
     *
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function rejouerPosition(array $operation, string $reference, Voyage $voyage): array
    {
        $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];

        $cible = $this->gare($payload['gare'] ?? null, $voyage);
        if ($cible === null) {
            return $this->refus($reference, 'Gare inconnue ou hors de cette compagnie');
        }

        $instant = $this->instant($operation['instant'] ?? null);

        /*
            'false' = le car était déjà à cette gare ou au-delà. Ce n'est pas une erreur : c'est un
            rejeu, ou une gare en aval qui a réceptionné le voyage entre-temps et fait avancer la
            position à notre place. Le résultat est le même, et c'est tout ce qui compte.
        */
        $avance = $this->avanceePosition->avancer($voyage, $cible, $instant);

        return [
            'reference' => $reference,
            'statut' => $avance ? self::ACCEPTE : self::DEJA_SYNCHRONISE,
            'position' => $cible->getLibelle(),
        ];
    }

    /**
     * Rejeu d'un départ de gare déclaré sans réseau.
     *
     * Le pendant de {@see rejouerPosition()} : celle-là horodate l'arrivée, celle-ci le départ.
     * Ensemble elles donnent le TEMPS D'ARRÊT en gare, et c'est tout l'objet du geste — raison pour
     * laquelle l'horodatage du téléphone fait foi ici plus qu'ailleurs. Prendre l'heure de la
     * synchronisation donnerait un arrêt de plusieurs heures là où le car s'est arrêté dix minutes.
     *
     * La gare n'est PAS lue dans le payload : le service repart de la position courante du voyage,
     * exactement comme en ligne. Dans un lot, cette position est celle que les opérations précédentes
     * viennent d'établir — l'ordre d'émission suffit donc à désigner la bonne gare.
     *
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function rejouerDepart(array $operation, string $reference, Voyage $voyage): array
    {
        $gare = $this->departGare->gareDeDepart($voyage);
        $instant = $this->instant($operation['instant'] ?? null);

        /*
            'false' = le départ était déjà horodaté. Ce n'est pas une erreur : c'est un rejeu, ou la
            gare elle-même qui a enregistré le départ entre-temps. Le résultat est le même.
        */
        $reparti = $this->departGare->repartir($voyage, $instant);

        return [
            'reference' => $reference,
            'statut' => $reparti ? self::ACCEPTE : self::DEJA_SYNCHRONISE,
            'position' => $gare?->getLibelle(),
        ];
    }

    /** L'instant déclaré par le téléphone ; null si illisible — le service retombera sur l'heure serveur. */
    private function instant(mixed $valeur): ?\DateTimeImmutable
    {
        if (!is_string($valeur) || trim($valeur) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($valeur);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Journalise la vente hors ligne, et l'écart de prix s'il y en a un.
     *
     * La trace n'est pas rétroactive — elle porte l'instant de la SYNCHRONISATION, pas celui de la
     * vente. C'est une limite assumée : le journal répond à « qui a fait quoi », et ce qui vient
     * d'être fait, c'est l'enregistrement.
     */
    private function tracer(Ticket $ticket, User $vendeur): void
    {
        $this->activiteLogger->log(
            ActiviteLogger::TICKET_HORS_LIGNE,
            sprintf(
                'Billet %s vendu hors ligne à bord par %s %s',
                (string) $ticket->getCodeticket(),
                (string) $vendeur->getPrenom(),
                (string) $vendeur->getNom()
            ),
            'Ticket',
            $ticket->getId()
        );

        $encaisse = $ticket->getMontantEncaisse();
        if ($encaisse !== null && $encaisse !== $ticket->getPrix()) {
            $this->activiteLogger->log(
                ActiviteLogger::TICKET_ECART_TARIF,
                sprintf(
                    'Billet %s : %s FCFA encaissés à bord, %s FCFA à la grille — écart de %s FCFA à régulariser',
                    (string) $ticket->getCodeticket(),
                    number_format($encaisse, 0, ',', ' '),
                    number_format((int) $ticket->getPrix(), 0, ',', ' '),
                    number_format($encaisse - (int) $ticket->getPrix(), 0, ',', ' ')
                ),
                'Ticket',
                $ticket->getId()
            );
        }
    }

    /**
     * Journalise le bagage hors ligne, et l'écart de facturation s'il y en a un.
     *
     * L'écart n'est PAS un forçage : {@see Bagage::$montantforce} dit que l'agent a délibérément
     * facturé hors grille, et il a sa propre trace. Ici, l'agent a appliqué la grille qu'il avait à
     * bord — c'est la grille qui a changé sous lui pendant le trajet.
     */
    private function tracerBagage(Bagage $bagage, User $vendeur): void
    {
        $this->activiteLogger->log(
            ActiviteLogger::BAGAGE_HORS_LIGNE,
            sprintf(
                'Bagage %s enregistré hors ligne à bord par %s %s',
                (string) $bagage->getCodebagage(),
                (string) $vendeur->getPrenom(),
                (string) $vendeur->getNom()
            ),
            'Bagage',
            $bagage->getId()
        );

        $encaisse = $bagage->getMontantEncaisse();
        if ($encaisse !== null && $encaisse !== $bagage->getMontant() && $bagage->isMontantforce() !== true) {
            $this->activiteLogger->log(
                ActiviteLogger::BAGAGE_ECART_TARIF,
                sprintf(
                    'Bagage %s (%s kg) : %s FCFA encaissés à bord, %s FCFA à la grille — écart de %s FCFA à régulariser',
                    (string) $bagage->getCodebagage(),
                    (string) $bagage->getPoids(),
                    number_format($encaisse, 0, ',', ' '),
                    number_format((int) $bagage->getMontant(), 0, ',', ' '),
                    number_format($encaisse - (int) $bagage->getMontant(), 0, ',', ' ')
                ),
                'Bagage',
                $bagage->getId()
            );
        }
    }

    /** Une gare de la compagnie du voyage, ou null. */
    private function gare(mixed $id, Voyage $voyage): ?Gare
    {
        if (!is_numeric($id)) {
            return null;
        }

        return $this->gareRepository->findOneBy([
            'id' => (int) $id,
            'identreprise' => $voyage->getIdentreprise(),
            'deletedAt' => null,
        ]);
    }

    /** Un siège du car affecté au voyage, ou null. */
    private function siege(mixed $id, Voyage $voyage): ?Siege
    {
        if (!is_numeric($id) || $voyage->getCar() === null) {
            return null;
        }

        return $this->siegeRepository->findOneBy(['id' => (int) $id, 'car' => $voyage->getCar()]);
    }

    private function texte(mixed $valeur): ?string
    {
        if (!is_string($valeur)) {
            return null;
        }
        $valeur = trim($valeur);

        return $valeur === '' ? null : $valeur;
    }

    /** @return array<string, mixed> */
    private function refus(?string $reference, string $motif): array
    {
        return ['reference' => $reference, 'statut' => self::REFUSE, 'motif' => $motif];
    }

    /** @return array<string, mixed> */
    private function resultat(string $statut, string $reference, Ticket $ticket): array
    {
        return [
            'reference' => $reference,
            'statut' => $statut,
            'ticket' => [
                'id' => $ticket->getId(),
                'codeticket' => $ticket->getCodeticket(),
                'prix' => $ticket->getPrix(),
                'remise' => $ticket->getRemise(),
                'montantEncaisse' => $ticket->getMontantEncaisse(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function resultatBagage(string $statut, string $reference, Bagage $bagage): array
    {
        return [
            'reference' => $reference,
            'statut' => $statut,
            'bagage' => [
                'id' => $bagage->getId(),
                'codebagage' => $bagage->getCodebagage(),
                'montant' => $bagage->getMontant(),
                'montantforce' => $bagage->isMontantforce(),
                'montantEncaisse' => $bagage->getMontantEncaisse(),
            ],
        ];
    }
}
