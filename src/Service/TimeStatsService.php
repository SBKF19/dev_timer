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

        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate, \DatePeriod::INCLUDE_END_DATE);

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
                    'theorique' => $this->formatMinutes($theoreticalMinutes),
                    'saisie' => $this->formatMinutes($actualMinutes)
                ];
            }
        }

        return $missingDays;
    }

    /**
     * Fonction utilitaire : Met en cache les jours fériés pour éviter les requêtes SQL à répétition
     */
    private function getHolidayMap(): array
    {
        $holidays = $this->holidayRepository->findAll();
        $map = [];
        foreach ($holidays as $h) {
            $map[$h->getDate()->format('Y-m-d')] = true;
        }
        return $map;
    }

    /**
     * Fonction utilitaire : Convertit un nombre total de minutes en format texte lisible (ex: 8h30)
     */
    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh%02d', floor($minutes / 60), $minutes % 60);
    }

    /**
     * Fonction pour obtenir le total des heures saisies et manquantes pour un utilisateur sur une période
     */
    public function getWeeklyStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        $holidayMap = $this->getHolidayMap();
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate, \DatePeriod::INCLUDE_END_DATE);
        
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
            ->where('h.user = :user AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()->getResult();

        foreach ($entries as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $totalSaisieMinutes += ($diff->h * 60) + $diff->i;
        }

        return [
            'saisie' => $this->formatMinutes($totalSaisieMinutes),
            'restant' => $this->formatMinutes(max(0, $totalTheoreticalMinutes - $totalSaisieMinutes)),
        ];
    }

    /**
     * Fonction pour obtenir le total cumulé des heures saisies et manquantes pour plusieurs utilisateurs
     */
    public function getManagerStats(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, $projectId = null): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        $holidayMap = $this->getHolidayMap();
        $scheduleCache = [];
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate, \DatePeriod::INCLUDE_END_DATE);
        
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
            ->where('h.user IN (:users) AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        if ($projectId) {
            $qb->andWhere('h.project IN (:project)')->setParameter('project', $projectId);
        }

        foreach ($qb->getQuery()->getResult() as $entry) {
            $diff = $entry->getStartDate()->diff($entry->getEndDate());
            $totalSaisieMinutes += ($diff->h * 60) + $diff->i;
        }

        return [
            'saisie' => $this->formatMinutes($totalSaisieMinutes),
            'restant' => $this->formatMinutes(max(0, $totalTheoreticalMinutes - $totalSaisieMinutes)),
        ];
    }

    /**
     * Fonction pour le graphique : Répartit le temps de travail cumulé en fonction des projets (Compatible User ou Array)
     */
    public function getProjectChartRawData(User|array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        if ($users instanceof User) $users = [$users];

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'p')->leftJoin('h.project', 'p')
            ->where('h.user IN (:users) AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('users', $users)->setParameter('start', $startDate)->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }

        $entries = $qb->getQuery()->getResult();
        $isMonthlyView = $startDate->diff($endDate)->days > 60;
        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate, \DatePeriod::INCLUDE_END_DATE);

        $labels = []; $dateMapping = [];
        foreach ($period as $index => $date) {
            $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            $dateMapping[$date->format($isMonthlyView ? 'Y-m' : 'Y-m-d')] = $index;
        }

        $datasets = [];
        $totalLabels = count($labels);

        foreach ($entries as $entry) {
            $project = $entry->getProject();
            if (!$project) continue;

            $pId = $project->getId();
            if (!isset($datasets[$pId])) {
                $datasets[$pId] = [
                    'label' => $project->getName(),
                    'backgroundColor' => $project->getColor() ?? '#cbd5e1',
                    'data' => array_fill(0, $totalLabels, 0),
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

    /**
     * Fonction pour le graphique : Répartit le temps de travail cumulé en fonction des activités (Compatible User ou Array)
     */
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
        $filteredEntries = $qb->getQuery()->getResult();

        $qbGlobal = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);
        $globalEntries = $qbGlobal->getQuery()->getResult();

        $holidayMap = $this->getHolidayMap();
        $scheduleCache = [];
        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        $labels = [];
        $datasets = [];
        $remainingData = [];

        foreach ($period as $date) {
            $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m');
            $stepStart = (clone $date)->setTime(0, 0, 0);
            $stepEnd = $isMonthlyView ? (clone $date)->modify('last day of this month')->setTime(23, 59, 59) : (clone $date)->setTime(23, 59, 59);

            $totalStepTheorique = 0;
            $totalStepSaisiReel = 0;

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

                foreach ($globalEntries as $entry) {
                    if ($entry->getUser() === $user && $entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                        $totalStepSaisiReel += ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
                    }
                }
            }

            foreach ($filteredEntries as $entry) {
                if ($entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                    $act = $entry->getActivity();
                    $actId = $act->getId();
                    $mins = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;

                    if (!isset($datasets[$actId])) {
                        $datasets[$actId] = [
                            'label' => $act->getLabel(),
                            'backgroundColor' => $act->getColor() ?? '#94a3b8',
                            'data' => array_fill(0, count($labels) - 1, 0),
                            'stack' => 'stack0'
                        ];
                    }
                    
                    if (!isset($datasets[$actId]['data'][count($labels) - 1])) {
                        $datasets[$actId]['data'][count($labels) - 1] = 0;
                    }
                    $datasets[$actId]['data'][count($labels) - 1] += round($mins / 60, 2);
                }
            }

            $remaining = max(0, ($totalStepTheorique - $totalStepSaisiReel) / 60);
            $remainingData[] = round($remaining, 2);

            foreach ($datasets as &$ds) {
                if (count($ds['data']) < count($labels)) {
                    $ds['data'][] = 0;
                }
            }
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

    public function getActivityChartRawData2(User|array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $isUserView = $users instanceof User;
        if ($isUserView) $users = [$users];

        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'a')->join('h.activity', 'a')
            ->where('h.user IN (:users) AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('users', $users)->setParameter('start', $startDate)->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:p)')->setParameter('p', $projectIds);
        }
        $filteredEntries = $qb->getQuery()->getResult();

        $qbGlobal = $this->hourEntryRepository->createQueryBuilder('h')
            ->where('h.user IN (:users)')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('users', $users)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);
        $globalEntries = $qbGlobal->getQuery()->getResult();

        $holidayMap = $this->getHolidayMap();
        $scheduleCache = [];
        $isMonthlyView = $startDate->diff($endDate)->days > 60;
        $interval = $isMonthlyView ? new \DateInterval('P1M') : new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate, \DatePeriod::INCLUDE_END_DATE);

        $labels = [];
        $datasets = [];
        $remainingData = [];
        foreach ($period as $date) { $labels[] = $date->format($isMonthlyView ? 'M Y' : 'd/m'); }
        $totalLabels = count($labels);
      
        $remainingData = array_fill(0, $totalLabels, 0);
        $singleUser = $isUserView ? (is_array($users) ? reset($users) : $users) : null;

        foreach ($period as $index => $date) {
            $stepStart = (clone $date)->setTime(0, 0, 0);
            $stepEnd = $isMonthlyView ? (clone $date)->modify('last day of this month')->setTime(23, 59, 59) : (clone $date)->setTime(23, 59, 59);

            $totalStepTheorique = 0;
            $totalStepSaisiReel = 0;

            if (count($users) === 1) {
                $subPeriod = new \DatePeriod($stepStart, new \DateInterval('P1D'), $stepEnd, \DatePeriod::INCLUDE_END_DATE);
                foreach ($subPeriod as $day) {
                    if (!isset($holidayMap[$day->format('Y-m-d')])) {
                        $schedules = $this->scheduleRepository->findActiveSchedulesByDay((int)$day->format('N'), $day);
                        foreach ($schedules as $s) {
                            $totalStepTheorique += ($s->getStartTime()->diff($s->getEndTime())->h * 60) + $s->getStartTime()->diff($s->getEndTime())->i;
                        }
                    }
                }

                foreach ($globalEntries as $entry) {
                    if ($entry->getUser() === $singleUser && $entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                        $totalStepSaisiReel += ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
                    }
                }
            }

            foreach ($filteredEntries as $entry) {
                if ($entry->getStartDate() >= $stepStart && $entry->getStartDate() <= $stepEnd) {
                    $act = $entry->getActivity();
                    $actId = $act->getId();
                    $mins = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;

                    if (!isset($datasets[$actId])) {
                        $datasets[$actId] = [
                            'label' => $act->getLabel(),
                            'backgroundColor' => $act->getColor() ?? '#94a3b8',
                            'data' => array_fill(0, count($labels) - 1, 0),
                            'stack' => 'stack0'
                        ];
                    }
                    
                    if (!isset($datasets[$actId]['data'][count($labels) - 1])) {
                        $datasets[$actId]['data'][count($labels) - 1] = 0;
                    }
                    $datasets[$actId]['data'][count($labels) - 1] += round($mins / 60, 2);
                }
            }

            $remaining = max(0, ($totalStepTheorique - $totalStepSaisiReel) / 60);
            $remainingData[] = round($remaining, 2);

            foreach ($datasets as &$ds) {
                if (count($ds['data']) < count($labels)) {
                    $ds['data'][] = 0;
                }
            }
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


    /**
     * Fonction pour le graphique : Compare le temps passé spécifiquement sur des tâches de développement entre les utilisateurs
     */
    public function getUserTotalDevChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'u', 'a')->join('h.user', 'u')->leftJoin('h.activity', 'a')
            ->where('h.user IN (:users) AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('users', $users)->setParameter('start', $startDate)->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }

        $dataMap = [];
        foreach ($users as $user) {
            $dataMap[$user->getId()] = [
                'name' => $user->getFirstname() . ' ' . $user->getLastname(),
                'color' => $user->getColor() ?? '#cbd5e1',
                'minutes' => 0
            ];
        }

        foreach ($qb->getQuery()->getResult() as $entry) {
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

    /**
     * Fonction pour le graphique global : Résume le temps total passé sur chaque activité sur l'ensemble de la période
     */
    public function getActivityTotalChartRawData(array $users, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?array $projectIds = null): array
    {
        $qb = $this->hourEntryRepository->createQueryBuilder('h')
            ->select('h', 'a')->join('h.activity', 'a')
            ->where('h.user IN (:users) AND h.startDate >= :start AND h.endDate <= :end')
            ->setParameter('users', $users)->setParameter('start', $startDate)->setParameter('end', $endDate);

        if (!empty($projectIds)) {
            $qb->andWhere('h.project IN (:projectIds)')->setParameter('projectIds', $projectIds);
        }

        $totals = [];
        foreach ($qb->getQuery()->getResult() as $entry) {
            $act = $entry->getActivity();
            $mins = ($entry->getStartDate()->diff($entry->getEndDate())->h * 60) + $entry->getStartDate()->diff($entry->getEndDate())->i;
            if (!isset($totals[$act->getId()])) {
                $totals[$act->getId()] = ['label' => $act->getLabel(), 'color' => $act->getColor() ?? '#94a3b8', 'mins' => 0];
            }
            $totals[$act->getId()]['mins'] += $mins;
        }

        $labels = []; $values = []; $colors = [];
        foreach ($totals as $t) {
            $labels[] = $t['label'];
            $values[] = round($t['mins'] / 60, 2);
            $colors[] = $t['color'];
        }
        return ['labels' => $labels, 'datasets' => [['data' => $values, 'backgroundColor' => $colors]]];
    }

    /**
     * Fonction pour obtenir les statistiques de complétion de la semaine en cours
     */
    public function getCompletionStats(User $user): array
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

        $totalTheo = 0; $totalSaisie = 0;
        $holidayMap = $this->getHolidayMap();
        $period = new \DatePeriod($startOfWeek, new \DateInterval('P1D'), $endOfWeek, \DatePeriod::INCLUDE_END_DATE);

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

        return [
            'percentage' => $totalTheo > 0 ? min(100, round(($totalSaisie / $totalTheo) * 100)) : 0,
            'missingHours' => max(0, round(($totalTheo - $totalSaisie) / 60, 1)),
        ];
    }
}