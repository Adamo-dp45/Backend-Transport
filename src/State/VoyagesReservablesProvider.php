<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Gare;
use App\Entity\Output\Reservation\VoyageReservableDto;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\VoyageRepository;
use App\Security\VoyageGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Voyages sur lesquels le guichet peut ENCORE créer une réservation.
 *
 * Pendant du provider public (DepartsPubliquesProvider), côté agent. Mêmes deux conditions, qui sont
 * exactement celles que ReservationCreationService appliquera ensuite — proposer un départ que la
 * création refuserait derrière est pire que de n'en proposer aucun :
 *   1. le car n'a pas déjà quitté la gare de montée ;
 *   2. il reste le délai de présentation avant son passage à cette gare.
 *
 * La gare de montée est celle de l'AGENT (le guichet ne réserve qu'au départ de sa propre gare). Un
 * profil sans gare — admin, central — choisit librement : le voyage est retenu s'il reste AU MOINS
 * une gare où monter, ce qui suffit à écarter les voyages devenus inutiles.
 *
 * `?usage=vente` sert le formulaire de VENTE au guichet, qui n'obéit pas tout à fait aux mêmes
 * règles :
 *   - pas de délai de présentation : le passager est devant l'agent, on vend jusqu'au départ du car ;
 *   - la gare d'embarquement suit `TicketProcessor` — le COMMERCIAL du voyage vend depuis la position
 *     courante du car, pas depuis sa gare d'attache.
 * Un seul provider pour les deux usages : la condition de position, elle, doit rester unique.
 */
