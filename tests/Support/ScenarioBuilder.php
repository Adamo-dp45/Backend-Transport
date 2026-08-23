<?php

namespace App\Tests\Support;

use App\Domain\Enum\BeneficiaireCategorie;
use App\Domain\Enum\CarStatus;
use App\Domain\Enum\ReferenceStatus;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Enum\TicketStatus;
use App\Entity\Arret;
use App\Entity\Bagage;
use App\Entity\Beneficiaire;
use App\Entity\Car;
use App\Entity\ConfigRemise;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\ParametreReservation;
use App\Entity\Passage;
use App\Entity\Permission;
use App\Entity\Reservation;
use App\Entity\Role;
use App\Entity\Siege;
use App\Entity\Tarif;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\UserRole;
use App\Entity\Ville;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Construit, pour UN test, le strict minimum dont il a besoin.
 *
 * Chaque test bâtit ses données plutôt que de s'appuyer sur le jeu de démo : il se lit seul, son
 * échec désigne la règle cassée et non un scénario partagé, et faire évoluer les fixtures ne fait
 * pas tomber des tests sans rapport.
 *
 * Les valeurs sans intérêt pour la règle testée (matricules, contacts, codes) sont générées ; seul
 * ce qui compte pour la règle est passé en argument.
 */
final class ScenarioBuilder
{
    private int $compteur = 0;

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function entreprise(string $libelle = 'Test Transport'): Entreprise
    {
        $suffixe = ++$this->compteur;

        $entreprise = (new Entreprise())
            ->setLibelle($libelle)
            ->setSigle('TST')
            ->setSlug('test-transport-' . $suffixe)
            ->setContact1('+225 00 00 00 00 ' . str_pad((string) $suffixe, 2, '0', STR_PAD_LEFT))
            ->setStatut(ReferenceStatus::ACTIF->value);

        $this->em->persist($entreprise);
        $this->em->flush();

        return $entreprise;
    }

    /**
     * Une ligne et ses arrêts ordonnés.
     *
     * @param list<string>   $gares  noms des gares, dans l'ORDRE de la ligne (1re = origine, dernière = terminus)
     * @param list<int|null> $durees durée du tronçon MENANT à chaque arrêt (null à l'origine) ; omise = pas d'horaire
     */
    public function reseau(array $gares, array $durees = [], ?Entreprise $entreprise = null): Reseau
    {
        if (count($gares) < 2) {
            throw new InvalidArgumentException('Une ligne compte au moins une origine et un terminus.');
        }

        $entreprise ??= $this->entreprise();
        $identreprise = (int) $entreprise->getId();

        $creees = [];
        $villes = [];
        foreach ($gares as $nom) {
            // Une ville par gare, du même nom : la production n'a pas de gare hors sol, et l'API
            // publique liste justement les gares PAR VILLE ('?ville='). Sans ce rattachement, ces
            // endpoints répondraient vide sans que rien ne soit cassé.
            $ville = (new Ville())->setNom($nom)->setIdentreprise($identreprise);
            $this->em->persist($ville);
            $villes[$nom] = $ville;

            $gare = (new Gare())
                ->setLibelle($nom)
                ->setVille($ville)
                ->setChefgare('Chef ' . $nom)
                ->setContact1('+225 27 00 00 00 00')
                ->setStatut(ReferenceStatus::ACTIF->value)
                ->setIdentreprise($identreprise);
            $this->em->persist($gare);
            $creees[$nom] = $gare;
        }

        $ligne = (new Ligne())
            ->setCodeligne('LI-TEST-' . ++$this->compteur)
            ->setLibelle($gares[0] . ' → ' . $gares[array_key_last($gares)])
            ->setGareorigine($creees[$gares[0]])
            ->setGareterminus($creees[$gares[array_key_last($gares)]])
            ->setIdentreprise($identreprise);

        foreach ($gares as $ordre => $nom) {
            $arret = (new Arret())
                ->setGare($creees[$nom])
                ->setOrdre($ordre)
                ->setDureeTronconMinutes($durees[$ordre] ?? null)
                ->setIdentreprise($identreprise);
            $ligne->addArret($arret);
        }

        $this->em->persist($ligne);
        $this->em->flush();

        return new Reseau($entreprise, $ligne, $creees, $villes);
    }

