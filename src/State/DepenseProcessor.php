<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Depense;
use App\Entity\Fournisseur;
use App\Entity\Gare;
use App\Entity\MediaObject;
use App\Entity\Typedepense;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Écriture d'une DÉPENSE : imputation, appartenance des références, audit.
 *
 * L'IMPUTATION est la règle qui compte. Une dépense appartient à une gare, ou au SIÈGE quand elle
 * n'en a pas. Laisser un agent choisir librement ouvrirait deux portes : imputer sa charge à la gare
 * voisine (qui la verrait sans l'avoir engagée), ou l'imputer au siège — où lui-même ne la verrait
 * plus, puisque le périmètre de gare masque les dépenses sans gare. On borne donc :
 *
 *  - acteur rattaché à une gare : sa gare, point. Absente, on la pose ; différente, on REFUSE
 *    plutôt que de réécrire en silence — l'agent doit savoir que son choix n'a pas été retenu ;
 *  - administrateur, ou utilisateur central sans gare : libre, siège compris. Ce sont les deux
 *    profils qui voient déjà les dépenses du siège.
 */
class DepenseProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Depense $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $data
                ->setIdentreprise($entrepriseId)
                ->setCreatedBy($user->getId())
            ;
            $this->assertReferencesDeLEntreprise($data, $entrepriseId);
            $this->assertJustificatifAccrochable($data->getJustificatif(), null, $user);
            $this->imputer($data, $user);

            $this->activiteLogger->log(
                ActiviteLogger::DEPENSE_ENREGISTREE,
                $this->resume($data),
                'Depense'
            ); /*
                - Avant le flush, comme le veut 'ActiviteLogger' : c'est '->process()' qui écrit.
                  L'id de la dépense n'existe pas encore, la cible reste donc le seul type
            */

            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        if($operation instanceof Patch) {
            /*
                L'état AVANT modification, lu dans l'unité de travail : il sert à deux choses, refuser
                un déplacement d'imputation et n'auditer que ce qui compte.
            */
            $original = $this->em->getUnitOfWork()->getOriginalEntityData($data);
            $ancienneGareId = $original['gare_id'] ?? null;
            $ancienMontant = isset($original['montant']) ? (int) $original['montant'] : null;
            $ancienTypeId = $original['typedepense_id'] ?? null;

            $data->setUpdatedBy($user->getId());
            $this->assertReferencesDeLEntreprise($data, $entrepriseId);
            $this->assertJustificatifAccrochable($data->getJustificatif(), $original['justificatif_id'] ?? null, $user);

            $nouvelleGareId = $data->getGare()?->getId();
            if($nouvelleGareId !== $ancienneGareId && !$this->imputeLibrement($user)) {
                throw new AccessDeniedHttpException(
                    'Vous ne pouvez pas changer la gare d\'imputation d\'une dépense.'
                ); /*
                    - Sans cette garde, un agent corrigeait après coup l'imputation que la création
                      venait de lui refuser
                */
            }
            $this->imputer($data, $user);

            /*
                On ne journalise que le MONTANT, la GARE et le TYPE : ce sont les trois champs qui
                déplacent de l'argent d'un compte à un autre. Tracer une correction de libellé
                noierait le journal sous du bruit, et le rendrait inutile le jour où on le consulte.
            */
            $aChange = $ancienMontant !== $data->getMontant()
                || $ancienneGareId !== $data->getGare()?->getId()
                || $ancienTypeId !== $data->getTypedepense()?->getId();

            if($aChange) {
                $this->activiteLogger->log(
                    ActiviteLogger::DEPENSE_MODIFIEE,
                    sprintf('%s (montant précédent : %s)', $this->resume($data), number_format((float) $ancienMontant, 0, ',', ' ')),
                    'Depense',
                    $data->getId()
                );
            }
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Peut-on imputer où l'on veut, siège compris ? Administrateur, ou utilisateur central sans gare
     * — les deux profils auxquels le périmètre de lecture montre déjà les dépenses du siège.
     */
    private function imputeLibrement(User $user): bool
    {
        return $this->security->isGranted('ROLE_ADMIN')
            || $this->security->isGranted('ROLE_SUPER_ADMIN')
            || $user->getGare() === null;
    }

    private function imputer(Depense $data, User $user): void
    {
        if($this->imputeLibrement($user)) {
            return;
        }

        $gareAgent = $user->getGare();

        if($data->getGare() === null) {
            $data->setGare($gareAgent); // absente : on l'impute à la gare de celui qui saisit

            return;
        }

        if($data->getGare()->getId() !== $gareAgent->getId()) {
            throw new AccessDeniedHttpException(sprintf(
                'Vous ne pouvez enregistrer une dépense que pour votre gare (%s).',
                $gareAgent->getLibelle()
            ));
        }
    }

    /**
     * Toute référence entrante doit appartenir à l'entreprise de l'acteur.
     *
     * CEINTURE, pas bretelle : 'EntrepriseScopeExtension' s'applique déjà à la RÉSOLUTION d'un IRI,
     * si bien qu'une gare étrangère est introuvable avant même d'arriver ici (ApiPlatform répond
     * 400). Cette relecture couvre ce que la dénormalisation ne couvre pas — une référence posée par
     * du code interne, et le jour où le périmètre de lecture changerait. Elle coûte cinq 'findOneBy'
     * sur une opération d'écriture rare.
     */
    private function assertReferencesDeLEntreprise(Depense $data, int $entrepriseId): void
    {
        $references = [
            ['classe' => Typedepense::class, 'valeur' => $data->getTypedepense(), 'libelle' => 'Le type de dépense'],
            ['classe' => Gare::class,        'valeur' => $data->getGare(),        'libelle' => 'La gare'],
            ['classe' => Fournisseur::class, 'valeur' => $data->getFournisseur(), 'libelle' => 'Le fournisseur'],
            ['classe' => Voyage::class,      'valeur' => $data->getVoyage(),      'libelle' => 'Le voyage'],
            ['classe' => MediaObject::class, 'valeur' => $data->getJustificatif(), 'libelle' => 'Le justificatif'],
        ];

        foreach ($references as $reference) {
            if($reference['valeur'] === null) {
                continue;
            }

            $existe = $this->em->getRepository($reference['classe'])->findOneBy([
                'id' => $reference['valeur']->getId(),
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$existe) {
                throw new NotFoundHttpException($reference['libelle'] . ' est introuvable dans votre entreprise');
            }
        }
    }

    /**
     * Un justificatif NOUVELLEMENT accroché doit être un document PRIVÉ, téléversé PAR L'ACTEUR.
     *
     * Le périmètre d'entreprise ne suffit pas, et c'est tout l'objet de cette garde : la route de
     * téléchargement sert le justificatif à quiconque peut lire LA DÉPENSE. Sans elle, un agent de
     * Bouaké pouvait accrocher à SA dépense le média d'un bulletin de salaire saisi au siège — même
     * compagnie, donc IRI résolu — puis le télécharger par sa propre fiche. Exiger l'auteur ferme ce
     * détour sans rien coûter : le formulaire téléverse puis accroche dans le même geste, par la même
     * personne.
     *
     * Un justificatif INCHANGÉ passe toujours : l'administrateur qui corrige le montant de la dépense
     * d'un agent renvoie le justificatif existant, qu'il n'a pas téléversé.
     *
     * Et il doit être PRIVÉ : une image publique accrochée comme justificatif remettrait une pièce
     * comptable sous `public/`, lisible sans compte.
     */
    private function assertJustificatifAccrochable(?MediaObject $justificatif, mixed $ancienId, User $user): void
    {
        if($justificatif === null || ($ancienId !== null && (int) $ancienId === $justificatif->getId())) {
            return;
        }

        if(!$justificatif->isPrive()) {
            throw new UnprocessableEntityHttpException(
                'Le justificatif doit être téléversé comme document privé (prive=1) : une pièce comptable ne se range pas avec les images publiques.'
            );
        }

        if($justificatif->getCreatedBy() !== $user->getId()) {
            throw new AccessDeniedHttpException(
                'Ce justificatif n\'a pas été téléversé par vous : téléversez le fichier avant de l\'accrocher.'
            );
        }
    }

    /** Ce que le journal doit pouvoir relire dans six mois, borné à 255 caractères par le logger. */
    private function resume(Depense $data): string
    {
        return sprintf(
            '%s : %s FCFA (%s)%s',
            $data->getTypedepense()?->getLibelle() ?? 'Dépense',
            number_format((float) $data->getMontant(), 0, ',', ' '),
            $data->getGare()?->getLibelle() ?? 'siège',
            $data->getBeneficiaire() ? ' — ' . $data->getBeneficiaire() : ''
        );
    }
}
