<?php
namespace App\Command;

use App\Repository\ReminderRepository;
use App\Service\ActivityReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:send-reminders', description: 'Envoie les rappels hebdomadaires')]
class SendActivityReminderCommand extends Command
{
    public function __construct(
        private ReminderRepository $reminderRepository,
        private ActivityReminderService $reminderService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        date_default_timezone_set('Europe/Paris');
        $reminder = $this->reminderRepository->findOneBy(['is_active' => true]);
        $now = new \DateTime();

        $output->writeln(sprintf(
            "Check à %s. Cible : Jour %s à %s",
            $now->format('H:i'),
            $reminder->getSendDay(),
            $reminder->getSendTime()->format('H:i')
        ));

        // 1. Récupérer le rappel actif
        $reminder = $this->reminderRepository->findOneBy(['is_active' => true]);

        if (!$reminder) {
            $output->writeln('Aucun rappel actif configuré.');
            return Command::SUCCESS;
        }

        // 2. Vérifier si on est le bon jour et la bonne heure
        $now = new \DateTime();
        $currentDay = (int) $now->format('N'); // 1 (lundi) à 7 (dimanche)
        $currentTime = $now->format('H:i');
        $targetTime = $reminder->getSendTime()->format('H:i');

        if ($currentDay === $reminder->getSendDay() && $currentTime === $targetTime) {
            $output->writeln('Lancement de l\'envoi des rappels...');
            $this->reminderService->sendReminders();
            $output->writeln('Rappels envoyés avec succès.');

            $output->writeln('Mise en pause de sécurité pour éviter les doublons...');
            sleep(65);
        } else {
            $output->writeln('Ce n\'est pas encore l\'heure prévue.');
        }

        $now = new \DateTime();
        $output->writeln('Heure système PHP : ' . $now->format('Y-m-d H:i:s'));
        $output->writeln('Heure cible BDD : ' . $targetTime);

        return Command::SUCCESS;
    }
}