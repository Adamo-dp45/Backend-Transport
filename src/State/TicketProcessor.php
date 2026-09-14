<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\TicketStatus;
use App\Entity\Ligne;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\ClientResolver;
use App\Domain\Service\ConfigRemiseService;
use App\Domain\Service\FideliteService;
use App\Repository\TarifRepository;
use Doctrine\DBAL\LockMode;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TicketProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private TarifRepository $tarifRepository,
        private ClientResolver $clientResolver,
        private FideliteService $fideliteService,
        private CapaciteService $capaciteService,
        private ActiviteLogger $activiteLogger,
        private ConfigRemiseService $configRemiseService,
        private VoyageGuard $voyageGuard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Ticket $data */
        return $this->emettre(
            $data,
            differee: false,
            ecrire: fn (Ticket $ticket) => $this->processor->process($ticket, $operation, $uriVariables, $context)
        );
    }

    /**
     * Émission d'un billet vendu HORS LIGNE par le vendeur à bord, rejouée à la synchronisation.
     *
     * Passe par le MÊME pipeline que la vente en ligne — cohérence du tronçon, provenance effective,
     * grille tarifaire, plafond de remise, rattachement client, canal commercial : tout est identique.
     * Dupliquer ces règles ailleurs les ferait dériver, et c'est le chemin d'écriture le plus chargé
     * en gardes de toute l'application.
     *
     * Seules trois choses changent, et elles sont documentées à leur point de relâchement ci-dessous :
     * l'occupation du siège, la capacité et le code de billet. Le verrou sur le voyage, lui, est
     * conservé : c'est lui qui sérialise le rejeu d'un lot.
     */
    public function emettreHorsLigne(Ticket $data): Ticket
    {
        return $this->emettre($data, differee: true, ecrire: function (Ticket $ticket): Ticket {
            $this->em->persist($ticket);
            $this->em->flush();

            return $ticket;
        });
    }

    /**
     * @param bool     $differee vente encaissée hors ligne, rejouée après coup (cf. emettreHorsLigne)
     * @param callable $ecrire   stratégie d'écriture : pipeline API Platform en ligne, persist direct hors ligne
     */
    private function emettre(Ticket $data, bool $differee, callable $ecrire)
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        $data
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId());

        // Rattache le billet à une identité client durable (find-or-create par téléphone).
        // Non-cassant : nomclient/contactclient restent le snapshot. Null si pas de téléphone.
        $data->setClient(
            $this->clientResolver->resolve($data->getNomclient(), $data->getContactclient(), $entrepriseId, $user->getId())
        );

        $voyage = $data->getVoyage();

        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé, la vente de tickets est impossible');
        }

        if (!$voyage->getCar()) {
            throw new BadRequestHttpException('Aucun véhicule affecté à ce voyage');
        }

        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce voyage n\'est pas rattaché à une ligne (lancez le backfill ou créez le voyage sur une ligne)');
        }

        // 1. Siège
        $siege = $data->getSiege();
        if (!$siege) {
            throw new BadRequestHttpException('Siège obligatoire');
        }
        if ($siege->getCar()->getId() !== $voyage->getCar()->getId()) {
            throw new BadRequestHttpException('Ce siège n\'appartient pas au véhicule affecté au voyage');
        }

        // 2. Gares montée / descente (descente par défaut = terminus de la ligne)
        $garemontee = $data->getGare();
        if (!$garemontee) {
            throw new BadRequestHttpException('La gare d\'embarquement (montée) est obligatoire');
        }
        $garedescente = $data->getGaredescente() ?? $ligne->getGareterminus();
        $data->setGaredescente($garedescente);

        // 3. Ordres des arrêts de la ligne
        $ordreParGare = $this->ordreParGare($ligne);
        $monteeId = $garemontee->getId();
        $descenteId = $garedescente->getId();

        // Sécurité : depuis OÙ l'utilisateur peut-il vendre ?
        // - Le COMMERCIAL du voyage (vendeur à bord) vend depuis la POSITION COURANTE du car
        //   ('garecourante'), qui avance le long de la ligne — pas depuis sa gare d'attache.
        // - Sinon, un agent rattaché à une gare ne vend qu'au départ de SA gare.
        $estCommercial = $voyage->getCommercial() && $voyage->getCommercial()->getId() === $user->getId();
        if ($estCommercial) {
            /*
                RELÂCHEMENT (1/3) — position du car, en vente DIFFÉRÉE.

                Le commercial EST dans le car : la gare où il a vendu est, par construction, celle où
                le véhicule se trouvait à cet instant. Entre-temps le car a pu avancer — lui-même le
                déclare, ou une gare en aval l'a réceptionné. Comparer la vente à la position ACTUELLE
                rejetterait donc systématiquement les billets d'un tronçon déjà franchi, c'est-à-dire
                précisément ceux qu'on synchronise.
            */
            $gc = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
            if (!$differee && $gc !== null && $monteeId !== $gc->getId()) {
                throw new BadRequestHttpException('Vous vendez depuis la position actuelle du car (' . $gc->getLibelle() . ')');
            }
        } else {
            $userGare = $user->getGare();
            if ($userGare !== null && $monteeId !== $userGare->getId()) {
                throw new BadRequestHttpException('Vous ne pouvez vendre que des tickets au départ de votre gare (' . $userGare->getLibelle() . ')');
            }
            /*
                Le car doit encore être là. Même garde que la réservation
                (ReservationCreationService) : les deux aboutissent au même siège du même car, il
                serait absurde que l'une refuse ce que l'autre vend.

                Volontairement HORS du cas commercial ci-dessus : le vendeur À BORD encaisse aussi
                après le départ (passagers montés sans avoir payé), et sa gare de vente est par
                construction la position du car. Le contraindre ici lui interdirait de travailler dès
                que le car s'ébranle.
            */
            $this->voyageGuard->assertMonteeNonDepassee(
                $voyage,
                $garemontee,
                'Le car a déjà quitté ' . $garemontee->getLibelle() . ' : plus de vente possible sur ce départ.'
            );
        }

        // Canal de vente FIGÉ sur le billet (snapshot). Vente EN ROUTE → recette du COMMERCIAL ;
        // vente au guichet → recette de la gare de montée. (cf. Ticket::$commercial, recetteParGare)
        $data->setCommercial($estCommercial ? $user : null);

        if (!isset($ordreParGare[$monteeId]) || !isset($ordreParGare[$descenteId])) {
            throw new BadRequestHttpException('La gare de montée ou de descente n\'est pas un arrêt de la ligne du voyage');
        }
        $ordreMontee = $ordreParGare[$monteeId];
        $ordreDescente = $ordreParGare[$descenteId];
        if ($ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('La gare de descente doit être située après la gare de montée sur la ligne');
        }
        // DÉPART PARTIEL : le voyage part de sa provenance réelle (origine effective) — aucune vente
        // avant cette gare (le car n'y passe jamais). Sans effet pour un départ normal (provenance = origine).
        $origineEff = $voyage->getOrigineEffective();
        $ordreProvenance = $origineEff !== null ? ($ordreParGare[$origineEff->getId()] ?? 0) : 0;
        if ($ordreMontee < $ordreProvenance) {
            throw new BadRequestHttpException('Ce voyage part de ' . ($origineEff?->getLibelle() ?? 'sa gare de provenance') . ' : aucune vente possible avant cette gare.');
        }

        // 4. Prix depuis la GRILLE GLOBALE de l'entreprise (couple de gares, indépendant de la ligne)
        $tarif = $this->tarifRepository->findMontant($monteeId, $descenteId, $entrepriseId);
        if (!$tarif) {
            throw new BadRequestHttpException('Aucun tarif défini pour ce trajet (' . $garemontee->getLibelle() . ' → ' . $garedescente->getLibelle() . ') dans la grille tarifaire');
        }

        $tarifMontant = $tarif->getMontant();

        if($data->isFideliteRecompense()) {
            // RÉCOMPENSE FIDÉLITÉ : la remise vient du programme (ex. 100 % = offert), pas d'un bénéficiaire.
            // Exclusive d'une remise manuelle (on n'empile pas les deux).
            $remise = $this->resoudreRemiseFidelite($data, $tarifMontant, $entrepriseId);
            $data->setBeneficiaire(null);
        } else {
            $remise = $this->resoudreRemise($data, $tarifMontant); /*
                - La remise est calculée à partir du type et de la valeur de remise 'pourcentage' ou 'montant' et du tarif
            */
            // Plafond ANTI-ABUS : la remise manuelle ne peut dépasser le % configuré (config remise dédiée,
            // null = pas de plafond). N'affecte PAS la récompense fidélité (autre branche).
            $capPct = $this->configRemiseService->get($entrepriseId)->getMaxpourcentage();
            if ($capPct !== null && $remise > 0 && $tarifMontant > 0) {
                $pct = $remise / $tarifMontant * 100;
                if ($pct > $capPct + 0.001) {
                    throw new BadRequestHttpException(sprintf(
                        'La remise de %d%% dépasse le plafond autorisé (%d%%) de votre compagnie.',
                        (int) round($pct), $capPct
                    ));
                }
            }
            // ── ÉTAT PRÉCÉDENT (désactivé) — le bénéficiaire était OBLIGATOIRE dès qu'une remise s'appliquait.
            // Le bénéficiaire est facultatif : on autorise désormais une remise SANS bénéficiaire.
            // if($remise > 0 && $data->getBeneficiaire() === null) {
            //     throw new BadRequestHttpException('Un bénéficiaire est obligatoire lorsqu\'une remise est appliquée');
            // }
            if($remise <= 0) {
                $data->setBeneficiaire(null);
            }
        }
        $data->setRemise($remise);

        // 5 + 7. Capacité par segment + code + persistance SOUS VERROU PESSIMISTE sur le voyage.
        // Sérialise les ventes concurrentes du MÊME voyage : un 2e agent qui vend au même instant
        // attend la fin de la 1re transaction, puis revérifie la disponibilité du siège SOUS verrou
        // → empêche la double-réservation d'un même siège/tronçon (et la collision de codeticket).
        return $this->em->wrapInTransaction(function () use (
            $data, $voyage, $siege, $entrepriseId, $ordreParGare, $ligne,
            $ordreMontee, $ordreDescente, $tarifMontant, $remise, $differee, $ecrire
        ) {
            $this->em->lock($voyage, LockMode::PESSIMISTIC_WRITE);

            /*
                RELÂCHEMENTS (2/3 et 3/3) — occupation du siège et capacité, en vente DIFFÉRÉE.

                Le passager est DÉJÀ ASSIS et a DÉJÀ PAYÉ : refuser ici n'annulerait pas la vente, cela
                ferait seulement disparaître un billet encaissé du système. On accepte donc, et c'est
                la doctrine d'éviction (CapaciteService::billetsEvinces, priorité à la gare amont) qui
                arbitre ensuite qui occupe réellement le siège — exactement le rôle pour lequel elle
                existe. Le surbooking est déjà assumé par le modèle ; le hors ligne en augmente la
                fréquence, il n'en change pas la nature.
            */
            if (!$differee) {
                // (Re)vérifie la disponibilité du siège sur le tronçon, à l'abri des ventes concurrentes
                $this->assertSiegeLibre($data, $voyage, $siege, $entrepriseId, $ordreParGare, $ligne, $ordreMontee, $ordreDescente);

                // Capacité : on ne vend pas une place promise à une réservation active (billets + réservations < capacité)
                $this->capaciteService->assertPlaceDisponible($voyage, $ordreMontee, $ordreDescente, $entrepriseId);
            }

            /*
                Le code : généré ici en ligne, REPRIS TEL QUEL en différé. Le téléphone l'a déjà
                imprimé sur le reçu du client, QR compris — le serveur ne peut plus en décider.
                L'unicité est garantie par la série dédiée au bord (suffixe « B ») et par l'index
                unique sur 'codeticket'.
            */
            if (!$differee) {
                $data->setCodeticket($voyage->getCodevoyage() . '-' . $this->generateCode($entrepriseId, $voyage->getId()));
            }

            // Prix NET (tarif - remise) : toutes les recettes (SUM(prix)) restent justes. Il vient
            // TOUJOURS de la grille, jamais de l'appareil — même hors ligne.
            $data->setPrix($tarifMontant - $remise);

            $result = $ecrire($data);

            // AUDIT anti-abus : toute remise (manuelle ou fidélité) est tracée dans le journal d'activité
            // — qui l'a posée (auteur), combien, pour qui, sur quel billet. Base de la détection des abus.
            if ($remise > 0) {
                $benef = $data->getBeneficiaire();
                $this->activiteLogger->log(
                    ActiviteLogger::TICKET_REMISE,
                    sprintf(
                        'Remise de %s FCFA (%s) sur le billet %s%s',
                        number_format($remise, 0, ',', ' '),
                        $data->isFideliteRecompense() ? 'récompense fidélité' : 'remise manuelle',
                        $data->getCodeticket(),
                        $benef !== null
                            ? ' — bénéficiaire : ' . trim($benef->getNom() ?? '') . ($benef->getCategorie() ? ' (' . $benef->getCategorie() . ')' : '')
                            : ''
                    ),
                    'Ticket',
                    $data->getId()
                );
            }

            return $result;
        });
    }

    /**
     * Calcule le montant de la remise (FCFA) à partir des entrées transitoires du ticket
     * (remisetype + remisevaleur) et du tarif. Valide les bornes.
     */
    private function resoudreRemise(Ticket $data, int $tarif): int
    {
        $type = $data->getRemisetype();
        $valeur = $data->getRemisevaleur();
        if ($valeur === null || $valeur <= 0 || $type === null) {
            return 0;
        }
        $remise = match ($type) {
            'POURCENTAGE' => (int) round($tarif * min($valeur, 100) / 100),
            'MONTANT' => $valeur,
            default => throw new BadRequestHttpException('Type de remise invalide'),
        };
        if ($remise < 0) {
            $remise = 0;
        }
        if ($remise > $tarif) {
            throw new BadRequestHttpException('La remise ne peut pas dépasser le prix du billet (' . $tarif . ' FCFA)');
        }
        return $remise;
    }

    /**
     * Récompense fidélité : valide l'éligibilité du client puis renvoie la remise (FCFA) issue du
     * programme (recompensePourcentage % du tarif). Le billet est ensuite marqué fideliteRecompense.
     */
    private function resoudreRemiseFidelite(Ticket $data, int $tarif, int $entrepriseId): int
    {
        $client = $data->getClient();
        if ($client === null) {
            throw new BadRequestHttpException('Un client identifié (téléphone) est requis pour utiliser une récompense fidélité');
        }
        if (!$client->isFidelite()) {
            throw new BadRequestHttpException('Ce client n\'est pas membre du programme de fidélité');
        }

        $programme = $this->fideliteService->getProgramme($entrepriseId);
        if (!$programme->isActif()) {
            throw new BadRequestHttpException('Le programme de fidélité est actuellement suspendu');
        }
        if (!$this->fideliteService->recompenseDisponible($client)) {
            throw new BadRequestHttpException('Ce client n\'a pas de récompense de fidélité disponible');
        }

        return (int) round($tarif * min($programme->getRecompensePourcentage(), 100) / 100);
    }

    /**
     * @return array<int, int> map gareId => ordre
     */
    private function ordreParGare(Ligne $ligne): array
    {
        $map = [];
        foreach ($ligne->getArrets() as $arret) {
            $map[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        return $map;
    }

    /**
     * Vérifie que le siège est disponible pour qui embarque à ordreMontee, avec PRIORITÉ À LA GARE AMONT :
     * on bloque uniquement si un passager existant est déjà assis à ce point (embarqué avant/à ordreMontee
     * et descend après). Un ticket vendu par une gare en aval (montée > ordreMontee) ne bloque pas — la gare
     * amont peut réutiliser le siège (le passager aval en conflit est réaccommodé par sa gare).
     */
    private function assertSiegeLibre(
        Ticket $data,
        Voyage $voyage,
        Siege $siege,
        int $entrepriseId,
        array $ordreParGare,
        Ligne $ligne,
        int $ordreMontee,
        int $ordreDescente
    ): void
    {
        $ordreTerminus = $ordreParGare[$ligne->getGareterminus()->getId()] ?? PHP_INT_MAX;

        $existants = $this->em->getRepository(Ticket::class)->findBy([
            'voyage' => $voyage,
            'siege' => $siege,
            'identreprise' => $entrepriseId,
            'statut' => TicketStatus::STATUT_VALIDE->value, // un billet reporté/annulé ne bloque plus le siège
            'deletedAt' => null,
        ]);

        foreach ($existants as $ticket) {
            if ($data->getId() !== null && $ticket->getId() === $data->getId()) {
                continue; // on s'ignore soi-même (cas d'une éventuelle modification)
            }
            $tm = $ordreParGare[$ticket->getGare()?->getId()] ?? null;
            // Descente EFFECTIVE : réelle si le passager est descendu en route (siège libéré), sinon vendue.
            $descenteEff = $ticket->getGaredescenteEffective();
            $td = $descenteEff
                ? ($ordreParGare[$descenteEff->getId()] ?? null)
                : $ordreTerminus; // anciens tickets sans descente = jusqu'au terminus
            if ($tm === null || $td === null) {
                continue;
            }
            // Priorité à la gare amont : on bloque seulement si un passager est DÉJÀ assis au moment où le
            // nouveau client embarque (embarqué avant/à $ordreMontee ET descend après). Une vente d'une gare
            // en aval (tm > ordreMontee) ne bloque PAS la gare amont — elle reste prioritaire sur le siège.
            if ($tm <= $ordreMontee && $td > $ordreMontee) {
                throw new BadRequestHttpException('Ce siège est déjà occupé sur ce tronçon du voyage');
            }
        }
    }

    private function generateCode(int $entrepriseId, int $voyageId): string
    {
        $count = $this->em->getRepository(Ticket::class)->count([
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
            'voyage' => $voyageId,
        ]);

        return 'TCK-' . date('Y') . '-' . ($count + 1);
    }
}
