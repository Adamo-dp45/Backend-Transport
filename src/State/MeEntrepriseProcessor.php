<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Dto\EntrepriseInput;
use App\Entity\Entreprise;
use App\Entity\MediaObject;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Repository\MediaObjectRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeEntrepriseProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntrepriseRepository $entrepriseRepository,
        private MediaObjectRepository $mediaObjectRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var EntrepriseInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        /**
         * @var Entreprise
         */
        $entreprise = $this->entrepriseRepository->find($user->getEntreprise()->getId()); /*
            - On ne vérifie pas vu que le '->find()' retourne une exception en cas d'erreur sinon throw 'RuntimeException'
        */
        $entreprise
            ->setLibelle($data->libelle)
            ->setContact1($data->contact1)
            ->setContact2($data->contact2)
            ->setAdresse($data->adresse)
            ->setEmail($data->email)
            ->setAnneecreation($this->lireAnneeCreation($data->anneecreation, $entreprise->getAnneecreation()))
            ->setSigle($data->sigle)
            ->setSiteweb($data->siteweb)
            ->setRccm($data->rccm)
            ->setBanque($data->banque)
            ->setType($data->type)
            ->setCentreimpot($data->centreimpot)
            ->setTauxtva($data->tauxtva)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($user->getId())
        ;

        if($data->image) {
            /*
                !! BORNÉ À L'ENTREPRISE (28/09/2026). L'image arrive en IDENTIFIANT et non en IRI : elle
                échappe donc à la résolution d'API Platform, et à 'EntrepriseScopeExtension' avec elle.
                Un 'find()' nu laissait un administrateur désigner le média de N'IMPORTE QUELLE compagnie
                comme logo — puis en lire l'URL sur sa propre fiche ('read:Entreprise'). Même trou que
                la lecture directe d'un média, par une autre porte.
                Un document PRIVÉ est refusé aussi : il n'a pas d'URL, un logo sans image n'a pas de sens.
            */
            /** @var MediaObject|null $media */
            $media = $this->mediaObjectRepository->findOneBy([
                'id' => $data->image,
                'identreprise' => $entreprise->getId(),
                'deletedAt' => null,
            ]);
            if($media === null || $media->isPrive()) {
                throw new NotFoundHttpException('Cette image est introuvable dans votre entreprise.');
            }
            $entreprise->setImage($media);
        }

        return $this->processor->process($entreprise, $operation, $uriVariables, $context); /*
            - Pas de '->flush()' vu qu'on a le 'process'
        */
    }

    /**
     * Année de création saisie, en distinguant les trois cas.
     *
     * Il y avait ici un 'new \DateTimeImmutable($data->anneecreation)' nu : sur un champ VIDÉ il
     * enregistrait l'instant courant (et lève désormais, la chaîne étant nulle), et sur une saisie
     * approximative comme « 2019 » il complétait avec le mois et le jour du jour.
     *
     *  - champ VIDÉ            → null, l'utilisateur veut effacer la date ;
     *  - date LISIBLE          → la date saisie ;
     *  - saisie ILLISIBLE      → on conserve l'existant plutôt que d'écraser par une date inventée.
     */
    private function lireAnneeCreation(?string $saisie, ?\DateTimeImmutable $actuelle): ?\DateTimeImmutable
    {
        if ($saisie === null || trim($saisie) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($saisie));
        } catch (\Exception) {
            return $actuelle;
        }
    }
}
