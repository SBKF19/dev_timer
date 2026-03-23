<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use App\Repository\UserRepository;
use App\Repository\HourEntryRepository;

class ActivityReminderService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private HourEntryRepository $hourEntryRepository
    ) {
    }

    public function sendReminders(): void
    {
        // 1. Récupérer uniquement les utilisateurs actifs (status = 1 dans ta BDD)
        $users = $this->userRepository->findBy(['status' => 1]);

        $today = new \DateTimeImmutable();

        foreach ($users as $user) {
            $missingDays = [];

            // 2. Vérifier les activités sur les 7 derniers jours
            for ($i = 1; $i <= 7; $i++) {
                $dateToCheck = $today->modify("- $i days");
                if (in_array($dateToCheck->format('N'), [6, 7])) {
                    continue;
                }

                $hasEntry = $this->hourEntryRepository->hasEntriesForDate($user, $dateToCheck);

                if (!$hasEntry) {
                    $missingDays[] = $dateToCheck;
                }
            }

            // 3. Envoyer le mail UNIQUEMENT s'il manque des jours
            if (count($missingDays) > 0) {
                $email = (new TemplatedEmail())
                    ->from('noreply@devtimer.fr') // À adapter selon ton nom de domaine
                    ->to($user->getEmail())
                    ->subject('Rappel : Saisie de vos activités manquante')
                    ->htmlTemplate('emails/reminder.html.twig')
                    ->context([
                        'user' => $user,
                        'missing_days' => $missingDays,
                    ]);

                $this->mailer->send($email);
            }
        }
    }
}