<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $usersData = [
            [
                'firstname' => 'Charles',
                'lastname' => 'Haller',
                'email' => 'admin@devtimer.fr',
                'password' => 'admin123',
                'hired_date' => '2023-01-01',
                'role_id' => 1,
                'photo_path' => 'uploads/photos/haller.jpg',
                'status' => true,
                'contract_end_date' => null,
                'color' => '#2a7de3',
            ],
            [
                'firstname' => 'Alice',
                'lastname' => 'Dupond',
                'email' => 'alice@devtimer.fr',
                'password' => 'dev123',
                'hired_date' => '2024-02-15',
                'role_id' => 2,
                'photo_path' => 'uploads/photos/alice.jpg',
                'status' => true,
                'contract_end_date' => '2027-02-15',
                'color' => '#3498db',
            ],
            [
                'firstname' => 'Sophie',
                'lastname' => 'Martin',
                'email' => 'sophie.rh@devtimer.fr',
                'password' => 'admin123',
                'hired_date' => '2023-03-15',
                'role_id' => 1, // Admin
                'photo_path' => 'uploads/photos/sophie.jpg',
                'status' => true,
                'contract_end_date' => null,
                'color' => '#e74c3c',
            ],
            [
                'firstname' => 'Thomas',
                'lastname' => 'Bernard',
                'email' => 't.bernard@devtimer.fr',
                'password' => 'dev123',
                'hired_date' => '2023-06-01',
                'role_id' => 2,
                'photo_path' => 'uploads/photos/thomas.jpg',
                'status' => true,
                'contract_end_date' => null,
                'color' => '#2ecc71',
            ],
            [
                'firstname' => 'Julien',
                'lastname' => 'Petit',
                'email' => 'j.petit@devtimer.fr',
                'password' => 'dev123',
                'hired_date' => '2024-01-10',
                'role_id' => 2,
                'photo_path' => 'uploads/photos/julien.jpg',
                'status' => true,
                'contract_end_date' => '2025-01-10',
                'color' => '#f1c40f',
            ],
            [
                'firstname' => 'Emma',
                'lastname' => 'Leroy',
                'email' => 'e.leroy@devtimer.fr',
                'password' => 'dev123',
                'hired_date' => '2024-05-20',
                'role_id' => 2,
                'photo_path' => 'uploads/photos/emma.jpg',
                'status' => true,
                'contract_end_date' => null,
                'color' => '#9b59b6',
            ],
        ];

        foreach ($usersData as $data) {
            $user = new User();
            $user->setFirstname($data['firstname']);
            $user->setLastname($data['lastname']);
            $user->setEmail($data['email']);

            $password = $this->hasher->hashPassword($user, $data['password']);
            $user->setPassword($password);

            $user->setHiredDate(new \DateTime($data['hired_date']));
            if ($data['contract_end_date']) {
                $user->setContractEndDate(new \DateTime($data['contract_end_date']));
            }

            $user->setPhoto($data['photo_path']);
            $user->setStatus($data['status']);
            $user->setColor($data['color']);

            $user->setCreateAt(new \DateTime());
            $user->setLastLogin(null);
            $user->setDeletedAt(null);

            $user->setRole($this->getReference('role_' . $data['role_id'], Role::class));

            $manager->persist($user);

            $this->addReference('user_' . $data['email'], $user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }
}