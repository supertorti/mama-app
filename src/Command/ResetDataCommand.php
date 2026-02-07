<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-data',
    description: 'Alle Aufgaben löschen und Punktestand bei allen Usern auf 0 setzen',
)]
class ResetDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$io->confirm('Alle Aufgaben und Punkte werden unwiderruflich gelöscht. Fortfahren?', false)) {
            $io->warning('Abgebrochen.');

            return Command::SUCCESS;
        }

        $connection = $this->entityManager->getConnection();

        $deletedTransactions = $connection->executeStatement('DELETE FROM point_transactions');
        $io->info(sprintf('%d Punkt-Transaktionen gelöscht.', $deletedTransactions));

        $deletedTasks = $connection->executeStatement('DELETE FROM tasks');
        $io->info(sprintf('%d Aufgaben gelöscht.', $deletedTasks));

        $updatedUsers = $connection->executeStatement('UPDATE users SET points = 0');
        $io->info(sprintf('Punktestand bei %d Usern auf 0 gesetzt.', $updatedUsers));

        $io->success('Alle Aufgaben gelöscht und Punktestand zurückgesetzt.');

        return Command::SUCCESS;
    }
}
