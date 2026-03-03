<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Project;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            $this->getReference('user_haller.charles@orange.fr', User::class),
        ];

        $projects = [
            [
                'name' => 'DevTimer',
                'description' => 'Project devTimer stage, réaliser un site',
                'created_at' => '2026-03-03',
                'manager_id' => $users[0],
                'archived_at' => ''
            ],
        ];

         foreach ($projects as $data) {
            $project = new Project();
            $project->setName($data['name']);
            $project->setDescription($data['description']);
            $project->setCreatedAt($data['created_at']);
            $project->setUser($data['manager_id']);
            $project->setArchivedAt($data['archived_at']);

            $manager->persist($role);

            $this->addReference('project_' . strtolower(str_replace(' ', '_', $data['name'])), $role);
        }

        $manager->flush();
    }
}