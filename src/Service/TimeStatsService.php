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

    public function getWeeklyStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        // 1. Calcul théorique (Schedules)
        $schedules = $this->scheduleRepository->findAll();
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);
        
        foreach ($period as $date) {
            $dayOfWeek = (int)$date->format('N');

            if ($this->holidayRepository->findOneBy(['date' => $date])) {
                continue;
            }

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

        // 2. Calcul réel (Saisies)
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

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', floor($minutes / 60), $minutes % 60);
    }

    public function getManagerStats(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, $projectId = null): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        foreach ($users as $user) {
            $period = new \DatePeriod($startDate, new \DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
            foreach ($period as $date) {
                if ($this->holidayRepository->findOneBy(['date' => $date])) continue;
                
                $dayOfWeek = (int)$date->format('N');
                $schedules = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $date);

                foreach ($schedules as $s) {
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

   public function getChartData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, $projectId = null): array
    {
        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;
        
        $labels = [];
        $datasets = [];

        // Initialisation des datasets par utilisateur
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
                // 1. Récupération des saisies réelles
                $qb = $this->hourEntryRepository->createQueryBuilder('h')
                    ->where('h.user = :u')
                    ->andWhere('h.startDate >= :s')
                    ->andWhere('h.endDate <= :e')
                    ->setParameter('u', $user)
                    ->setParameter('s', $stepStart)
                    ->setParameter('e', $stepEnd);

                if ($projectId) {
                    $qb->andWhere('h.project = :p')->setParameter('p', $projectId);
                }

                $userMinutes = 0;
                foreach ($qb->getQuery()->getResult() as $entry) {
                    $d = $entry->getStartDate()->diff($entry->getEndDate());
                    $userMinutes += ($d->h * 60) + $d->i;
                }
                $datasets[$user->getId()]['data'][] = round($userMinutes / 60, 2);
                $totalStepSaisi += $userMinutes;

                // 2. Calcul du théorique (uniquement si ce n'est pas un jour férié)
                $subPeriod = new \DatePeriod($stepStart, new \DateInterval('P1D'), (clone $stepEnd)->modify('+1 second'));
                foreach ($subPeriod as $day) {
                    // On ne compte le théorique que si ce n'est pas un jour férié
                    if (!$this->holidayRepository->findOneBy(['date' => $day])) {
                        $dayOfWeek = (int)$day->format('N');
                        $schedules = $this->scheduleRepository->findActiveSchedulesByDay($dayOfWeek, $day);
                        foreach ($schedules as $s) {
                            $diffS = $s->getStartTime()->diff($s->getEndTime());
                            $totalStepTheorique += ($diffS->h * 60) + $diffS->i;
                        }
                    }
                }
            }

            // Calcul du orange (restant)
            $remaining = max(0, ($totalStepTheorique - $totalStepSaisi) / 60);
            $datasets['remaining']['data'][] = round($remaining, 2);
        }

        return [
            'labels' => $labels,
            'datasets' => array_values($datasets)
        ];
    }

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

    public function getActivityChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $diff = $startDate->diff($endDate);
        $isMonthlyView = $diff->days > 60;
        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        $labels = [];
        $datasets = [];
        $remainingData = [];

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
            $stepActivities = [];

            foreach ($users as $user) {
                $subPeriod = new \DatePeriod($stepStart, new \DateInterval('P1D'), (clone $stepEnd)->modify('+1 second'));
                foreach ($subPeriod as $day) {
                    if (!$this->holidayRepository->findOneBy(['date' => $day])) {
                        $schedules = $this->scheduleRepository->findActiveSchedulesByDay((int)$day->format('N'), $day);
                        foreach ($schedules as $s) {
                            $diffS = $s->getStartTime()->diff($s->getEndTime());
                            $totalStepTheorique += ($diffS->h * 60) + $diffS->i;
                        }
                    }
                }

                $qb = $this->hourEntryRepository->createQueryBuilder('h')
                    ->join('h.activity', 'a')
                    ->where('h.user = :u')
                    ->andWhere('h.startDate >= :s')
                    ->andWhere('h.endDate <= :e')
                    ->setParameter('u', $user)
                    ->setParameter('s', $stepStart)
                    ->setParameter('e', $stepEnd);

                if (!empty($projectIds)) {
                    $qb->andWhere('h.project IN (:p)')->setParameter('p', $projectIds);
                }

                foreach ($qb->getQuery()->getResult() as $entry) {
                    $act = $entry->getActivity();
                    $d = $entry->getStartDate()->diff($entry->getEndDate());
                    $mins = ($d->h * 60) + $d->i;
                    
                    $totalStepSaisi += $mins;
                    $stepActivities[$act->getId()]['label'] = $act->getLabel();
                    $stepActivities[$act->getId()]['color'] = $act->getColor();
                    $stepActivities[$act->getId()]['minutes'] = ($stepActivities[$act->getId()]['minutes'] ?? 0) + $mins;
                }
            }
            foreach ($stepActivities as $id => $info) {
                if (!isset($datasets[$id])) {
                    $datasets[$id] = [
                        'label' => $info['label'],
                        'backgroundColor' => $info['color'] ?? '#cbd5e1',
                        'data' => array_fill(0, count($labels) - 1, 0),
                        'stack' => 'stack0'
                    ];
                }
                $datasets[$id]['data'][] = round($info['minutes'] / 60, 2);
            }

            foreach ($datasets as $id => &$ds) {
                if (count($ds['data']) < count($labels)) {
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