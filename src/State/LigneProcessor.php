<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Arret;
use App\Entity\Dto\LigneInput;
use App\Entity\Ligne;
use App\Entity\User;
use App\Repository\GareRepository;
use App\Repository\LigneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LigneProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private GareRepository $gareRepository,
        private LigneRepository $ligneRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var LigneInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $ligne = new Ligne();
            $ligne
                ->setIdentreprise($entrepriseId)
                ->setCreatedBy($user->getId());
        } elseif($operation instanceof Patch) {
            /** @var Ligne $ligne */
            $ligne = $this->ligneRepository->findOneBy([
                'id' => $uriVariables['id'],
                'identreprise' => $entrepriseId,
                'deletedAt' => null,
            ]);
            if (!$ligne) {
                throw new NotFoundHttpException('Ligne introuvable');
            }
            $ligne->setUpdatedBy($user->getId());

            // Hard delete des arrêts existants puis recréation (config, pas d'historique)
            foreach ($ligne->getArrets()->toArray() as $arret) {
                $this->em->remove($arret);
            }
            $ligne->getArrets()->clear();
            /*
                - On flush ICI pour exécuter les DELETE avant les INSERT des nouveaux arrêts.
                  Sinon Doctrine ordonne les INSERT avant les DELETE dans un même flush et un nouvel arrêt
                  réutilisant le même (ligne, gare) viole la contrainte UNIQ_arret_ligne_gare ("Duplicate entry '2-1'").
            */
            $this->em->flush();
        } else {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        $ligne->setLibelle($data->libelle);
        $ligne->setHeuredepart($this->parseHeure($data->heuredepart));

        $this->handleArrets($ligne, $data->arrets, $entrepriseId);

        $ligne->setCodeligne($this->generateCodeligne($entrepriseId, $ligne->getGareorigine()->getVille()?->getNom() ?? '', $ligne->getGareterminus()->getVille()?->getNom() ?? ''));

        return $this->processor->process($ligne, $operation, $uriVariables, $context);
    }

    /**
     * Résout et crée les arrêts, valide l'ordre, fixe origine/terminus.
     * @return array<int, int> map gareId => ordre
     */
    private function handleArrets(Ligne $ligne, array $arrets, int $entrepriseId): array
    {
        if (count($arrets) < 2) {
            throw new BadRequestHttpException('Une ligne doit avoir au moins 2 arrêts');
        }

        // Tri par ordre croissant
        usort($arrets, fn($a, $b) => ($a['ordre'] ?? 0) <=> ($b['ordre'] ?? 0));

        $ordreParGare = [];
        $ordresVus = [];
        $position = 0;
        $gareOrigine = null;
        $gareTerminus = null;

        foreach ($arrets as $arretInput) {
            $gareId = (int) ($arretInput['gare'] ?? 0);
            $ordre = (int) ($arretInput['ordre'] ?? -1);

            if (isset($ordreParGare[$gareId])) {
                throw new BadRequestHttpException('Une gare est en doublon dans les arrêts de la ligne');
            }
            if (in_array($ordre, $ordresVus, true)) {
                throw new BadRequestHttpException('Deux arrêts ont le même ordre');
            }
            if ($ordre !== $position) {
                throw new BadRequestHttpException('Les ordres des arrêts doivent être contigus à partir de 0 (0, 1, 2, ...)');
            }

            $gare = $this->gareRepository->findOneBy([
                'id' => $gareId,
                'identreprise' => $entrepriseId,
                'deletedAt' => null,
            ]);
            if (!$gare) {
                throw new NotFoundHttpException(sprintf('Gare invalide (id %d) dans les arrêts', $gareId));
            }

            $arret = new Arret();
            $arret
                ->setGare($gare)
                ->setOrdre($ordre)
                ->setIdentreprise($entrepriseId);
            $ligne->addArret($arret);

            $ordreParGare[$gareId] = $ordre;
            $ordresVus[] = $ordre;
            if ($position === 0) {
                $gareOrigine = $gare;
            }
            $gareTerminus = $gare; // le dernier itéré = terminus
            $position++;
        }

        $ligne->setGareorigine($gareOrigine);
        $ligne->setGareterminus($gareTerminus);

        return $ordreParGare;
    }

    private function parseHeure(?string $heure): ?\DateTimeImmutable
    {
        if (empty($heure)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('H:i', $heure);
        return $dt === false ? null : $dt;
    }

    private function generateCodeligne(int $entrepriseId, string $origine, string $terminus): string
    {
        $count = $this->em->getRepository(Ligne::class)->count([
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        return sprintf('LI-%s-%s-%04d', $this->slugify($origine), $this->slugify($terminus), $count + 1);
    }

    private function slugify(string $value): string
    {
        $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Upper()', $value);
        $mots = preg_split('/[\s\-]+/', trim($value));
        if(count($mots) > 1) {
            $code = '';
            foreach($mots as $mot) {
                $code .= substr($mot, 0, 1);
            }
            return str_pad(substr($code, 0, 3), 3, 'X');
        }
        return str_pad(substr(preg_replace('/[^A-Z0-9]/', '', $value), 0, 3), 3, 'X');
    }
}
