<?php

namespace App\Command;

use App\Repository\ApiLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ApiLogListener écrit une ligne par requête /api/* et rien ne la purge : sans cette
 * commande (à lancer périodiquement, ex. cron), la table api_log grossit indéfiniment.
 */
#[AsCommand(name: 'app:logs:prune', description: 'Supprime les entrées api_log plus anciennes que N jours')]
class PruneApiLogsCommand extends Command
{
    public function __construct(
        private readonly ApiLogRepository $apiLogRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Ancienneté (en jours) au-delà de laquelle purger', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        if ($days < 1) {
            $io->error('Le nombre de jours doit être supérieur à 0.');

            return Command::FAILURE;
        }

        $deleted = $this->apiLogRepo->deleteOlderThan($days);
        $io->success("{$deleted} entrée(s) api_log de plus de {$days} jour(s) supprimée(s).");

        return Command::SUCCESS;
    }
}
