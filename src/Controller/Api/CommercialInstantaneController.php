<?php

namespace App\Controller\Api;

use App\Domain\Enum\TicketStatus;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\ConfigRemiseService;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\BagageRepository;
use App\Repository\TarifbagageRepository;
use App\Repository\TarifRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * INSTANTANÉ d'un voyage : tout ce dont le téléphone du vendeur à bord a besoin pour vendre SEUL.
 *
 * Téléchargé quand le réseau est encore là, il arme le mode hors ligne. Ce qu'il contient est dicté
 * par une seule question : que faut-il pour rejouer, sur un téléphone, les calculs que le serveur
 * ferait ?
 *
 *  - la LIGNE et ses arrêts ordonnés → cohérence du tronçon (montée avant descente) ;
 *  - les SIÈGES du car et les billets en cours → plan de sièges, avec la même règle d'occupation que
 *    'SiegeStateProvider', le manifeste nominatif pour le contrôle à bord, et de quoi RÉIMPRIMER un
 *    reçu perdu en route ;
 *  - les BAGAGES enregistrés par ce commercial → le second volet de « Mes ventes » ;
 *  - la GRILLE TARIFAIRE des couples desservis → le prix, que le téléphone peut calculer entièrement
 *    seul (la grille ne dépend ni de la ligne, ni de la date, ni du remplissage) ;
 *  - la GRILLE DE POIDS des bagages → le montant d'un bagage, même calcul de tranche que
 *    'TarifbagageRepository::findTarifForPoids' ;
 *  - le PLAFOND DE REMISE → un entier ;
 *  - l'EN-TÊTE de la compagnie → le reçu s'imprime sans réseau.
 *
 * Ce qui n'y est PAS, et ne peut pas y être : la récompense de fidélité, dérivée de tout l'historique
 * des billets de la compagnie. Deux appareils hors ligne brûleraient la même. La vente hors ligne
 * l'ignore donc, et le client la récupère à son passage suivant au guichet.
 *
 * L'instantané VIEILLIT dès qu'il est pris — d'autres gares vendent pendant ce temps. C'est assumé :
 * le conflit d'occupation est arbitré à la synchronisation par la doctrine d'éviction, pas ici.
 */
final class CommercialInstantaneController extends AbstractController
{
    #[Route(
        '/api/voyages/{id}/me/instantane',
        name: 'api_voyages_me_instantane',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function instantane(
        int $id,
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository,
        TarifRepository $tarifRepository,
        TarifbagageRepository $tarifbagageRepository,
        BagageRepository $bagageRepository,
        ConfigRemiseService $configRemiseService,
        CapaciteService $capaciteService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }
        $entrepriseId = $user->getEntreprise()?->getId();

