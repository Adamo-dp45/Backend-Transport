<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\SlugService;
use App\Entity\Dto\RegisterInput;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegisterProcessor implements ProcessorInterface // services.yaml
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private ProcessorInterface $processor,
        private SlugService $slugService,
        // private JWTTokenManagerInterface $jwtManager
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var RegisterInput $data */
        /*
            - 'ApiPlatform' gère déjà la validation avec '#[Assert\..]'
        */
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $data->email]);
        if ($existingUser) {
            throw new ConflictHttpException('Cet email est déjà utilisé'); /* - On laisse 'ApiPlatform' gérer la réponse 'HTTP'
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Cet email est déjà utilisé'
                ], Response::HTTP_CONFLICT);
            */
        }

        $this->em->wrapInTransaction(function () use ($data, &$user) // Au lieu de '->beginTransaction()'
        {
            $entreprise = new Entreprise()
                ->setLibelle($data->libelle)
                ->setSlug($this->slugService->genererPourEntreprise($data->libelle))
                ->setContact1($data->contact1)
                ->setContact2($data->contact2)
                ->setAdresse($data->adresse)
                ->setEmail($data->emailEntreprise)
                ->setAnneecreation(new \DateTimeImmutable($data->anneecreation))
                ->setSigle($data->sigle)
                ->setRccm($data->rccm)
                ->setBanque($data->banque)
                ->setType($data->type)
                ->setCentreimpot($data->centreimpot)
                ->setTauxtva($data->tauxtva);
            $this->em->persist($entreprise);

            $user = new User()
                ->setNom($data->nom)
                ->setPrenom($data->prenom)
                ->setEmail($data->email)
                ->setEntreprise($entreprise)
                ->setRoles(['ROLE_ADMIN'])
                ->setIsFounder(true)
            ;
            $hashedPassword = $this->hasher->hashPassword(
                $user,
                $data->password
            );
            $user->setPassword($hashedPassword);
            $this->em->persist($user); /*
                - Le 'persist' vu qu'on a une nouvelle entité qui n'est pas attachée via cascade
                - Si on n'avait accès à l'id du dernier enregistrement on aurait pu 'setCreatedBy'..
            */
        });
        /*  - Si on veut le connecter directement
                $token = $this->jwtManager->create($user);
                return new RegisterOutput($token, $user);
        */
        return $this->processor->process($user, $operation, $uriVariables, $context);
    }
}