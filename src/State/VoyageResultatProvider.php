<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\VoyageResultatService;
use App\Entity\Depense;
use App\Entity\Output\Exploitation\VoyageDepenseLigneDto;
use App\Entity\Output\Exploitation\VoyageResultatDto;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Le résultat d'un départ, servi HORS PÉRIMÈTRE DE GARE.
 *
 * Le voyage est chargé par le repository et non par le provider automatique d'API Platform : c'est
 * ce qui met la lecture hors d'atteinte de `GareScopeExtension`, dont le filtre ferait varier la
 * recette d'un même départ selon la gare du lecteur. Même procédé que `BordereauProvider`, qui a
 * besoin des données complètes du voyage pour la même raison.
 *
 * La VISIBILITÉ du voyage reste tenue, elle : ouvrir le périmètre ne veut pas dire l'abandonner. On
 * rejoue la règle de `LigneGareScopedInterface` — un agent rattaché ne lit que les départs d'une
 * ligne qui DESSERT sa gare —, faute de quoi cette route deviendrait une porte pour lire la recette
 * de n'importe quel départ de l'entreprise, là où l'item `/api/voyages/{id}` la refuse. 404 et non
 * 403 : le voyage est réputé introuvable, comme partout ailleurs dans le périmètre.
 */
class VoyageResultatProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly VoyageRepository $voyageRepository,
        private readonly VoyageResultatService $resultat
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if ($user === null) {
            // Le provider tourne AVANT 'security' sur une opération personnalisée : on refuse
            // l'utilisateur nul soi-même, sinon l'appel anonyme produit un 500 au lieu d'un 401.
            throw new NotFoundHttpException('Voyage introuvable');
        }

        $voyage = $this->voyageRepository->findOneBy([
            'id' => $uriVariables['id'] ?? null,
            'identreprise' => $user->getEntreprise()?->getId(),
            'deletedAt' => null,
        ]);

        if (!$voyage instanceof Voyage || !$this->estVisible($voyage, $user)) {
            throw new NotFoundHttpException('Voyage introuvable');
        }

        // Les MONTANTS DE CHARGE restent une question de permission : le périmètre de gare s'ouvre,
        // le droit de lire de l'argent non.
        $avecDepenses = $this->security->isGranted('VOIR', 'Depense');
        $r = $this->resultat->pour($voyage, $avecDepenses);

        return new VoyageResultatDto(
            voyageId: (int) $voyage->getId(),
            codevoyage: (string) $voyage->getCodevoyage(),
            billets: $r['billets']['montant'],
            nbBillets: $r['billets']['nb'],
            reservations: $r['reservations']['montant'],
            nbReservations: $r['reservations']['nb'],
            bagages: $r['bagages']['montant'],
            nbBagages: $r['bagages']['nb'],
            courriers: $r['courriers']['montant'],
            nbCourriers: $r['courriers']['nb'],
            recette: $r['recette'],
            depenses: $r['depenses']['montant'] ?? null,
            nbDepenses: $r['depenses']['nb'] ?? null,
            resultat: $r['resultat'],
            lignesDepenses: array_map(
                static fn (Depense $d): VoyageDepenseLigneDto => new VoyageDepenseLigneDto(
                    id: (int) $d->getId(),
                    datedepense: $d->getDatedepense()->format(\DateTimeInterface::ATOM),
                    montant: (int) $d->getMontant(),
                    typedepense: $d->getTypedepense()?->getLibelle(),
                    libelle: $d->getLibelle(),
                    beneficiaire: $d->getBeneficiaire() ?? $d->getFournisseur()?->getLibelle(),
                    modereglement: $d->getModereglement(),
                    gare: $d->getGare()?->getLibelle(),
                ),
                $r['lignesDepenses']
            ),
            picOccupation: $r['picOccupation'],
            capacite: $r['capacite'],
            placesRestantes: $r['placesRestantes'],
            tauxRemplissage: $r['tauxRemplissage'],
            troncons: $r['troncons'],
        );
    }

    /**
     * La règle de `Voyage::ligneScopePath()`, rejouée en PHP : la ligne du voyage a-t-elle un arrêt à
     * la gare de l'acteur ? Admins et utilisateurs centraux sans gare voient tout, comme dans
     * `GareScopeExtension`.
     */
    private function estVisible(Voyage $voyage, User $user): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        $gare = $user->getGare();
        if ($gare === null) {
            return true;
        }

        foreach ($voyage->getLigne()?->getArrets() ?? [] as $arret) {
            if ($arret->getGare()?->getId() === $gare->getId()) {
                return true;
            }
        }

        return false;
    }
}
