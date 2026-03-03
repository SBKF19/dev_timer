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
            ['day' => '1', 'startTime' => '09:00', 'endTime' => '12:00'],
            ['day' => '1', 'startTime' => '13:00', 'endTime' => '17:00'],
            ['day' => '2', 'startTime' => '08:00', 'endTime' => '11:30'],
            ['day' => '2', 'startTime' => '13:00', 'endTime' => '16:30'],
            ['day' => '3', 'startTime' => '10:00', 'endTime' => '13:00'],
            ['day' => '3', 'startTime' => '14:00', 'endTime' => '17:30'],
            ['day' => '4', 'startTime' => '09:30', 'endTime' => '12:00'],
            ['day' => '4', 'startTime' => '14:00', 'endTime' => '18:00'],
            ['day' => '5', 'startTime' => '09:30', 'endTime' => '13:00'],
        ];
    }

    public function load(ObjectManager $manager): void
        {
            foreach (self::data() as $index => $data) {

                $schedule = new Schedule();
                $schedule->setDayOfWeek($data['day']);
                $schedule->setStartTime(new \DateTime($data['startTime']));
                $schedule->setEndTime(new \DateTime($data['endTime']));
                $manager->persist($schedule);
            }
            $manager->flush();
        }
}
