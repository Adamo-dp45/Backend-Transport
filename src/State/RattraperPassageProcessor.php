<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\PassageService;
use App\Domain\Service\PositionCouranteService;
use App\Entity\Dto\RattrapagePassageInput;
use App\Entity\Gare;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * RATTRAPAGE d'un passage oublié : `PATCH /voyages/{id}/rattraper-passage` (administrateur).
 *
 * La réception exige désormais que les arrêts situés en amont aient été pointés — sans quoi une gare
 * en aval, en réceptionnant trop tôt, punit celles qu'elle survole. Mais un agent qui oublie de
 * réceptionner bloquerait alors toute la ligne derrière lui. Cette action est la porte de sortie, et
 * elle est volontairement ÉTROITE.
 *
 * ELLE NE RÉCEPTIONNE PAS : elle consigne un PASSAGE. Les courriers et bagages qui descendent à cette
 * gare ne sont PAS basculés en « réceptionné » / « livré », parce que rien ne prouve qu'ils aient été
 * remis — s'il n'y avait personne pour pointer le car, il n'y avait probablement personne pour
 * décharger. La gare fera sa réception quand elle le pourra : l'arrivée étant déjà posée,
 * `PassageService` ne la réécrira pas, et seuls les colis basculeront.
 *
 * Ce qu'elle fait, en revanche, est exactement ce que fait une réception sur la POSITION du car
 * (`PositionCouranteService`, partagé) : les deux chemins ne peuvent pas diverger.
 */
class RattraperPassageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private PassageService $passageService,
        private PositionCouranteService $positionCourante,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var RattrapagePassageInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()?->getId();

        // Le voyage est relu SOUS PÉRIMÈTRE d'entreprise : l'opération reçoit un DTO, elle n'a donc
        // pas traversé le provider scopé. Sans ce filtre, un identifiant deviné ouvrirait le voyage
        // d'une autre compagnie (même piège que 'AvancerCommercialProcessor').
        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'] ?? null,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        $gare = $data->gare;
        $arrivee = $data->arrivee;
        if ($gare === null || $arrivee === null) {
            throw new BadRequestHttpException('La gare et l\'heure réelle du passage sont obligatoires');
        }

        $this->assertGareRattrapable($voyage, $gare);
        $this->assertChronologie($voyage, $gare, $arrivee);

        $this->passageService->marquerArrivee($voyage, $gare, $arrivee);
        $this->positionCourante->avancerA($voyage, $gare, $arrivee);

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_PASSAGE_RATTRAPE,
            sprintf(
                'Passage rattrapé à %s (arrivée déclarée le %s)',
                (string) $gare->getLibelle(),
                $arrivee->format('d/m/Y H:i')
            ),
            (int) $voyage->getId()
        );

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }

    /**
     * La gare est-elle un arrêt TRAVERSÉ de ce voyage, et son passage reste-t-il à consigner ?
     */
    private function assertGareRattrapable(Voyage $voyage, Gare $gare): void
    {
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : sa chronologie n\'est plus modifiable');
        }

        /*
            Le DÉPART doit être consigné avant tout rattrapage. Une réception ordinaire peut le poser
            elle-même, mais elle l'horodate à l'instant présent : appliqué ici, cela placerait le
            départ APRÈS l'arrivée rétroactive que l'on est en train de déclarer. Plutôt qu'inventer
            une chronologie fausse, on renvoie l'administrateur au démarrage du voyage.
        */
        if ($voyage->getDatedepartreelle() === null) {
            throw new BadRequestHttpException(
                'Le départ de ce voyage n\'a pas été enregistré : commencez par le démarrer, '
                . 'sinon le passage rattrapé serait antérieur au départ'
            );
        }

        $ligne = $voyage->getLigne();
        if ($ligne === null) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = (int) $arret->getOrdre();
        }
        if (!isset($ordreParGare[$gare->getId()])) {
            throw new BadRequestHttpException(sprintf(
                '%s n\'est pas un arrêt de la ligne de ce voyage',
                (string) $gare->getLibelle()
            ));
        }

        $origine = $voyage->getOrigineEffective();
        $ordreOrigine = $origine !== null ? ($ordreParGare[$origine->getId()] ?? 0) : 0;
        if ($ordreParGare[$gare->getId()] <= $ordreOrigine) {
            throw new BadRequestHttpException(
                'Ce voyage ne passe pas par cette gare : elle est à son origine, ou en amont de son départ'
            );
        }
        if ($gare->getId() === $ligne->getGareterminus()?->getId()) {
            throw new BadRequestHttpException(
                'Le terminus ne se rattrape pas : c\'est la clôture du voyage qui l\'horodate'
            );
        }

        // Déjà pointée : on ne réécrit jamais un horodatage existant. Le premier fait foi — ici comme
        // dans 'PassageService', et pour la même raison : une heure corrigée après coup rendrait
        // indéfendable tout ce qui en découle (retards, échéances, réservations fermées).
        $passage = $this->passageService->connu($voyage, $gare);
        if ($passage?->getArriveeReelle() !== null) {
            throw new BadRequestHttpException(sprintf(
                'Le passage à %s est déjà consigné (%s) : il n\'y a rien à rattraper',
                (string) $gare->getLibelle(),
                $passage->getArriveeReelle()->format('d/m/Y H:i')
            ));
        }
    }

    /**
     * L'heure déclarée doit s'INSÉRER entre les passages déjà connus.
     *
     * C'est la garde qui rend le rattrapage sûr. Sans elle, on rouvrirait par la porte de service le
     * défaut que la garde d'ordre vient de fermer : une arrivée hors séquence fausse les durées de
     * tronçon, les retards par gare et le recalage des horaires — et cette fois avec la signature
     * d'un administrateur, donc sans que personne ne songe à en douter.
     */
    private function assertChronologie(Voyage $voyage, Gare $gare, \DateTimeImmutable $arrivee): void
    {
        // On répare le passé, on ne prédit pas l'avenir. Une arrivée post-datée ferait passer le car
        // pour EN AVANCE à cette gare, et l'avance se propage dans la ponctualité comme un retard.
        if ($arrivee > new \DateTimeImmutable()) {
            throw new BadRequestHttpException(
                'Le passage rattrapé ne peut pas être dans le futur : on consigne une arrivée qui a eu lieu'
            );
        }

        $ligne = $voyage->getLigne();
        $ordreParGare = [];
        foreach ($ligne?->getArrets() ?? [] as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = (int) $arret->getOrdre();
        }
        $ordreCible = $ordreParGare[$gare->getId()] ?? null;
        if ($ordreCible === null) {
            return;
        }

        // Borne BASSE : le dernier instant connu en amont — départ du voyage, ou passage d'une gare
        // précédente. Borne HAUTE : le premier instant connu en aval.
        $avant = $voyage->getDatedepartreelle();
        $avantLibelle = 'du départ du voyage';
        $apres = null;
        $apresLibelle = null;

        foreach ($voyage->getPassages() as $passage) {
            $gareId = $passage->getGare()?->getId();
            $ordre = $gareId !== null ? ($ordreParGare[$gareId] ?? null) : null;
            if ($ordre === null || $ordre === $ordreCible) {
                continue;
            }

            // Le départ d'une gare vaut mieux que son arrivée comme borne basse : c'est le dernier
            // moment où le car y était encore.
            $instant = $ordre < $ordreCible
                ? ($passage->getDepartReelle() ?? $passage->getArriveeReelle())
                : $passage->getArriveeReelle();
            if ($instant === null) {
                continue;
            }

            if ($ordre < $ordreCible && ($avant === null || $instant > $avant)) {
                $avant = $instant;
                $avantLibelle = 'du passage à ' . (string) $passage->getGare()?->getLibelle();
            }
            if ($ordre > $ordreCible && ($apres === null || $instant < $apres)) {
                $apres = $instant;
                $apresLibelle = 'du passage à ' . (string) $passage->getGare()?->getLibelle();
            }
        }

        if ($avant !== null && $arrivee <= $avant) {
            throw new BadRequestHttpException(sprintf(
                'Le passage à %s doit être postérieur à celui %s (%s)',
                (string) $gare->getLibelle(),
                $avantLibelle,
                $avant->format('d/m/Y H:i')
            ));
        }
        if ($apres !== null && $arrivee >= $apres) {
            throw new BadRequestHttpException(sprintf(
                'Le passage à %s doit être antérieur à celui %s (%s)',
                (string) $gare->getLibelle(),
                $apresLibelle,
                $apres->format('d/m/Y H:i')
            ));
        }
    }
}
