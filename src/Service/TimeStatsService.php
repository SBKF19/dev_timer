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
    ) {}

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

    // Fonction pour le graphique : Compare le temps saisi par chaque utilisateur par rapport au temps global manquant
    public function getChartData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, $projectId = null): array
    {
        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;
        
        $labels = [];
        $datasets = [];

        foreach ($users as $user) {
            $datasets[$user->getId()] = [
                'label' => $user->getFirstname(),
                'backgroundColor' => $user->getColor() ?? '#36a2eb',
                'data' => [],
                'stack' => 'stack0'
            ];
        }
        
        $datasets['remaining'] = [
            'label' => 'Non saisies',
            'backgroundColor' => '#f97316',
            'data' => [],
            'stack' => 'stack0'
        ];

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($projectId) {
            $qb->andWhere('h.project = :p')->setParameter('p', $projectId);
        }
        $allEntries = $qb->getQuery()->getResult();

        $holidayMap = $this->getHolidayMap();
        $scheduleCache = []; 

        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        foreach ($period as $date) {
            $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            
            $stepStart = (clone $date)->setTime(0, 0, 0);
            $stepEnd = (clone $date);
            if ($isMonthlyView) {
                $stepEnd->modify('last day of this month')->setTime(23, 59, 59);
            } else {
                $stepEnd->setTime(23, 59, 59);
            }

            $totalStepTheorique = 0;
            $totalStepSaisi = 0;

            foreach ($users as $user) {
                $userMinutes = 0;
                foreach ($allEntries as $entry) {
                    if ($entry->getUser() === $user && $entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                        $d = $entry->getStartDate()->diff($entry->getEndDate());
                        $userMinutes += ($d->h * 60) + $d->i;
                    }
                }
                $datasets[$user->getId()]['data'][] = round($userMinutes / 60, 2);
                $totalStepSaisi += $userMinutes;

                $subPeriod = new \DatePeriod($stepStart, new \DateInterval('P1D'), (clone $stepEnd)->modify('+1 second'));
                foreach ($subPeriod as $day) {
                    $dayStr = $day->format('Y-m-d');
                    if (!isset($holidayMap[$dayStr])) {
                        if (!isset($scheduleCache[$dayStr])) {
                            $dayOfWeek = (int)$day->format('N');
                            $scheduleCache[$dayStr] = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $day);
                        }
                        foreach ($scheduleCache[$dayStr] as $s) {
                            $diffS = $s->getStartTime()->diff($s->getEndTime());
                            $totalStepTheorique += ($diffS->h * 60) + $diffS->i;
                        }
                    }
                }
            }

            $remaining = max(0, ($totalStepTheorique - $totalStepSaisi) / 60);
            $datasets['remaining']['data'][] = round($remaining, 2);
        }

        return [
            'labels' => $labels,
            'datasets' => array_values($datasets)
        ];
    }

    // Fonction pour le graphique : Répartit le temps de travail cumulé en fonction des projets
    public function getProjectChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'p')
            ->leftJoin('h.project', 'p')
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
    public function getActivityChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'a', 'u')
            ->join('h.activity', 'a')
            ->join('h.user', 'u')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
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
        $remainingDataset = [
            'label' => 'Heures manquantes',
            'backgroundColor' => '#f97316',
            'data' => [],
            'stack' => 'stack0'
        ];

        foreach ($period as $date) {
            $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            $stepStart = (clone $date)->setTime(0, 0, 0);
            $stepEnd = $isMonthlyView ? (clone $date)->modify('last day of this month')->setTime(23, 59, 59) : (clone $date)->setTime(23, 59, 59);

            $totalStepTheorique = 0;
            $totalStepSaisi = 0;

            foreach ($users as $user) {
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
            }

            foreach ($allEntries as $entry) {
                if ($entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                    $act = $entry->getActivity();
                    $mins = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
                    
                    $totalStepSaisi += $mins;
                    $actId = $act->getId();
                    
                    if (!isset($datasets[$actId])) {
                        $datasets[$actId] = [
                            'label' => $act->getLabel(),
                            'backgroundColor' => $act->getColor() ?? '#94a3b8',
                            'data' => array_fill(0, count($labels), 0),
                            'stack' => 'stack0'
                        ];
                    }
                    
                    if (!isset($datasets[$actId]['data'][count($labels) - 1])) {
                        $datasets[$actId]['data'][count($labels) - 1] = 0;
                    }
                    
                    $datasets[$actId]['data'][count($labels) - 1] += round($mins / 60, 2);
                }
            }

            foreach ($datasets as &$ds) {
                while (count($ds['data']) < count($labels)) {
                    $ds['data'][] = 0;
                }
            }

            $remaining = max(0, ($totalStepTheorique - $totalStepSaisi) / 60);
            $remainingDataset['data'][] = round($remaining, 2);
        }

        $finalDatasets = array_values($datasets);
        $finalDatasets[] = $remainingDataset;

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
                $minutes = ($diff->h * 60) + $diff->i;
                $dataMap[$entry->getUser()->getId()]['minutes'] += $minutes;
            }
        }

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($dataMap as $userData) {
            if ($userData['minutes'] > 0) {
                $labels[] = $userData['name'];
                $values[] = round($userData['minutes'] / 60, 2);
                $colors[] = $userData['color'];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $colors,
                ]
            ]
        ];
    }

    // Fonction pour le graphique global : Résume le temps total passé sur chaque activité sur l'ensemble de la période
    public function getActivityTotalChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $rawData = $this->getActivityChartRawData($users, $startDate, $endDate, $projectIds);
        
        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rawData['datasets'] as $ds) {
            $labels[] = $ds['label'];
            $values[] = array_sum($ds['data']);
            $colors[] = $ds['backgroundColor'];
        }

        return [
            'labels' => $labels,
            'datasets' => [['data' => $values, 'backgroundColor' => $colors]]
        ];
    }
}