    /** Un car et ses sièges numérotés de 1 à $places. */
    public function car(Entreprise $entreprise, int $places): Car
    {
        $car = (new Car())
            ->setMatricule(sprintf('%04d TS 01', ++$this->compteur))
            ->setNbrsiege($places)
            ->setSiegesGauche(2)
            ->setSiegesDroite(2)
            ->setEtat(CarStatus::DISPONIBLE->value)
            ->setDatearrivee(new DateTimeImmutable('2024-01-01'))
            ->setIdentreprise((int) $entreprise->getId());
        $this->em->persist($car);

        for ($numero = 1; $numero <= $places; $numero++) {
            $siege = (new Siege())
                ->setNumero($numero)
                ->setRangee((int) ceil($numero / 4))
                ->setColonne((($numero - 1) % 4) + 1)
                ->setCote('GRILLE')
                ->setCar($car)
                ->setIdentreprise((int) $entreprise->getId());
            $this->em->persist($siege);
            $car->addSiege($siege);
        }

        $this->em->flush();

        return $car;
    }

    /**
     * Un départ sur la ligne du réseau.
     *
     * @param string|null $provenance gare de départ EFFECTIVE ; null = origine de la ligne.
     *                                La renseigner à une gare intermédiaire produit un DÉPART PARTIEL.
     */
    public function voyage(
        Reseau $reseau,
        ?Car $car = null,
        ?DateTimeImmutable $depart = null,
        ?string $provenance = null,
        ?int $placesprevues = null,
    ): Voyage {
        $origine = $provenance !== null ? $reseau->gare($provenance) : $reseau->ligne->getGareorigine();

        $voyage = (new Voyage())
            ->setCodevoyage($reseau->ligne->getCodeligne() . '-V' . ++$this->compteur)
            ->setLigne($reseau->ligne)
            ->setGareprovenance($origine)
            ->setGarecourante($origine)
            ->setProvenance((string) $origine->getLibelle())
            ->setDestination((string) $reseau->ligne->getGareterminus()->getLibelle())
            ->setDatedepartprevue($depart ?? new DateTimeImmutable('tomorrow 08:00'))
            ->setPlacesprevues($placesprevues)
            ->setIdentreprise($reseau->identreprise());

        if ($car !== null) {
            $voyage->setCar($car)->setPlacesTotal($car->getNbrsiege());
        }

        $this->em->persist($voyage);
        $this->em->flush();

        return $voyage;
    }

    /**
     * Un billet VALIDE sur un tronçon.
     *
     * @param string|null $descendu gare de descente RÉELLE (descente anticipée) : le siège se libère
     *                              en aval de celle-ci, pas de la descente vendue.
     */
    public function billet(
        Reseau $reseau,
        Voyage $voyage,
        int $siege,
        string $de,
        string $a,
        int $prix = 10000,
        ?string $descendu = null,
        TicketStatus $statut = TicketStatus::STATUT_VALIDE,
        ?User $commercial = null,
        ?DateTimeImmutable $vendu = null,
    ): Ticket {
        $ticket = (new Ticket())
            ->setVoyage($voyage)
            ->setSiege($this->siege($voyage, $siege))
            ->setGare($reseau->gare($de))
            ->setGaredescente($reseau->gare($a))
            ->setGaredescentereelle($descendu !== null ? $reseau->gare($descendu) : null)
            ->setPrix($prix)
            ->setRemise(0)
            ->setStatut($statut->value)
            ->setCodeticket('TCK-TEST-' . ++$this->compteur)
            ->setNomclient('Client ' . $this->compteur)
            ->setContactclient('+225 07 00 00 00 00')
            ->setCommercial($commercial)
            ->setIdentreprise($reseau->identreprise());

        $this->em->persist($ticket);
        $this->em->flush();

        if ($vendu !== null) {
            // 'EntityBase::onPrePersist()' écrase createdAt : les tests de recette par période
            // doivent donc antidater APRÈS l'insertion.
            $this->daterCreation($ticket, $vendu);
        }

        return $ticket;
    }

