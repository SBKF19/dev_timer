<?php

namespace App\DataFixtures;

use App\Entity\Holiday;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class HolidayFixtures extends Fixture
{
    public static function data(): array
    {
        return [
            ['date' => '2026-01-01', 'name' => 'Jour de l\'An'],
            ['date' => '2026-04-06', 'name' => 'Pâques'],
            ['date' => '2026-05-01', 'name' => 'Fête du Travail'],
            ['date' => '2026-05-08', 'name' => 'Victoire 1945'],
            ['date' => '2026-05-14', 'name' => 'Ascension'],
            ['date' => '2026-05-25', 'name' => 'Pentecôte'],
            ['date' => '2026-07-14', 'name' => 'Fête Nationale'],
            ['date' => '2026-08-15', 'name' => 'Assomption'],
            ['date' => '2026-11-01', 'name' => 'Toussaint'],
            ['date' => '2026-11-11', 'name' => 'Armistice 1918'],
            ['date' => '2026-12-25', 'name' => 'Noël'],


        ];
    }

        public function load(ObjectManager $manager): void
        {
            foreach (self::data() as $index => $data) {

                $holiday = new Holiday();
                $holiday->setDate(new \DateTime($data['date']));
                $holiday->setName($data['name']);
                $manager->persist($holiday);
            }

        $manager->flush();
    }
        }


