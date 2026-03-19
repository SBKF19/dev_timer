<?php

namespace App\DataFixtures;

use App\Entity\Schedule;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ScheduleFixtures extends Fixture
{
    public static function data(): array
    {
        return [
            ['day' => '1', 'period' => 'Matin', 'startTime' => '09:00', 'endTime' => '12:00'],
            ['day' => '1', 'period' => 'Après-midi', 'startTime' => '13:00', 'endTime' => '17:00'],
            ['day' => '2', 'period' => 'Matin', 'startTime' => '08:00', 'endTime' => '11:30'],
            ['day' => '2', 'period' => 'Après-midi', 'startTime' => '13:00', 'endTime' => '16:30'],
            ['day' => '3', 'period' => 'Matin', 'startTime' => '10:00', 'endTime' => '13:00'],
            ['day' => '3', 'period' => 'Après-midi', 'startTime' => '14:00', 'endTime' => '17:30'],
            ['day' => '4', 'period' => 'Matin', 'startTime' => '09:30', 'endTime' => '12:00'],
            ['day' => '4', 'period' => 'Après-midi', 'startTime' => '14:00', 'endTime' => '18:00'],
            ['day' => '5', 'period' => 'Matin', 'startTime' => '09:30', 'endTime' => '13:00'],
        ];
    }

    public function load(ObjectManager $manager): void
        {
            $startDate = new \DateTime('2026-01-01 08:00:00');
            foreach (self::data() as $index => $data) {

                $schedule = new Schedule();
                $schedule->setDayOfWeek($data['day']);
                $schedule->setPeriod($data['period']);
                $schedule->setStartTime(new \DateTime($data['startTime']));
                $schedule->setEndTime(new \DateTime($data['endTime']));
                if (method_exists($schedule, 'setCreatedAt')) {
                    $schedule->setCreatedAt($startDate);
                }
                $manager->persist($schedule);
            }
            $manager->flush();
        }
}
