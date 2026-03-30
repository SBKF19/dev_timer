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

    public function getWeeklyStats(User $user, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $totalTheoreticalMinutes = 0;
        $totalSaisieMinutes = 0;

        // 1. Calcul théorique (Schedules)
        $schedules = $this->scheduleRepository->findAll();
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);

        foreach ($period as $date) {
            $dayOfWeek = (int) $date->format('N');

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
                if ($this->holidayRepository->findOneBy(['date' => $date]))
                    continue;

                $dayOfWeek = (int) $date->format('N');
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
                        $dayOfWeek = (int) $day->format('N');
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
}