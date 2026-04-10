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
                'users' => ['admin@devtimer.fr', 'alice@devtimer.fr', 't.bernard@devtimer.fr'],
                'ref' => 'proj_intranet'
            ],
            [
                'name' => 'Module Congés',
                'description' => 'Développement du module de gestion des absences',
                'color' => '#2ecc71',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'alice@devtimer.fr',
                'users' => ['alice@devtimer.fr', 'e.leroy@devtimer.fr'],
                'ref' => 'proj_conges'
            ],
            [
                'name' => 'E-commerce BioShop',
                'description' => 'Développement d\'une boutique en ligne sous Sylius / Symfony',
                'color' => '#27ae60',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'admin@devtimer.fr',
                'users' => ['admin@devtimer.fr', 'alice@devtimer.fr', 'j.petit@devtimer.fr'],
                'ref' => 'proj_ecommerce_bio'
            ],
            [
                'name' => 'API Gateway Logistique',
                'description' => 'Conception d\'une API REST centralisant les flux transporteurs',
                'color' => '#8e44ad',
                'manager' => 'sophie.rh@devtimer.fr',
                'creator' => 'admin@devtimer.fr',
                'users' => ['t.bernard@devtimer.fr', 'e.leroy@devtimer.fr'],
                'ref' => 'proj_api_logistique'
            ],
            [
                'name' => 'Dashboard SaaS RH',
                'description' => 'Interface d\'administration en Vue.js avec backend Symfony',
                'color' => '#f39c12',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'sophie.rh@devtimer.fr',
                'users' => ['alice@devtimer.fr', 't.bernard@devtimer.fr', 'e.leroy@devtimer.fr'],
                'ref' => 'proj_saas_rh'
            ],
            [
                'name' => 'Refonte Portail Client',
                'description' => 'Migration d\'un legacy PHP vers une architecture découplée (Next.js / Symfony)',
                'color' => '#2980b9',
                'manager' => 'sophie.rh@devtimer.fr',
                'creator' => 'sophie.rh@devtimer.fr',
                'users' => ['admin@devtimer.fr', 'j.petit@devtimer.fr'],
                'ref' => 'proj_portail_client'
            ],
            [
                'name' => 'Maintenance Web & Correctifs',
                'description' => 'Interventions rapides, mises à jour de sécurité et debug divers',
                'color' => '#c0392b',
                'manager' => 'admin@devtimer.fr',
                'creator' => 'admin@devtimer.fr',
                'users' => ['admin@devtimer.fr', 'alice@devtimer.fr', 't.bernard@devtimer.fr', 'j.petit@devtimer.fr', 'e.leroy@devtimer.fr'],
                'ref' => 'proj_maintenance'
            ],
        ];

        foreach ($projectsData as $data) {
            $project = new Project();
            $project->setName($data['name']);
            $project->setDescription($data['description']);
            $project->setColor($data['color']);

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