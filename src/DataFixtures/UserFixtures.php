<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }
    public function load(ObjectManager $manager): void
    {
        $roles = [
            $this->getReference('role_développeur', Role::class),
            $this->getReference('role_responsable', Role::class),
        ];

        $users = [
            [
                'email' => 'haller.charles@orange.fr',
                'firstname' => 'Haller',
                'lastname' => 'Charles',
                'password' => '123',
                'hired_date' => '2025-12-05',
                'photo' => 'haller.png',
                'status' => 'actif',
                'contract_end_date' => '',
                'role_id' => $roles[1]
            ],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setPassword($data['password']);
            $user->setHiredDate(new \DateTime($data['hired_date']));
            $user->setPhoto($data['photo']);
            $user->setStatus($data['status']);
            $user->setRole($data['role_id']);
            $manager->persist($user);

            $this->addReference('user_' . strtolower(str_replace(' ', '_', $data['email'])), $user);
        }

        $manager->flush();
    }
}