        $voyage = $voyageRepository->findOneBy(['id' => $id, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
        if ($voyage === null) {
            return $this->json(['message' => 'Voyage introuvable'], 404);
        }
        if ($voyage->getCommercial()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Seul le commercial de ce voyage peut en armer le mode hors ligne'], 403);
        }

        $ligne = $voyage->getLigne();
        if ($ligne === null || $voyage->getCar() === null) {
            return $this->json([
                'message' => 'Ce voyage n\'a pas de ligne ou pas de véhicule : la vente hors ligne ne peut pas être armée',
            ], 409);
        }

        $arrets = [];
        foreach ($ligne->getArrets() as $arret) {
            $arrets[] = [
                'gareId' => $arret->getGare()?->getId(),
                'libelle' => $arret->getGare()?->getLibelle(),
                'ordre' => $arret->getOrdre(),
            ];
        }
        usort($arrets, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);

        /*
            La DISPOSITION accompagne chaque siège ('rangee', 'colonne', 'cote') : le plan hors ligne
            se dessine comme un car — sièges de gauche, couloir, sièges de droite — et non comme une
            file de cinquante cases. Sans elle, le téléphone ne peut pas reconstituer la vue du
            guichet.
        */
        $sieges = [];
        foreach ($voyage->getCar()->getSieges() as $siege) {
            $sieges[] = [
                'id' => $siege->getId(),
                'numero' => $siege->getNumero(),
                'rangee' => $siege->getRangee(),
                'colonne' => $siege->getColonne(),
                'cote' => $siege->getCote(),
            ];
        }
        usort($sieges, static fn (array $a, array $b): int => [$a['rangee'], $a['colonne']] <=> [$b['rangee'], $b['colonne']]);

        /*
            Les billets VALIDES du voyage, tous canaux confondus. Ce bloc sert DEUX lecteurs, et il
            faut le savoir pour le lire :

             * le PLAN DE SIÈGES, qui n'a besoin que des bornes du tronçon ('monteeId' /
               'descenteId', cette dernière étant la descente EFFECTIVE) ;
             * le MANIFESTE nominatif, qui a besoin de l'identité et du siège affiché.

            'evince' est calculé ici parce qu'un téléphone ne peut pas le refaire : l'éviction se
            déduit de la capacité du car et de la priorité amont, sur l'ensemble des billets. Le
            manifeste s'en sert pour ne pas annoncer des passagers qui ne monteront pas ; le plan de
            sièges l'ignore, exactement comme en ligne.
        */
        $evinces = $capaciteService->billetsEvinces($voyage, $entrepriseId);
        $billets = [];
        foreach ($ticketRepository->findBy([
            'voyage' => $voyage,
            'identreprise' => $entrepriseId,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]) as $billet) {
            $billets[] = [
                'siegeId' => $billet->getSiege()?->getId(),
                'monteeId' => $billet->getGare()?->getId(),
                'descenteId' => $billet->getGaredescenteEffective()?->getId(),
                'descenteAfficheeId' => $billet->getGaredescente()?->getId(),
                'ticketId' => $billet->getId(),
                'codeticket' => $billet->getCodeticket(),
                'siegeNumero' => $billet->getSiege()?->getNumero(),
                'nomclient' => $billet->getNomclient(),
                'contactclient' => $billet->getContactclient(),
                'aBord' => $billet->getCommercial() !== null,
                /*
                    'aBord' dit le CANAL (vendu par un commercial plutôt qu'au guichet) et sert le
                    manifeste ; 'aMoi' dit que c'est MA vente, et c'est lui que « Mes ventes » filtre.
                    Les deux coïncident tant qu'un voyage n'a qu'un commercial — mais le déduire
                    reviendrait à faire reposer un périmètre sur une coïncidence.
                */
                'aMoi' => $billet->getCommercial()?->getId() === $user->getId(),
                'evince' => isset($evinces[$billet->getId()]),
                // De quoi RÉIMPRIMER le reçu sans réseau : un passager qui perd son billet en route
                // est exactement le cas où le commercial n'a pas de couverture.
                'prix' => $billet->getPrix(),
                'remise' => $billet->getRemise(),
                'statut' => $billet->getStatut(),
                'dateEmission' => $billet->getCreatedAt()?->format(DATE_ATOM),
            ];
        }

        /*
            Les BAGAGES enregistrés par ce commercial sur ce voyage — le second volet de « Mes
            ventes ». Restreints à son canal : la page ne montre que ce qu'il a lui-même encaissé,
            et c'est la même restriction que l'endpoint en ligne.
        */
        $bagages = [];
        foreach ($bagageRepository->findBy([
            'voyage' => $voyage,
            'commercial' => $user,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]) as $bagage) {
            $billet = $bagage->getTicket();
            $bagages[] = [
                'id' => $bagage->getId(),
                'codebagage' => $bagage->getCodebagage(),
                'nature' => $bagage->getNature(),
                'type' => $bagage->getType(),
                'poids' => $bagage->getPoids(),
                'montant' => $bagage->getMontant(),
                'montantforce' => $bagage->isMontantforce(),
                'statut' => $bagage->getStatut(),
                'ticketId' => $billet?->getId(),
                'codeticket' => $billet?->getCodeticket(),
                'nomclient' => $billet?->getNomclient(),
                'contactclient' => $billet?->getContactclient(),
                'monteeId' => $bagage->getGaredepart()?->getId(),
                'descenteId' => $bagage->getGaredescente()?->getId(),
            ];
        }

        /*
            La grille des couples réellement atteignables depuis les arrêts de CETTE ligne. Inutile
            d'embarquer toute la grille de la compagnie : le commercial ne vend que sur sa ligne, en
            aval de la position du car.
        */
        $tarifs = [];
        foreach ($arrets as $depart) {
            foreach ($arrets as $arrivee) {
                if ($depart['ordre'] >= $arrivee['ordre']) {
                    continue;
                }
                $tarif = $tarifRepository->findMontant($depart['gareId'], $arrivee['gareId'], $entrepriseId);
                if ($tarif !== null) {
                    $tarifs[] = [
                        'departId' => $depart['gareId'],
                        'arriveeId' => $arrivee['gareId'],
                        'montant' => $tarif->getMontant(),
                    ];
                }
            }
        }

        /*
            La grille de poids des bagages, triée comme le fait la requête serveur (poidsmin croissant,
            première tranche gagnante). Le téléphone applique la MÊME règle : 'poidsmin <= poids' et
            'poidsmax >= poids ou illimité'. 'poidsmax' nul = dernière tranche, qui couvre tout ce qui
            dépasse.
        */
        $tarifsBagage = [];
        foreach ($tarifbagageRepository->findBy(
            ['identreprise' => $entrepriseId, 'deletedAt' => null],
            ['poidsmin' => 'ASC']
        ) as $tranche) {
            $tarifsBagage[] = [
                'poidsmin' => $tranche->getPoidsmin(),
                'poidsmax' => $tranche->getPoidsmax(),
                'montant' => $tranche->getMontant(),
            ];
        }

        $entreprise = $user->getEntreprise();

        return $this->json([
            'preLePar' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'voyage' => [
                'id' => $voyage->getId(),
                'codevoyage' => $voyage->getCodevoyage(),
                'provenance' => $voyage->getProvenance(),
                'destination' => $voyage->getDestination(),
                'datedepartprevue' => $voyage->getDatedepartprevue()?->format(DATE_ATOM),
                'carId' => $voyage->getCar()->getId(),
                'garecouranteId' => ($voyage->getGarecourante() ?? $voyage->getOrigineEffective())?->getId(),
                'origineEffectiveId' => $voyage->getOrigineEffective()?->getId(),
            ],
            'arrets' => $arrets,
            'sieges' => $sieges,
            'billets' => $billets,
            'bagages' => $bagages,
            'tarifs' => $tarifs,
            'tarifsBagage' => $tarifsBagage,
            'plafondRemisePourcentage' => $configRemiseService->get($entrepriseId)->getMaxpourcentage(),
            // Les quatre champs de l'en-tête de reçu, tels que 'GET /api/me/entreprise' les rend :
            // hors ligne, c'est cette copie qui imprime le billet et l'étiquette de bagage.
            'entreprise' => [
                'libelle' => $entreprise?->getLibelle(),
                'sigle' => $entreprise?->getSigle(),
                'contact1' => $entreprise?->getContact1(),
                'contact2' => $entreprise?->getContact2(),
            ],
        ]);
    }
}