    /** Une réservation, dans l'état demandé. Sans siège : elle tient une PLACE, pas un siège. */
    public function reservation(
        Reseau $reseau,
        Voyage $voyage,
        string $de,
        string $a,
        ReservationStatus $statut = ReservationStatus::STATUT_EN_ATTENTE,
        int $prix = 10000,
        ?DateTimeImmutable $expiration = null,
        string $etatpaiement = 'EN_ATTENTE_PAIEMENT',
        ?DateTimeImmutable $datepaiement = null,
        ?DateTimeImmutable $creee = null,
    ): Reservation {
        $reservation = (new Reservation())
            ->setCode('RES-TEST-' . ++$this->compteur)
            ->setVoyage($voyage)
            ->setGare($reseau->gare($de))
            ->setGaredescente($reseau->gare($a))
            ->setPrix($prix)
            ->setStatut($statut->value)
            ->setEtatpaiement($etatpaiement)
            ->setDatepaiement($datepaiement)
            ->setDateexpiration($expiration ?? new DateTimeImmutable('+1 hour'))
            ->setSource('MOBILE')
            ->setNomclient('Client réservation ' . $this->compteur)
            ->setContactclient('+225 07 11 11 11 11')
            ->setIdentreprise($reseau->identreprise());

        $this->em->persist($reservation);
        $this->em->flush();

        if ($creee !== null) {
            // La limite de PAIEMENT se compte depuis la création : un test sur ce délai doit
            // pouvoir fixer cette date.
            $this->daterCreation($reservation, $creee);
        }

        return $reservation;
    }

    /**
     * Paramétrage de réservation de l'entreprise.
     *
     * 'ReservationConfigService' en crée un avec les défauts s'il n'en trouve pas : ne l'appeler que
     * lorsque le test porte justement sur les délais, et qu'il lui faut des valeurs choisies.
     */
    public function parametreReservation(
        Entreprise $entreprise,
        int $presentationMinutes = 15,
        int $paiementMinutes = 30,
        string $penaliteType = 'AUCUNE',
        int $penaliteValeur = 0,
        int $fenetreRegularisationJours = 7,
    ): ParametreReservation {
        $parametre = (new ParametreReservation())
            ->setDelaiPresentationMinutes($presentationMinutes)
            ->setDelaiPaiementMinutes($paiementMinutes)
            ->setPenaliteType($penaliteType)
            ->setPenaliteValeur($penaliteValeur)
            ->setFenetreRegularisationJours($fenetreRegularisationJours)
            ->setIdentreprise((int) $entreprise->getId());

        $this->em->persist($parametre);
        $this->em->flush();

        return $parametre;
    }

    /**
     * Horaires RÉELS du car à une gare.
     *
     * Le DÉPART est ce qui ferme définitivement une gare ('VoyageGuard::monteeDepassee') : la
     * position courante du voyage, elle, n'avance qu'à l'arrivée.
     */
    public function passage(
        Reseau $reseau,
        Voyage $voyage,
        string $gare,
        ?DateTimeImmutable $arrivee = null,
        ?DateTimeImmutable $depart = null,
    ): Passage {
        $passage = (new Passage())
            ->setVoyage($voyage)
            ->setGare($reseau->gare($gare))
            ->setArriveeReelle($arrivee)
            ->setDepartReelle($depart)
            ->setIdentreprise($reseau->identreprise());

        $this->em->persist($passage);
        $this->em->flush();

        return $passage;
    }

    /** Un bagage rattaché à un billet : il en suit le voyage, les gares et l'identité. */
    public function bagage(
        Reseau $reseau,
        Ticket $ticket,
        string $statut = 'ENREGISTRE',
        int $poids = 12,
        int $montant = 2500,
    ): Bagage {
        $bagage = (new Bagage())
            ->setCodebagage('BAG-TEST-' . ++$this->compteur)
            ->setNature('Valise')
            ->setPoids($poids)
            ->setMontant($montant)
            ->setMontantforce(false)
            ->setTicket($ticket)
            ->setVoyage($ticket->getVoyage())
            ->setGaredepart($ticket->getGare())
            ->setGaredescente($ticket->getGaredescente())
            ->setStatut($statut)
            ->setIdentreprise($reseau->identreprise());

        $this->em->persist($bagage);
        $this->em->flush();

        return $bagage;
    }

