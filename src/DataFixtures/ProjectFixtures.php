<?php

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $projectsData = [
            [
                'name' => 'Refonte Intranet',
                'description' => 'Migration vers Symfony 8 et API Platform',
                'color' => '#3498db',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'admin@devtimer.fr',
                'users' => ['admin@devtimer.fr', 'alice@devtimer.fr'],
                'ref' => 'proj_intranet'
            ],
            [
                'name' => 'Module Congés',
                'description' => 'Développement du module de gestion des absences',
                'color' => '#2ecc71',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'alice@devtimer.fr',
                'users' => ['alice@devtimer.fr'],
                'ref' => 'proj_conges'
            ],
        ];

        foreach ($projectsData as $data) {
            $project = new Project();
            $project->setName($data['name']);
            $project->setDescription($data['description']);
            $project->setColor($data['color']);

            // On récupère les utilisateurs par leur référence (email)
            $managerUser = $this->getReference('user_' . $data['manager'], User::class);
            $creatorUser = $this->getReference('user_' . $data['creator'], User::class);

            $project->setManager($managerUser);
            $project->setCreatedBy($creatorUser);

            foreach ($data['users'] as $userEmail) {
                $member = $this->getReference('user_' . $userEmail, User::class);
                $project->addUserInProject($member);
            }

            $manager->persist($project);

            $this->addReference($data['ref'], $project);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}