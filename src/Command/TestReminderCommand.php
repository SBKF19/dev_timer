<?php

namespace App\Command;

use App\Service\ActivityReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-reminder',
    description: 'Envoie manuellement les mails de rappel pour tester le service.',
)]
class TestReminderCommand extends Command
{
    public function __construct(
        private ActivityReminderService $reminderService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->note('Lancement de l\'analyse des jours manquants...');

        // Appel de ton service
        $this->reminderService->sendReminders();

        $io->success('Le script a terminé son exécution. Vérifiez votre boîte mail (ou le profiler Symfony) !');

        return Command::SUCCESS;
    }
}