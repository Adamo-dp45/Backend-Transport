<?php

namespace App\Tests\Domain;

use App\Repository\CourrierRepository;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * La recette d'un courrier, c'est le transport PLUS le frais de suivi SMS.
 *
 * Ces deux sommes se paient au guichet en même temps et s'impriment sur le même reçu, en deux
 * lignes. Le frais de suivi n'entrait pourtant dans AUCUN total : la recette courrier était
 * incomplète, en moins, sur tous les écrans financiers. Le sens de l'erreur explique qu'elle ait
 * duré — un chiffre trop BAS n'alarme personne.
 *
 * Le piège du correctif est l'inverse du bug : 'fraissuivi' est nullable, et 'montant + NULL' vaut
 * NULL en SQL. Une addition écrite sans 'COALESCE' ferait donc disparaître de la somme tous les
 * courriers SANS frais de suivi — soit l'immense majorité. C'est ce que garde le deuxième test.
 */
final class RecetteCourrierTest extends IntegrationTestCase
{
    private CourrierRepository $courriers;

    private Reseau $reseau;

    private \DateTimeImmutable $debut;

    private \DateTimeImmutable $fin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->courriers = $this->service(CourrierRepository::class);
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->debut = new \DateTimeImmutable('-1 day');
        $this->fin = new \DateTimeImmutable('+1 day');
    }

    private function recetteTotale(): float
    {
        return $this->courriers->recettesTotales($this->debut, $this->fin, $this->reseau->identreprise());
    }

    #[Test]
    #[TestDox('Le frais de suivi entre dans la recette du courrier')]
    public function fraisDeSuiviCompte(): void
    {
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 5000, fraissuivi: 500);

        self::assertSame(5500.0, $this->recetteTotale());
    }

    #[Test]
    #[TestDox('Un courrier SANS frais de suivi compte quand même son transport')]
    public function fraisDeSuiviAbsentNAnnulePasLaLigne(): void
    {
        // La sentinelle du COALESCE : sans lui, 'montant + NULL' vaudrait NULL et cette recette
        // tomberait à zéro. Un correctif naïf serait donc PIRE que le bug qu'il répare.
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 3000, fraissuivi: null);

        self::assertSame(3000.0, $this->recetteTotale());
    }

    #[Test]
    #[TestDox('Les deux cas se mélangent sans se perdre')]
    public function melangeDesDeuxCas(): void
    {
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 5000, fraissuivi: 500);
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 3000, fraissuivi: null);
        $this->scenario->courrier($this->reseau, 'Bouaké', 'Abidjan', montant: 2000, fraissuivi: 1000);

        self::assertSame(11500.0, $this->recetteTotale());
    }

    #[Test]
    #[TestDox('Un courrier annulé ne laisse ni son transport ni son frais de suivi')]
    public function courrierAnnuleNeCompteRien(): void
    {
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 5000, fraissuivi: 500);
        $this->scenario->courrier(
            $this->reseau, 'Abidjan', 'Bouaké',
            montant: 9000, fraissuivi: 2000, statut: 'ANNULE'
        );

        self::assertSame(5500.0, $this->recetteTotale());
    }

    #[Test]
    #[TestDox('La recette par gare de dépôt compte aussi le frais de suivi')]
    public function recetteParGareCompteLeFraisDeSuivi(): void
    {
        /*
            Les totaux par gare, par agent, par jour, par trajet et par ligne sont huit requêtes
            distinctes. Celle-ci en tient une au hasard : ce qu'on protège, c'est qu'une seule d'entre
            elles ne reste pas sur l'ancienne formule — le chiffre d'un tableau de bord de gare ne
            collerait alors plus avec le total de l'entreprise.
        */
        $this->scenario->courrier($this->reseau, 'Abidjan', 'Bouaké', montant: 5000, fraissuivi: 500);
        $this->scenario->courrier($this->reseau, 'Bouaké', 'Abidjan', montant: 2000, fraissuivi: null);

        $parGare = [];
        foreach($this->courriers->recetteParGare($this->debut, $this->fin, $this->reseau->identreprise()) as $ligne) {
            $parGare[$ligne['garelibelle']] = (int) $ligne['recette'];
        }

        self::assertSame(5500, $parGare[$this->reseau->gare('Abidjan')->getLibelle()]);
        self::assertSame(2000, $parGare[$this->reseau->gare('Bouaké')->getLibelle()]);
    }
}
