<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Dto\ChangerEmailInput;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Domain\Service\ActiviteLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * LE SUPER ADMIN CHANGE L'E-MAIL D'UN UTILISATEUR — et rien d'autre.
 *
 * POURQUOI CETTE PORTE EXISTE. Depuis le 06/10/2026, l'e-mail n'est plus modifiable en self-service
 * (cf. `UserProfileProcessor`) : c'est l'identifiant de connexion et le canal de réinitialisation du
 * mot de passe. L'admin d'une compagnie peut le changer pour ses agents via `PATCH /users/{id}` —
 * mais personne ne pouvait changer celui de l'ADMIN lui-même, qui se retrouvait enfermé : ni son
 * profil, ni sa propre gestion d'utilisateurs (où il peut se voir mais où la règle le vise aussi).
 *
 * !! PORTE ÉTROITE, PAS OUVERTURE DU `PATCH` GÉNÉRAL. Ce dernier est fermé au super admin
 * délibérément — sa garde dit « c'est à l'admin de modifier son utilisateur, à cause des
 * `userRoles` ». L'élargir pour l'e-mail lui donnerait du même coup les rôles, l'entreprise et la
 * gare, c'est-à-dire précisément ce que cette exclusion protège.
 *
 * !! L'UNICITÉ EST VÉRIFIÉE ICI, pas laissée à la base. La colonne porte `UNIQ_IDENTIFIER_EMAIL` :
 * sans ce contrôle, prendre une adresse déjà utilisée rendrait une erreur SQL en 500, illisible.
 * Un changement d'identifiant de connexion mérite de dire pourquoi il est refusé.
 */
class ChangerEmailProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof ChangerEmailInput) {
            throw new BadRequestHttpException('Corps de requête invalide.');
        }

        $cible = $this->userRepository->find((int) ($uriVariables['id'] ?? 0));
        if (!$cible instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        $nouvelle = mb_strtolower(trim($data->email));
        $ancienne = (string) $cible->getEmail();

        if ($nouvelle === mb_strtolower($ancienne)) {
            return $cible; // rien à faire : on ne trace pas un non-événement
        }

        $occupee = $this->userRepository->findOneBy(['email' => $nouvelle]);
        if ($occupee instanceof User && $occupee->getId() !== $cible->getId()) {
            throw new BadRequestHttpException('Cette adresse e-mail est déjà utilisée par un autre compte.');
        }

        $cible->setEmail($nouvelle);

        /*
            TRACÉ, parce qu'un changement d'identifiant de connexion déplace le pouvoir de reprendre
            un compte : qui reçoit les liens de réinitialisation peut y entrer. L'ancienne adresse
            est consignée — sans elle, on constate le changement sans pouvoir revenir en arrière.

            !! LA TRACE VA AU JOURNAL DE LA CIBLE, pas de l'acteur, et ce n'est pas un détail :
            'ActiviteLogger' se scope sur l'entreprise de l'acteur, or LE SUPER ADMIN N'EN A AUCUNE
            (vérifié en base). Sans l'entreprise passée explicitement, 'log()' sortait en silence et
            le geste le plus sensible du module ne laissait AUCUNE trace — pendant que ce commentaire
            affirmait le contraire. C'est d'ailleurs le bon journal : celui que lit l'admin dont
            l'agent vient de changer d'adresse.

            Le 'log()' PRÉCÈDE le flush : il ne fait que 'persist()', c'est le flush de l'appelant
            qui écrit la ligne. Flusher d'abord l'aurait laissée en mémoire, jamais enregistrée.
        */
        $this->activiteLogger->log(
            ActiviteLogger::USER_EMAIL_MODIFIE,
            sprintf('E-mail de %s modifié : %s → %s', trim($cible->getPrenom() . ' ' . $cible->getNom()), $ancienne, $nouvelle),
            'User',
            $cible->getId(),
            $cible->getEntreprise()?->getId()
        );

        $this->em->flush();

        return $cible;
    }
}
