<?php

namespace App\DataFixtures;

use App\Entity\Activities;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\HourEntry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class HourEntryFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            ActivitiesFixtures::class,
            UserFixtures::class,
            ProjectFixtures::class
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $activities = $manager->getRepository(Activities::class)->findAll();
        $users = $manager->getRepository(User::class)->findAll();
        $projects = $manager->getRepository(Project::class)->findAll();

        $hourEntries = [];

        for ($i = 0; $i < 10; $i++) {

            $start = new \DateTime();
            $start->modify('-' . rand(0, 30) . ' days');

            // période matin ou aprèm horaire = 8/12h - 13/17h
            $period = rand(0, 1); 
            if ($period === 0) {
                $hour = rand(8, 11);
                $maxDuration = 12 - $hour;
            } else {
                // 13h - 17h
                $hour = rand(13, 16);
                $maxDuration = 17 - $hour;

            }
            $start->setTime($hour, 0);

            //Durée qi ne dépase pas la plage 
            $duration = rand(1, $maxDuration);
            $end = (clone $start)->modify('+' . rand(1, 8) . ' hours');

            $hourEntries[] = [
                'activity_id' => $activities[array_rand($activities)],
                'user_id' => $users[array_rand($users)],
                'project_id' => $projects[array_rand($projects)],
                'start_date' => $start,
                'end_date' => $end,
                'commentary' => rand(0, 1) ? 'Travail sur feature X' : '',
                'created_at' => new \DateTimeImmutable(),
                'created_by' => 'fixture'
            ];
        }

        foreach ($hourEntries as $index => $data) {
            $hourEntry = new HourEntry();
            $hourEntry->setActivities($data['activity_id']);
            $hourEntry->setUser($data['user_id']);
            $hourEntry->setProject($data['project_id']);
            $hourEntry->setStartDate($data['start_date']);
            $hourEntry->setEndDate($data['end_date']);
            $hourEntry->setCommentary($data['commentary']);
            $hourEntry->setCreatedAt($data['created_at']);
            $hourEntry->setCreatedBy($data['created_by']);
            $manager->persist($hourEntry);

            $this->addReference('hour_entry_'.$index, $hourEntry);
        }


        $manager->flush();
    }
}