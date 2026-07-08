<?php

namespace App\Command;

use App\Domain\Service\ReservationExpirationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Régularise le statut des réservations échues (à planifier en cron) :
 *  - impayées échues        → EXPIREE
 *  - payées échues (no-show) → A_REGULARISER (récupérables via report + pénalité)
 *  - fenêtre de régularisation dépassée → EXPIREE
 * La capacité ignore déjà les échues ; ceci ne fait que matérialiser les statuts.
 */
#[AsCommand(name: 'app:reservations:expirer', description: 'Traite les réservations échues (expiration / bascule en régularisation)')]
class ExpirerReservationsCommand extends Command
{
    public function __construct(private ReservationExpirationService $expirationService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $r = $this->expirationService->traiter();
        $output->writeln(sprintf(
            '%d impayée(s) expirée(s), %d payée(s) basculée(s) en régularisation, %d régularisation(s) échue(s) définitivement expirée(s).',
            $r['expirees'],
            $r['aRegulariser'],
            $r['expireesDefinitives']
        ));

        return Command::SUCCESS;
    }
}
