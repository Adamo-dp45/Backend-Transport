<?php

namespace App\Command;

use App\Domain\Service\AlerteGenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Balaie l'état courant de l'application et réconcilie les alertes du centre de notifications
 * (à planifier en cron, fréquence recommandée 5–10 min). Idempotent : relancer ne crée pas de
 * doublon (cf. AlerteGenerationService).
 */
#[AsCommand(name: 'app:alertes:generer', description: 'Génère / résout les alertes (balayage de l\'état courant)')]
class GenererAlertesCommand extends Command
{
    public function __construct(private AlerteGenerationService $generation)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $r = $this->generation->generer();
        $output->writeln(sprintf(
            '%d entreprise(s) balayée(s) : %d alerte(s) créée(s), %d résolue(s).',
            $r['entreprises'],
            $r['creees'],
            $r['resolues']
        ));

        return Command::SUCCESS;
    }
}
