<?php

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public static function data(): array
    {
        return [
            [
                'id' => 1,
                'label' => "Developpeur"
            ],
            [
                'id' => 2,
                'label' => "Responsable"
            ]
        ];
    }
    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < count(self::data()); ++$i) {
            $role = new Role();
            $role->setId(self::data()[$i]['id']);
            $role->setLabel(self::data()[$i]['label']);

            $manager->persist($role);
            $this->addReference('role_' . self::data()[$i]['id'], $role);
        }

        $manager->flush();
    }
}
