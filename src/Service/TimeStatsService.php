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
}