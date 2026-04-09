<?php

namespace App\DataFixtures;

use App\Entity\Activities;
use App\Entity\User;
use App\Entity\Project;
use App\Entity\HourEntry;
use App\Entity\Schedule;
use App\Entity\Holiday;
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
            ProjectFixtures::class,
            ScheduleFixtures::class,
            HolidayFixtures::class
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $activities = $manager->getRepository(Activities::class)->findAll();
        $projects = $manager->getRepository(Project::class)->findAll();
        $users = $manager->getRepository(User::class)->findAll();
        $schedules = $manager->getRepository(Schedule::class)->findAll();
        $holidays = $manager->getRepository(Holiday::class)->findAll();

        // Mapping des commentaires par label d'activité (normalisé)
        $commentsByActivity = [
            'developpement'    => ['Refactorisation du core', 'Correction bug affichage', 'Développement API', 'Optimisation SQL', 'Review de code'],
            'reunion'          => ['Réunion hebdomadaire', 'Sprint Planning', 'Daily Stand-up', 'RDV Client', 'Debriefing projet'],
            'sav'              => ['Ticket #4502', 'Assistance téléphonique', 'Debug production', 'Maintenance serveur'],
            'deplacement'      => ['Trajet agence', 'Déplacement site client', 'Visite technique'],
            'intervention'     => ['Installation sur site', 'Maintenance préventive', 'Dépannage matériel'],
            'conges_paye'      => ['CP - Validé', 'Repos'],
            'conge_sans_solde' => ['Absence autorisée'],
            'autre'            => ['Veille technologique', 'Rangement bureau', 'Formation interne']
        ];

        // Indexation des jours fériés pour la performance
        $holidayDates = [];
        foreach ($holidays as $h) {
            $holidayDates[$h->getDate()->format('Y-m-d')] = true;
        }

        // Période : du 01/01/2026 à aujourd'hui
        $startDateObj = new \DateTime('2026-01-01');
        $endDateObj   = new \DateTime(); 
        
        $interval  = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($startDateObj, $interval, $endDateObj->modify('+1 day'));

        foreach ($dateRange as $currentDate) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayOfWeek = (int)$currentDate->format('N'); // 1 (Lundi) à 7 (Dimanche)

            // 1. Exclusion des jours fériés
            if (isset($holidayDates[$dateKey])) continue;

            // 2. Récupération des créneaux de travail prévus pour ce jour
            $dailySchedules = array_filter($schedules, function($s) use ($dayOfWeek) {
                return $s->getDayOfWeek() === $dayOfWeek;
            });

            if (empty($dailySchedules)) continue;

            foreach ($users as $user) {
                // 3. Vérification des dates de validité du contrat
                if ($currentDate < $user->getHiredDate()) continue;
                if ($user->getContractEndDate() && $currentDate > $user->getContractEndDate()) continue;
                if ($user->getDeletedAt() && $currentDate > $user->getDeletedAt()) continue;

                foreach ($dailySchedules as $sch) {
                    // 4. Simulation d'absence ou oubli (8% de chance)
                    if (rand(1, 100) <= 8) continue;

                    $entry = new HourEntry();
                    
                    // Définition de l'activité
                    $selectedActivity = $activities[array_rand($activities)];
                    $entry->setActivity($selectedActivity);

                    // Nettoyage du label pour correspondre aux clés du mapping
                    $cleanLabel = strtolower(str_replace([' ', 'é', 'è'], ['_', 'e', 'e'], $selectedActivity->getLabel()));
                    $possibleComments = $commentsByActivity[$cleanLabel] ?? ['Travail en cours'];
                    
                    // Configuration de l'entrée
                    $entry->setUser($user);
                    $entry->setCommentary($possibleComments[array_rand($possibleComments)]);
                    $entry->setCreatedBy($user);
                    $entry->setCreatedAt(clone $currentDate);

                    // Gestion du projet (Uniquement si l'activité le nécessite)
                    if ($selectedActivity->isNeedProject() && !empty($projects)) {
                        $entry->setProject($projects[array_rand($projects)]);
                    } else {
                        $entry->setProject(null);
                    }

                    // Calcul des horaires selon le Schedule
                    $start = (clone $currentDate)->setTime(
                        (int)$sch->getStartTime()->format('H'), 
                        (int)$sch->getStartTime()->format('i')
                    );
                    $end = (clone $currentDate)->setTime(
                        (int)$sch->getEndTime()->format('H'), 
                        (int)$sch->getEndTime()->format('i')
                    );

                    $entry->setStartDate($start);
                    $entry->setEndDate($end);

                    $manager->persist($entry);
                }
            }
            
            // Flush par paquet de jours pour optimiser la mémoire
            if ($dayOfWeek === 7) {
                $manager->flush();
            }
        }

        $manager->flush();
        $manager->clear(); // Libère la mémoire à la fin
    }
}