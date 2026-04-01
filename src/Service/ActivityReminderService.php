<?php

// src/Service/ActivityReminderService.php

namespace App\Service;

use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class ActivityReminderService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private TimeStatsService $timeStatsService
    ) {
    }

    public function sendReminders(): void
    {
        // On récupère les utilisateurs actifs
        $users = $this->userRepository->findBy(['status' => 1]);

        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->modify('-7 days');

        foreach ($users as $user) {
            // On utilise le nouveau service pour obtenir le détail des manques
            $missingDays = $this->timeStatsService->getMissingDaysDetail($user, $startDate, $endDate);

            if (count($missingDays) > 0) {
                $email = (new TemplatedEmail())
                    ->from('noreply@devtimer.fr')
                    ->to($user->getEmail())
                    ->subject('🔔 Rappel : Saisie d’activités incomplète')
                    ->htmlTemplate('emails/reminder.html.twig')
                    ->context([
                        'user' => $user,
                        'missing_days' => $missingDays,
                        'period_start' => $startDate,
                        'period_end' => $endDate,
                    ]);

                $this->mailer->send($email);

                // Petite pause pour éviter de saturer le serveur SMTP (optionnel selon provider)
                sleep(30); // 0.5 seconde
            }
        }
    }
}