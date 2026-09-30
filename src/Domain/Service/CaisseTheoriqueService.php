<?php

namespace App\Domain\Service;

use App\Entity\Sessioncaisse;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\ReservationRepository;
use App\Repository\TicketRepository;

/**
 * CE QUE LE TIROIR DEVRAIT CONTENIR — la moitié du rapprochement, l'autre étant ce que l'agent compte.
 *
 * SOURCE UNIQUE du théorique, sur le modèle de 'RecetteGareService' : le service compose, les
 * repositories portent les requêtes. Un second calcul ailleurs — un aperçu d'écran, un export —
 * finirait par diverger, et deux théoriques pour une même caisse ne se départagent pas.
 *
 * !! LE THÉORIQUE N'EST PAS UNE RECETTE. La recette dit ce qui a été VENDU et exclut ce qui est
 * annulé ; le théorique dit ce qui est PASSÉ PAR LE TIROIR et n'exclut rien de tel. Les deux
 * chiffres diffèrent légitimement sur une même journée, et vouloir les rapprocher casserait l'un
 * des deux. La caisse ne s'ajoute d'ailleurs à aucun total existant : elle RAPPROCHE, elle ne
 * compte pas — ne jamais soustraire un écart du bénéfice.
 *
 * LES HUIT POSTES SONT BRANCHÉS. Sept entrent, un sort — le remboursement d'un désistement, seul
 * geste qui vide un tiroir. Dépenses et versements n'y sont pas : ils sortent du coffre du chef de
 * gare, jamais du guichet d'un agent.
 */
class CaisseTheoriqueService
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private BagageRepository $bagageRepository,
        private CourrierRepository $courrierRepository,
        private ReservationRepository $reservationRepository
    )
    {
    }

    /**
     * Les huit postes et leur total, sans rien écrire.
     *
     * !! NE JAMAIS SERVIR CE CALCUL À L'AGENT AVANT QU'IL AIT COMPTÉ. Le comptage est AVEUGLE, et
     * c'est ce qui lui donne sa valeur : à qui connaît le théorique, il suffit de le recopier pour
     * n'avoir jamais d'écart, et le contrôle ne mesure plus rien. L'agent saisit ce qu'il a dans le
     * tiroir, le serveur lui répond l'écart.
     *
     * (Ce docbloc a d'abord dit l'inverse — « annoncer le théorique avant que l'agent ne compte » —
     * et c'était une faute : c'est la pratique comptable exactement retournée.)
     *
     * Séparé de 'geler()' pour que la clôture puisse calculer sans écrire tant que ses gardes n'ont
     * pas toutes répondu, et pour qu'un écran de CONTRÔLE — celui d'un chef de gare, pas celui du
     * titulaire — puisse lire un attendu sans figer quoi que ce soit.
     *
     * @return array{totalbillets: int, totalbagages: int, totalcourriers: int, totalfraissuivi: int, totalreservations: int, totalpenalites: int, totalcomplements: int, totalremboursements: int, montanttheorique: int}
     */
    public function composer(Sessioncaisse $session): array
    {
        $id = (int) $session->getId();
        $courriers = $this->courrierRepository->totauxPourSession($id);
        $regularisations = $this->reservationRepository->totauxRegulPourSession($id);

        $postes = [
            'totalbillets' => $this->ticketRepository->totalPourSession($id),
            'totalbagages' => $this->bagageRepository->totalPourSession($id),
            'totalcourriers' => $courriers['montant'],
            'totalfraissuivi' => $courriers['fraissuivi'],
            'totalreservations' => $this->reservationRepository->totalPourSession($id),
            'totalpenalites' => $regularisations['penalites'],
            'totalcomplements' => $regularisations['complements'],
            /*
                LE REMBOURSEMENT COURT SUR TROIS ENTITÉS : le billet désisté, ses bagages annulés en
                cascade, et le courrier repris avant le départ. N'en compter qu'une partie
                fabriquerait à l'agent un manquant du montant du reste — au comptoir il rend tout en
                une fois. Toute entité qui se mettra un jour à rembourser devra s'ajouter ICI.
            */
            'totalremboursements' => $this->ticketRepository->totalRembourseePourSession($id)
                + $this->bagageRepository->totalRembourseePourSession($id)
                + $this->courrierRepository->totalRembourseePourSession($id),
        ];

        /*
            LE FONDS D'OUVERTURE EST DANS LE TOTAL : c'est la monnaie que le chef de gare a avancée,
            elle est PHYSIQUEMENT dans le tiroir au moment du comptage. L'oublier ferait apparaître
            un excédent égal au fonds sur chaque caisse qui en a reçu un.

            Et le REMBOURSEMENT se SOUSTRAIT : c'est le seul poste en sortie d'une caisse. Dépenses
            et versements n'y sont pas — ils sortent du coffre du chef de gare, pas du tiroir.
        */
        $postes['montanttheorique'] = $session->getFondsouverture()
            + $postes['totalbillets']
            + $postes['totalbagages']
            + $postes['totalcourriers']
            + $postes['totalfraissuivi']
            + $postes['totalreservations']
            + $postes['totalpenalites']
            + $postes['totalcomplements']
            - $postes['totalremboursements'];

        return $postes;
    }

    /**
     * Fige les postes sur la session : la clôture est une PIÈCE OPPOSABLE.
     *
     * !! C'EST ICI QUE LA DOCTRINE MAISON EST MISE DE CÔTÉ, en connaissance de cause. « Rien de
     * dérivable n'est stocké » vaut partout ailleurs ; ici, un tarif corrigé ou une annulation
     * tardive déplaceraient un théorique recalculé, et l'écart que l'agent a signé ce soir-là ne
     * voudrait plus rien dire demain. Même exception assumée que 'Ticket::$desistementImputableCompagnie'.
     */
    public function geler(Sessioncaisse $session): void
    {
        $postes = $this->composer($session);

        $session
            ->setTotalbillets($postes['totalbillets'])
            ->setTotalbagages($postes['totalbagages'])
            ->setTotalcourriers($postes['totalcourriers'])
            ->setTotalfraissuivi($postes['totalfraissuivi'])
            ->setTotalreservations($postes['totalreservations'])
            ->setTotalpenalites($postes['totalpenalites'])
            ->setTotalcomplements($postes['totalcomplements'])
            ->setTotalremboursements($postes['totalremboursements'])
            ->setMontanttheorique($postes['montanttheorique']);
    }
}
