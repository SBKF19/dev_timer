<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $roles = [
            [
                'label' => 'Développeur',
            ],
            [
                'label' => 'Responsable',
            ]
        ];

        foreach ($roles as $data) {
            $role = new Role();
            $role->setLabel($data['label']);
            $manager->persist($role);

            $this->addReference('role_' . strtolower(str_replace(' ', '_', $data['label'])), $role);
        }

        $manager->flush();
    }
}