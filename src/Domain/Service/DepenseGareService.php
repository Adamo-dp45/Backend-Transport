<?php

namespace App\Domain\Service;

use App\Repository\DepenseRepository;

/**
 * Calcul CENTRALISÉ des DÉPENSES par gare, le symétrique de 'RecetteGareService'.
 *
 * Même raison d'être : trois surfaces posent la même question (tableau de bord d'une gare, pilotage
 * inter-gares, écran d'analyse des dépenses), et trois requêtes écrites séparément finiraient par
 * donner trois chiffres différents.
 *
 * !! CE QUE CE SERVICE NE PEUT PAS COMPTER. Un 'Depannage' et un 'Approvisionnement' n'ont AUCUNE
 * relation vers une gare : ces coûts-là sont portés par l'entreprise, jamais imputés à un point de
 * vente. Le résultat d'une gare est donc « ses recettes moins les dépenses qu'on lui a imputées »,
 * et non un bénéfice complet. C'est une limite du modèle, pas un oubli : elle se dit à l'écran
 * ('perimetre' dans la charge du tableau de bord) plutôt que de se cacher derrière un chiffre rond.
 */
class DepenseGareService
{
    public function __construct(
        private DepenseRepository $depenseRepository
    )
    {
    }

    /**
     * Dépenses de chaque gare sur la période, plus celles du SIÈGE à part.
     *
     * @return array{gares: array<int, array{gareId: int, libelle: string, montant: int, nb: int}>, siege: array{montant: int, nb: int}, total: int}
     */
    public function parGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        $gares = [];
        $total = 0;

        foreach ($this->depenseRepository->totalParGare($debut, $fin, $identreprise) as $r) {
            $gareId = (int) $r['gareid'];
            $montant = (int) $r['montant'];
            $gares[$gareId] = [
                'gareId' => $gareId,
                'libelle' => $r['garelibelle'] ?? '—',
                'montant' => $montant,
                'nb' => (int) $r['nbdepenses'],
            ];
            $total += $montant;
        }

        /*
            Le siège est tenu À PART et non fondu dans le total des gares : ses charges (loyer,
            salaires de la direction) ne sont imputables à aucun guichet, et les mélanger ferait
            porter à une gare une dépense qu'elle n'a pas engagée.
        */
        $siege = $this->depenseRepository->totalSiege($debut, $fin, $identreprise);

        return [
            'gares' => $gares,
            'siege' => $siege,
            'total' => $total + $siege['montant'],
        ];
    }

    /**
     * Ce qu'UNE gare a dépensé, avec sa ventilation par poste — la vue du chef de gare.
     *
     * @return array{montant: int, nb: int, parType: list<array{libelle: string, montant: int, nb: int}>}
     */
    public function pourGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise, int $gareId): array
    {
        $parType = [];
        $montant = 0;
        $nb = 0;

        foreach ($this->depenseRepository->totalParType($debut, $fin, $identreprise, gareId: $gareId) as $r) {
            $ligne = [
                'libelle' => $r['typelibelle'] ?? '—',
                'montant' => (int) $r['montant'],
                'nb' => (int) $r['nbdepenses'],
            ];
            $parType[] = $ligne;
            $montant += $ligne['montant'];
            $nb += $ligne['nb'];
        }

        return ['montant' => $montant, 'nb' => $nb, 'parType' => $parType];
    }
}
