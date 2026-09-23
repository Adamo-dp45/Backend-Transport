<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Suivi des cars pour SA gare (tout agent rattaché, donnée opérationnelle et non financière → pas de
 * restriction admin de gare). Liste les voyages en cours qui desservent la gare, répartis selon la
 * position courante du car (garecourante) :
 *  - « vers ma gare » : le car n'a pas encore atteint ma gare (en approche),
 *  - « depuis ma gare » : le car est à ma gare ou l'a dépassée (en partance / parti).
 */
final class GareSuiviController extends AbstractController
{
    #[Route('/api/gares/me/suivi', name: 'api_gare_me_suivi', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function suivi(
        Security $security,
        VoyageRepository $voyageRepository,
        \App\Domain\Service\ReservationEcheanceService $echeance
    ): JsonResponse {
        /** @var User $user */
        $user = $security->getUser();
        $gare = $user->getGare();
        if (!$gare) {
            return $this->json(['gare' => null, 'versMaGare' => [], 'depuisMaGare' => []]);
        }
        $gareId = $gare->getId();
        $entId = $user->getEntreprise()->getId();

        $voyages = $voyageRepository->enCoursDesservantGare($gareId, $entId);

        $versMaGare = [];
        $depuisMaGare = [];

        foreach ($voyages as $voyage) {
            $ligne = $voyage->getLigne();
            if (!$ligne) {
                continue;
            }

            // Carte gare → ordre sur la ligne (tous les arrêts)
            $ordreParGare = [];
            $libelleParOrdre = [];
            foreach ($ligne->getArrets() as $arret) {
                $g = $arret->getGare();
                if ($g) {
                    $ordreParGare[$g->getId()] = $arret->getOrdre();
                    $libelleParOrdre[$arret->getOrdre()] = $g->getLibelle();
                }
            }
            if (!isset($ordreParGare[$gareId])) {
                continue; // sécurité : ma gare doit être sur la ligne
            }
            $ordreGare = $ordreParGare[$gareId];

            // Étapes ordonnées (libellés) pour la frise du mini-plan de ligne
            ksort($libelleParOrdre);
            $etapes = array_values($libelleParOrdre);

            // Position courante du car (par défaut : origine de la ligne)
            $courante = $voyage->getGarecourante();
            $ordreCourante = $courante && isset($ordreParGare[$courante->getId()])
                ? $ordreParGare[$courante->getId()]
                : 0;

            $item = $this->item($voyage, $courante, $ordreGare, $ordreCourante, $etapes, $gare, $echeance);

            if ($ordreCourante < $ordreGare) {
                $versMaGare[] = $item;
            } else {
                $depuisMaGare[] = $item;
            }
        }

        // En approche : le plus proche d'abord ; en partance : celui qui vient de partir d'abord.
        usort($versMaGare, fn ($a, $b) => $a['arrets'] <=> $b['arrets']);
        usort($depuisMaGare, fn ($a, $b) => $a['arrets'] <=> $b['arrets']);

        return $this->json([
            'gare' => ['id' => $gareId, 'libelle' => $gare->getLibelle()],
            'versMaGare' => $versMaGare,
            'depuisMaGare' => $depuisMaGare,
        ]);
    }

    private function item(
        Voyage $voyage,
        ?\App\Entity\Gare $courante,
        int $ordreGare,
        int $ordreCourante,
        array $etapes,
        \App\Entity\Gare $maGare,
        \App\Domain\Service\ReservationEcheanceService $echeance
    ): array {
        $commercial = $voyage->getCommercial();

        /*
            ETA chez MOI = heure de passage PRÉVUE à ma gare, décalée du retard COURANT du car
            (mesuré à sa position réelle). Sans ça, une gare en aval ne dispose que de l'horaire
            théorique et découvre le retard à l'arrivée du car. Le retard peut être négatif : le car
            est en avance, et l'ETA se rapproche — c'est une information tout aussi utile à quai.

            Les deux composantes restent exposées séparément : une gare doit pouvoir distinguer
            « prévu 14:00, +25 min » de « 14:25 » sec, et l'ETA disparaît si le retard n'est pas
            mesurable (car pas encore parti) plutôt que d'afficher une heure faussement sûre.
        */
        $heurePrevue = $echeance->heurePassage($voyage, $maGare);
        $retard = $echeance->retardCourantMinutes($voyage);
        $eta = ($heurePrevue !== null && $retard !== null)
            ? $heurePrevue->modify(($retard >= 0 ? '+' : '') . $retard . ' minutes')
            : null;

        return [
            'voyageId' => $voyage->getId(),
            'codevoyage' => $voyage->getCodevoyage(),
            'numerodepart' => $voyage->getNumerodepart(),
            'ligne' => $voyage->getLigne()?->getLibelle(),
            'provenance' => $voyage->getProvenance(),
            'destination' => $voyage->getDestination(),
            'car' => $voyage->getCar()?->getMatricule(),
            'positionActuelle' => $courante?->getLibelle(),
            'aQuai' => $ordreCourante === $ordreGare, // le car est à ma gare
            'arrets' => abs($ordreGare - $ordreCourante), // nb d'arrêts entre le car et ma gare
            'commercial' => $commercial ? trim($commercial->getPrenom() . ' ' . $commercial->getNom()) : null,
            'datedepartprevue' => $voyage->getDatedepartprevue()?->format('Y-m-d H:i'),
            // Horaires à MA gare : prévu, retard courant du car, et heure d'arrivée estimée
            'heurePrevue' => $heurePrevue?->format('H:i'),
            'retardMinutes' => $retard,
            'eta' => $eta?->format('H:i'),
            // Mini-plan de ligne : étapes ordonnées + position du car et de ma gare
            'etapes' => $etapes,
            'nbArrets' => count($etapes),
            'ordreCar' => $ordreCourante,
            'ordreGare' => $ordreGare,
        ];
    }
}
