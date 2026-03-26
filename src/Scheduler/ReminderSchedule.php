<?php
namespace App\Scheduler;

use App\Command\SendActivityReminderCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\RecurringMessage;

#[AsSchedule('reminders')]
class ReminderSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        // On demande au scheduler de vérifier toutes les minutes 
        // La logique de filtrage (jour/heure) se fait dans la commande elle-même
        return (new Schedule())->add(
            RecurringMessage::every('50 seconds', new \Symfony\Component\Console\Messenger\RunCommandMessage('app:send-reminders'))
        );
    }
}