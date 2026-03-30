<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\HourEntryRepository;
use App\Repository\ScheduleRepository;
use App\Repository\HolidayRepository;

class TimeStatsService
{
    public function __construct(
        private HourEntryRepository $hourEntryRepository,
        private ScheduleRepository $scheduleRepository,
        private HolidayRepository $holidayRepository
    ) {
    }

    /**
     * Retourne la liste des jours où la saisie est incomplète
     */
    public function getMissingDaysDetail(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $missingDays = [];

        // Optimisation : On récupère tous les jours fériés de la période en une fois
        $holidays = $this->holidayRepository->findBetweenDates($startDate, $endDate);
        $holidayDates = array_map(fn($h) => $h->getDate()->format('Y-m-d'), $holidays);

        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), (clone $endDate)->modify('+1 day'));

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');

            // Exclusion Week-end (6,7) et Jours fériés
            if (in_array($dayOfWeek, [6, 7]) || in_array($dateStr, $holidayDates)) {
                continue;
            }

            // 1. Théorique pour ce jour précis
            $theoreticalMinutes = 0;
            $schedules = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $date);
            foreach ($schedules as $s) {
                $diff = $s->getStartTime()->diff($s->getEndTime());
                $theoreticalMinutes += ($diff->h * 60) + $diff->i;
            }

            if ($theoreticalMinutes === 0)
                continue;

            // 2. Réel pour ce jour précis
            $actualMinutes = 0;
            $entries = $this->hourEntryRepository->findByUserAndDate($user, $date);
            foreach ($entries as $entry) {
                $diff = $entry->getStartDate()->diff($entry->getEndDate());
                $actualMinutes += ($diff->h * 60) + $diff->i;
            }

            // 3. Comparaison
            if ($actualMinutes < $theoreticalMinutes) {
                $missingDays[] = [
                    'date' => clone $date,
                    'hours_missing' => round(($theoreticalMinutes - $actualMinutes) / 60, 2),
                    'theorique' => $this->formatMinutes($theoreticalMinutes), // Clé renommée
                    'saisie' => $this->formatMinutes($actualMinutes)         // Clé renommée pour Twig
                ];
            }
        }

        return $missingDays;
    }

    // Fonction utilitaire : Met en cache les jours fériés pour éviter les requêtes SQL à répétition dans les boucles
    private function getHolidayMap(): array
    {
        $holidays = $this->holidayRepository->findAll();
        $map = [];
        foreach ($holidays as $h) {
            $map[$h->getDate()->format('Y-m-d')] = true;
        }
        return $map;
    }

    // Fonction utilitaire : Convertit un nombre total de minutes en format texte lisible (ex: 8h30)
    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', floor($minutes / 60), $minutes % 60);
    }

    // Fonction pour obtenir le total des heures saisies et manquantes pour un utilisateur sur une période
    public function getWeeklyStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        $holidayMap = $this->getHolidayMap();
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), clone $endDate);
        
        foreach ($period as $date) {
            if (isset($holidayMap[$date->format('Y-m-d')])) {
                continue;
            }

            $dayOfWeek = (int)$date->format('N');
            $schedules = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $date);

            foreach ($schedules as $s) {
                $start = $s->getStartTime();
                $end = $s->getEndTime();

                if ($start && $end) {
                    $diff = $start->diff($end);
                    $totalTheoreticalMinutes += ($diff->h * 60) + $diff->i;
                }
            }
        }

        $entries = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user = :user')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $totalSaisieMinutes += ($diff->h * 60) + $diff->i;
        }

        $restantMinutes = max(0, $totalTheoreticalMinutes - $totalSaisieMinutes);

        return [
            'saisie' => $this->formatMinutes($totalSaisieMinutes),
            'restant' => $this->formatMinutes($restantMinutes),
        ];
    }

    // Fonction pour obtenir le total cumulé des heures saisies et manquantes pour plusieurs utilisateurs
    public function getManagerStats(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, $projectId = null): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        $holidayMap = $this->getHolidayMap();
        $scheduleCache = [];

        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
        
        foreach ($users as $user) {
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                if (isset($holidayMap[$dateStr])) continue;
                
                if (!isset($scheduleCache[$dateStr])) {
                    $dayOfWeek = (int)$date->format('N');
                    $scheduleCache[$dateStr] = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $date);
                }

                foreach ($scheduleCache[$dateStr] as $s) {
                    $diff = $s->getStartTime()->diff($s->getEndTime());
                    $totalTheoreticalMinutes += ($diff->h * 60) + $diff->i;
                }
            }
        }

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($projectId) {
            $qb->andWhere('h.project IN (:project)')->setParameter('project', $projectId);
        }

        $entries = $qb->getQuery()->getResult();

        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $totalSaisieMinutes += ($diff->h * 60) + $diff->i;
        }

        $restantMinutes = max(0, $totalTheoreticalMinutes - $totalSaisieMinutes);

        return [
            'saisie' => $this->formatMinutes($totalSaisieMinutes),
            'restant' => $this->formatMinutes($restantMinutes),
        ];
    }

    // Fonction pour le graphique : Répartit le temps de travail cumulé en fonction des projets
    public function getProjectChartRawData(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'p')
            ->leftJoin('h.project', 'p')
            ->where('h.user = :user')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }

        $entries = $qb->getQuery()->getResult();

        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;
        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        $labels = [];
        $dateMapping = [];
        foreach ($period as $index => $date) {
            $label = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            $labels[] = $label;
            $dateMapping[$date->format($isMonthlyView ? 'Y-m' : 'Y-m-d')] = $index;
        }

        $datasets = [];
        foreach ($entries as $entry) {
            $project = $entry->getProject();
            if (!$project) continue;

            $pId = $project->getId();
            if (!isset($datasets[$pId])) {
                $datasets[$pId] = [
                    'label' => $project->getName(),
                    'backgroundColor' => $project->getColor() ?? '#cbd5e1',
                    'data' => array_fill(0, count($labels), 0),
                    'stack' => 'combined',
                ];
            }

            $duration = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
            $dateKey = $entry->getStartDate()->format($isMonthlyView ? 'Y-m' : 'Y-m-d');
            
            if (isset($dateMapping[$dateKey])) {
                $datasets[$pId]['data'][$dateMapping[$dateKey]] += round($duration / 60, 2);
            }
        }

        return ['labels' => $labels, 'datasets' => array_values($datasets)];
    }

    // Fonction pour le graphique : Répartit le temps de travail cumulé en fonction des activités
    public function getActivityChartRawData(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'a')
            ->join('h.activity', 'a')
            ->where('h.user = :user')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:p)')->setParameter('p', $projectIds);
        }
        
        $allEntries = $qb->getQuery()->getResult();
        $holidayMap = $this->getHolidayMap();
        $scheduleCache = [];

        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        $labels = [];
        $datasets = [];
        $remainingData = [];

        foreach ($period as $date) {
            $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            $currentIndex = count($labels) - 1;

            $stepStart = (clone $date)->setTime(0, 0, 0);
            $stepEnd = $isMonthlyView ? (clone $date)->modify('last day of this month')->setTime(23, 59, 59) : (clone $date)->setTime(23, 59, 59);

            $totalStepTheorique = 0;
            $totalStepSaisi = 0;

            $subPeriod = new \DatePeriod($stepStart, new \DateInterval('P1D'), (clone $stepEnd)->modify('+1 second'));
            foreach ($subPeriod as $day) {
                $dayStr = $day->format('Y-m-d');
                if (!isset($holidayMap[$dayStr])) {
                    if (!isset($scheduleCache[$dayStr])) {
                        $scheduleCache[$dayStr] = $this->scheduleRepository->findActiveSchedulesByDay((int)$day->format('N'), $day);
                    }
                    foreach ($scheduleCache[$dayStr] as $s) {
                        $totalStepTheorique += ($s->getStartTime()->diff($s->getEndTime())->h * 60) + $s->getStartTime()->diff($s->getEndTime())->i;
                    }
                }
            }

            // Correction : On initialise l'index actuel pour tous les datasets déjà créés
            foreach ($datasets as &$ds) {
                $ds['data'][$currentIndex] = 0;
            }

            foreach ($allEntries as $entry) {
                if ($entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                    $mins = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
                    $totalStepSaisi += $mins;
                    $act = $entry->getActivity();
                    $actId = $act->getId();
                    
                    if (!isset($datasets[$actId])) {
                        $datasets[$actId] = [
                            'label' => $act->getLabel(),
                            'backgroundColor' => $act->getColor() ?? '#94a3b8',
                            'data' => array_fill(0, count($labels), 0), // Remplit de zéros jusqu'à maintenant
                            'stack' => 'stack0'
                        ];
                    }
                    $datasets[$actId]['data'][$currentIndex] += round($mins / 60, 2);
                }
            }

            $remainingData[] = round(max(0, ($totalStepTheorique - $totalStepSaisi) / 60), 2);
        }

        $finalDatasets = array_values($datasets);
        $finalDatasets[] = [
            'label' => 'Heures manquantes',
            'backgroundColor' => '#f97316',
            'data' => $remainingData,
            'stack' => 'stack0'
        ];

        return ['labels' => $labels, 'datasets' => $finalDatasets];
    }

    // Fonction pour le graphique : Compare le temps passé spécifiquement sur des tâches de développement entre les utilisateurs
    public function getUserTotalDevChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'u', 'a')
            ->join('h.user', 'u')
            ->leftJoin('h.activity', 'a')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }

        $entries = $qb->getQuery()->getResult();
        $dataMap = [];
        foreach ($users as $user) {
            $dataMap[$user->getId()] = [
                'name' => $user->getFirstname() . ' ' . $user->getLastname(),
                'color' => $user->getColor() ?? '#cbd5e1',
                'minutes' => 0
            ];
        }

        foreach ($entries as $entry) {
            if ($entry->getActivity()?->isDeveloping()) {
                $diff = $entry->getStartDate()->diff($entry->getEndDate());
                $dataMap[$entry->getUser()->getId()]['minutes'] += ($diff->h * 60) + $diff->i;
            }
        }

        $labels = []; $values = []; $colors = [];
        foreach ($dataMap as $userData) {
            if ($userData['minutes'] > 0) {
                $labels[] = $userData['name'];
                $values[] = round($userData['minutes'] / 60, 2);
                $colors[] = $userData['color'];
            }
        }

        return ['labels' => $labels, 'datasets' => [['data' => $values, 'backgroundColor' => $colors]]];
    }

    // Fonction pour le graphique global : Résume le temps total passé sur chaque activité sur l'ensemble de la période
    public function getActivityTotalChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        return []; 
    }

    public function getCompletionStats(User $user): array
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

        $totalTheo = 0; $totalSaisie = 0;
        $holidayMap = $this->getHolidayMap();
        $period = new \DatePeriod($startOfWeek, new \DateInterval('P1D'), (clone $endOfWeek)->modify('+1 day'));

        foreach ($period as $date) {
            if (isset($holidayMap[$date->format('Y-m-d')])) continue;
            $schedules = $this->scheduleRepository->findActiveSchedulesByDay((int)$date->format('N'), $date);
            foreach ($schedules as $s) {
                $diff = $s->getStartTime()->diff($s->getEndTime());
                $totalTheo += ($diff->h * 60) + $diff->i;
            }
        }

        $entries = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user = :u AND h.startDate >= :s AND h.endDate <= :e')
            ->setParameter('u', $user)->setParameter('s', $startOfWeek)->setParameter('e', $endOfWeek)
            ->getQuery()->getResult();

        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $totalSaisie += ($diff->h * 60) + $diff->i;
        }

        $percentage = $totalTheo > 0 ? min(100, round(($totalSaisie / $totalTheo) * 100)) : 0;
        $missingHours = max(0, round(($totalTheo - $totalSaisie) / 60, 1));

        return [
            'percentage' => $percentage,
            'missingHours' => $missingHours,
        ];
    }
}