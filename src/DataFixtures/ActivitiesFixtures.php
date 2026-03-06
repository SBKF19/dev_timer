<?php

namespace App\DataFixtures;

use App\Entity\Activities;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ActivitiesFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $activities = [
            [
                'label' => 'Developpement',
                'color' => '#70D6FF',
                'is_developing' => 1,
                'need_project' => 1
            ],
            [
                'label' => 'Réunion',
                'color' => '#E9FF70',
                'is_developing' => 0,
                'need_project' => 0
            ],
            [
                'label' => 'SAV',
                'color' => '#FED0EE',
                'is_developing' => 1,
                'need_project' => 1
            ],
            [
                'label' => 'déplacement',
                'color' => '#8A38F5',
                'is_developing' => 0,
                'need_project' => 0
            ],
            [
                'label' => 'intervention',
                'color' => '#48089B',
                'is_developing' => 0,
                'need_project' => 0
            ],
            [
                'label' => 'congés payé',
                'color' => '#B4B4B4',
                'is_developing' => 0,
                'need_project' => 0
            ],
            [
                'label' => 'Congé sans solde',
                'color' => '#EBEBEB',
                'is_developing' => 0,
                'need_project' => 0
            ],
            [
                'label' => 'autre',
                'color' => '#3D3D3D',
                'is_developing' => 0,
                'need_project' => 0,
            ]
        ];
        foreach ($activities as $data) {
            $activitie = new Activities();
            $activitie->setLabel($data['label']);
            $activitie->setColor($data['color']);
            $activitie->setIsDeveloping($data['is_developing']);
            $activitie->setNeedProject($data['need_project']);
            $manager->persist($activitie);

            $this->addReference('activitie_' . strtolower(str_replace(' ', '_', $data['label'])), $activitie);
        }

        $manager->flush();
    }
}