final class VoyagesReservablesProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private VoyageRepository $voyageRepository,
        private VoyageGuard $voyageGuard,
        private ReservationEcheanceService $echeance
    )
    {
    }

    /** @return VoyageReservableDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /*
            Sur une opération de COLLECTION, ApiPlatform exécute le provider AVANT d'évaluer
            l'expression 'security' : sans ce contrôle, une requête anonyme plantait ici (500) au lieu
            du 401 que renvoient les autres routes. On refuse donc explicitement.
        */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $entrepriseId = (int) $user->getEntreprise()?->getId();
        $pourVente = ($context['filters']['usage'] ?? null) === 'vente';
        $now = new \DateTimeImmutable();

        $reservables = [];
        foreach ($this->voyageRepository->findOuvertsPourEntreprise($entrepriseId) as $voyage) {
            // Le vendeur À BORD (commercial du voyage) vend depuis la position courante du car : la garde
            // de position ne s'applique pas à lui (cf. estReservableDepuis + TicketProcessor).
            $vendeurABord = $pourVente
                && $voyage->getCommercial() !== null
                && $voyage->getCommercial()->getId() === $user->getId();
            $gareAgent = $this->gareEmbarquement($voyage, $user, $pourVente);
            $montee = $gareAgent ?? $this->premiereGareEncoreOuverte($voyage, $now, $entrepriseId, $pourVente);
            if ($montee === null || !$this->estReservableDepuis($voyage, $montee, $now, $entrepriseId, $pourVente, $vendeurABord)) {
                continue;
            }

            $reservables[] = new VoyageReservableDto(
                id: (int) $voyage->getId(),
                codevoyage: $voyage->getCodevoyage(),
                numerodepart: $voyage->getNumerodepart(),
                provenance: $voyage->getProvenance(),
                destination: $voyage->getDestination(),
                heurepassage: $gareAgent === null
                    ? null // sans gare fixe, aucune heure de passage ne s'impose à l'agent
                    : $this->echeance->heurePassage($voyage, $gareAgent)?->format(\DateTimeInterface::ATOM),
                datedepartprevue: $voyage->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
                enRoute: $voyage->getDatedepartreelle() !== null,
            );
        }

        // Le plus imminent d'abord : c'est celui que l'agent sert au comptoir.
        usort($reservables, fn (VoyageReservableDto $a, VoyageReservableDto $b) => ($a->heurepassage ?? $a->datedepartprevue ?? '') <=> ($b->heurepassage ?? $b->datedepartprevue ?? ''));

        return $reservables;
    }

    /**
     * D'où CET utilisateur embarquerait-il sur CE voyage ? Miroir exact de TicketProcessor pour la
     * vente : le commercial du voyage embarque à la position courante du car (il est à bord), les
     * autres à leur gare d'attache. Null = profil sans gare fixe (admin, central).
     */
    private function gareEmbarquement(Voyage $voyage, User $user, bool $pourVente): ?Gare
    {
        if ($pourVente
            && $voyage->getCommercial() !== null
            && $voyage->getCommercial()->getId() === $user->getId()
        ) {
            return $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        }

        return $user->getGare();
    }

    private function estReservableDepuis(Voyage $voyage, Gare $montee, \DateTimeImmutable $now, int $entrepriseId, bool $pourVente, bool $vendeurABord = false): bool
    {
        /*
            VENDEUR À BORD : il vend depuis la POSITION COURANTE du car ('garecourante'), qui EST sa gare
            de montée — le car y est, elle n'est donc jamais « dépassée ». On n'applique pas la garde de
            position, exactement comme TicketProcessor qui la saute pour le commercial. Sans ça, au
            DÉMARRAGE (garecourante = origine ⇒ 'montee <= origine' ⇒ monteeDepassee = true) le commercial
            perdait son propre voyage dans le sélecteur. Seul le terminus n'a aucun tronçon à vendre après.
        */
        if ($vendeurABord) {
            return $montee->getId() !== $voyage->getLigne()?->getGareterminus()?->getId();
        }
        /*
            DÉPART PARTIEL : le car ne passe JAMAIS par les gares situées avant sa provenance
            effective. 'monteeDepassee' ne le voit pas tant que le voyage n'est pas parti (elle
            renvoie false faute de position connue), si bien qu'un départ lancé depuis Bouaké était
            proposé au guichet d'Abidjan — que 'TicketProcessor' et 'ReservationCreationService'
            refusaient ensuite (« aucune vente possible avant cette gare »). Proposer un départ que
            la création refuse derrière est pire que de n'en proposer aucun : on applique donc ici la
            même borne qu'eux.
        */
        if (!$this->surLaRouteEffective($voyage, $montee)) {
            return false;
        }
        if ($this->voyageGuard->monteeDepassee($voyage, $montee)) {
            return false;
        }
        // La VENTE n'exige pas de délai de présentation : le passager est déjà au guichet.
        if ($pourVente) {
            return true;
        }
        $limite = $this->echeance->limitePresentationPour($voyage, $montee, $entrepriseId);

        return $limite !== null && $limite > $now;
    }

    /**
     * La gare est-elle sur la route que le car parcourt RÉELLEMENT (provenance effective → terminus) ?
     *
     * Une gare de la ligne située en amont d'un départ partiel n'en fait pas partie.
     */
    private function surLaRouteEffective(Voyage $voyage, Gare $montee): bool
    {
        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            return true; // sans ligne, rien à borner : on ne masque pas le voyage
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $gare = $arret->getGare();
            if ($gare !== null) {
                $ordreParGare[$gare->getId()] = (int) $arret->getOrdre();
            }
        }

        $ordreMontee = $ordreParGare[$montee->getId()] ?? null;
        if ($ordreMontee === null) {
            return false; // gare hors de la ligne
        }

        $origine = $voyage->getOrigineEffective();
        $ordreOrigine = $origine !== null ? ($ordreParGare[$origine->getId()] ?? 0) : 0;

        return $ordreMontee >= $ordreOrigine;
    }

    /** Première gare de la ligne où il reste possible de monter, ou null s'il n'y en a plus aucune. */
    private function premiereGareEncoreOuverte(Voyage $voyage, \DateTimeImmutable $now, int $entrepriseId, bool $pourVente): ?Gare
    {
        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            return null;
        }

        $arrets = $ligne->getArrets()->toArray();
        usort($arrets, fn ($a, $b) => (int) $a->getOrdre() <=> (int) $b->getOrdre());
        foreach ($arrets as $arret) {
            $gare = $arret->getGare();
            // Le terminus ne se monte pas : il n'y a plus de tronçon après lui.
            if ($gare === null || $gare->getId() === $ligne->getGareterminus()?->getId()) {
                continue;
            }
            if ($this->estReservableDepuis($voyage, $gare, $now, $entrepriseId, $pourVente)) {
                return $gare;
            }
        }

        return null;
    }
}
