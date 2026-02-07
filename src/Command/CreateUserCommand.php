<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-user',
    description: 'Neuen User (Elternteil oder Kind) anlegen',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $io->ask('Name des Users', null, static function (mixed $value): string {
            if (!is_string($value) || trim($value) === '') {
                throw new \RuntimeException('Der Name darf nicht leer sein.');
            }

            return trim($value);
        });

        /** @var string $pin */
        $pin = $io->askHidden('4-stelliger PIN', static function (mixed $value): string {
            if (!is_string($value) || !preg_match('/^\d{4}$/', $value)) {
                throw new \RuntimeException('Der PIN muss genau 4 Ziffern haben.');
            }

            return $value;
        });

        $isAdmin = $io->confirm('Soll der User Admin-Rechte haben?', false);

        $user = new User();
        $user->setName($name);
        $user->hashPin($pin);
        $user->setIsAdmin($isAdmin);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $role = $isAdmin ? 'Admin' : 'Kind';
        $io->success(sprintf('User "%s" (%s) wurde erfolgreich angelegt.', $name, $role));

        return Command::SUCCESS;
    }
}