    /** Plafond de remise de la compagnie (null = aucun plafond). */
    public function configRemise(Entreprise $entreprise, ?int $maxPourcentage): ConfigRemise
    {
        $config = (new ConfigRemise())
            ->setMaxpourcentage($maxPourcentage)
            ->setIdentreprise((int) $entreprise->getId());

        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    /** Bénéficiaire d'une remise — exigé par le serveur dès qu'une remise manuelle est accordée. */
    public function beneficiaire(Entreprise $entreprise, string $nom = 'Étudiants'): Beneficiaire
    {
        $beneficiaire = (new Beneficiaire())
            ->setNom($nom . ' ' . ++$this->compteur)
            ->setCategorie(BeneficiaireCategorie::ETUDIANT->value)
            ->setIdentreprise((int) $entreprise->getId());

        $this->em->persist($beneficiaire);
        $this->em->flush();

        return $beneficiaire;
    }

    /** Prix aller ET retour, comme le fait 'TarifProcessor'. */
    public function tarif(Reseau $reseau, string $de, string $a, int $montant): void
    {
        foreach ([[$de, $a], [$a, $de]] as [$depart, $arrivee]) {
            $tarif = (new Tarif())
                ->setGaredepart($reseau->gare($depart))
                ->setGarearrivee($reseau->gare($arrivee))
                ->setMontant($montant)
                ->setIdentreprise($reseau->identreprise());
            $this->em->persist($tarif);
        }

        $this->em->flush();
    }

    /**
     * @param list<string> $roles rôles Symfony ('ROLE_ADMIN', 'ROLE_ADMIN_GARE'…) ; vide = agent simple
     */
    public function utilisateur(
        Entreprise $entreprise,
        ?Gare $gare = null,
        array $roles = [],
        ReferenceStatus $statut = ReferenceStatus::ACTIF,
        string $motDePasse = 'Password123!',
    ): User {
        $suffixe = ++$this->compteur;

        $user = (new User())
            ->setEmail(sprintf('user%d@test.local', $suffixe))
            ->setNom('Nom' . $suffixe)
            ->setPrenom('Prenom' . $suffixe)
            ->setRoles($roles)
            ->setEntreprise($entreprise)
            ->setGare($gare)
            ->setStatut($statut->value)
            ->setIsFounder(false)
            // Hachage volontairement direct : les tests d'intégration n'ont pas besoin du hasher
            // configuré, et un bcrypt à coût réel par utilisateur ralentirait toute la suite.
            ->setPassword(password_hash($motDePasse, PASSWORD_BCRYPT, ['cost' => 4]));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Dote un utilisateur d'un rôle RBAC portant les permissions demandées.
     *
     * Les tests d'écriture en ont besoin : les opérations sont gardées par `is_granted('CREER',
     * 'Ticket')` et consorts. Passer par un vrai `Role` + `Permission` plutôt que par `ROLE_ADMIN`
     * fait aussi traverser le `PermissionVoter` — c'est le chemin qu'emprunte un agent réel.
     *
     * @param array<string, list<string>> $permissions nom court d'entité => actions
     */
    public function autoriser(User $user, array $permissions): User
    {
        $identreprise = (int) $user->getEntreprise()?->getId();

        $role = (new Role())
            ->setName('Rôle de test ' . ++$this->compteur)
            ->setIdentreprise($identreprise);

        foreach ($permissions as $entite => $actions) {
            foreach ($actions as $action) {
                $role->addPermission(
                    (new Permission())->setEntity($entite)->setAction($action)->setIdentreprise($identreprise)
                );
            }
        }
        $this->em->persist($role);

        $user->addUserRole((new UserRole())->setRole($role)->setIdentreprise($identreprise));
        $this->em->flush();

        return $user;
    }

    public function siege(Voyage $voyage, int $numero): Siege
    {
        foreach ($voyage->getCar()?->getSieges() ?? [] as $siege) {
            if ($siege->getNumero() === $numero) {
                return $siege;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Le car du voyage n\'a pas de siège n°%d (a-t-il été créé avec assez de places ?).',
            $numero
        ));
    }

    /**
     * Antidate un enregistrement : 'onPrePersist' a écrasé sa date de création au flush.
     *
     * L'UPDATE en DQL court-circuite l'identity map, l'objet en mémoire garderait donc l'ancienne
     * date. On le rafraîchit dans la foulée : sans cela, tout test qui relit 'getCreatedAt()' après
     * antidatage obtiendrait l'heure du flush, et l'on serait tenté de vider l'EntityManager — ce
     * qui détacherait au passage tout le réseau construit pour le test.
     */
    public function daterCreation(object $entity, DateTimeImmutable $date): void
    {
        $this->em->createQuery(
            sprintf('UPDATE %s e SET e.createdAt = :date, e.updatedAt = :date WHERE e.id = :id', $entity::class)
        )
            ->setParameter('date', $date)
            ->setParameter('id', $entity->getId())
            ->execute();

        $this->em->refresh($entity);
    }
}
