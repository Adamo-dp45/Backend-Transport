<?php

namespace App\Command;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Crée le compte SUPER ADMINISTRATEUR de la plateforme.
 *
 * POURQUOI UNE COMMANDE : depuis que l'inscription est réservée au super admin, plus aucun chemin
 * HTTP ne permet de créer le tout premier compte — la plateforme neuve serait inaccessible. Cette
 * commande est donc l'amorçage, et le seul endroit d'où un 'ROLE_SUPER_ADMIN' peut naître.
 *
 * SANS ENTREPRISE, volontairement : le super admin est un rôle de PLATEFORME, hors périmètre
 * entreprise. Plusieurs services en dépendent — la corbeille, notamment, ne doit jamais lire
 * l'entreprise de l'acteur. Lui en attacher une masquerait ce contrat et fausserait tous les
 * écrans qui distinguent « super admin » de « admin d'une compagnie ».
 *
 * Le mot de passe se saisit de préférence en INTERACTIF (il est alors masqué et n'apparaît ni à
 * l'écran, ni dans l'historique du shell, ni dans la liste des processus).
 */
#[AsCommand(
    name: 'app:creer-super-admin',
    description: 'Crée le compte super administrateur de la plateforme (amorçage)'
)]
final class CreerSuperAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Adresse e-mail de connexion')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prénom')
            ->addOption(
                'mot-de-passe',
                null,
                InputOption::VALUE_REQUIRED,
                'Mot de passe (à éviter : il reste dans l\'historique du shell — préférez la saisie interactive)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Créer un super administrateur supplémentaire alors qu\'il en existe déjà'
            )
            ->setHelp(<<<'AIDE'
                Crée le compte super administrateur, seul habilité à enregistrer les compagnies.

                Saisie interactive (recommandée, le mot de passe est masqué) :
                  <info>php %command.full_name%</info>

                Sans interaction (déploiement automatisé) :
                  <info>php %command.full_name% --email=admin@exemple.ci --nom=Kouassi --prenom=Jean --mot-de-passe='…'</info>
                AIDE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création du super administrateur');

        $existants = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        if ($existants > 0 && !$input->getOption('force')) {
            // Plusieurs super admins restent légitimes (redondance, passation), mais c'est assez
            // inhabituel pour exiger une intention explicite.
            $io->warning(sprintf('%d compte(s) super administrateur existe(nt) déjà.', $existants));

            if (!$input->isInteractive()) {
                // Sans terminal, une question retomberait sur « non » et la commande ne ferait
                // rien EN ANNONÇANT un succès : un script de déploiement croirait le compte créé.
                $io->error('Ajoutez --force pour en créer un de plus.');

                return Command::FAILURE;
            }

            if (!$io->confirm('En créer un de plus ?', false)) {
                $io->text('Aucun compte créé.');

                return Command::SUCCESS;
            }
        }

        $email = $this->demander($io, $input, 'email', 'Adresse e-mail');
        $nom = $this->demander($io, $input, 'nom', 'Nom');
        $prenom = $this->demander($io, $input, 'prenom', 'Prénom');
        $motDePasse = $this->demanderMotDePasse($io, $input);

        if ($email === null || $nom === null || $prenom === null || $motDePasse === null) {
            $io->error('Informations incomplètes : renseignez --email, --nom, --prenom et --mot-de-passe, ou lancez la commande en interactif.');

            return Command::INVALID;
        }

        $erreurs = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email()]);
        if (count($erreurs) > 0) {
            $io->error(sprintf('Adresse e-mail invalide : %s', $erreurs[0]->getMessage()));

            return Command::INVALID;
        }

        if (mb_strlen($motDePasse) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return Command::INVALID;
        }

        // La colonne 'email' porte un index unique : mieux vaut un message clair qu'une violation
        // de contrainte remontée par le driver.
        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse « %s ».', $email));

            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            // Ni entreprise, ni gare : c'est ce qui le place hors de tout périmètre.
            ->setEntreprise(null)
            ->setGare(null)
            ->setStatut(ReferenceStatus::ACTIF->value)
            ->setIsFounder(false);
        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Super administrateur « %s » créé.', $email));
        $io->text([
            'Il peut maintenant se connecter et enregistrer les compagnies',
            'depuis Administration › Entreprises › Nouvelle compagnie.',
        ]);

        return Command::SUCCESS;
    }

    /** Valeur de l'option, sinon question à l'écran si le terminal est interactif. */
    private function demander(SymfonyStyle $io, InputInterface $input, string $option, string $libelle): ?string
    {
        $valeur = $input->getOption($option);
        if (is_string($valeur) && trim($valeur) !== '') {
            return trim($valeur);
        }

        if (!$input->isInteractive()) {
            return null;
        }

        $reponse = $io->ask($libelle, null, static function (?string $saisie): string {
            if ($saisie === null || trim($saisie) === '') {
                throw new \RuntimeException('Cette information est obligatoire.');
            }

            return trim($saisie);
        });

        return is_string($reponse) ? $reponse : null;
    }

    private function demanderMotDePasse(SymfonyStyle $io, InputInterface $input): ?string
    {
        $valeur = $input->getOption('mot-de-passe');
        if (is_string($valeur) && $valeur !== '') {
            return $valeur;
        }

        if (!$input->isInteractive()) {
            return null;
        }

        // Saisie masquée, puis confirmation : une faute de frappe sur le compte d'amorçage
        // enfermerait dehors la seule personne capable d'ouvrir des comptes.
        $question = (new Question('Mot de passe'))->setHidden(true)->setHiddenFallback(false);
        $confirmation = (new Question('Confirmez le mot de passe'))->setHidden(true)->setHiddenFallback(false);

        $premier = $io->askQuestion($question);
        $second = $io->askQuestion($confirmation);

        if ($premier !== $second) {
            $io->error('Les deux saisies diffèrent.');

            return null;
        }

        return is_string($premier) && $premier !== '' ? $premier : null;
    }
}
