<?php

namespace App\Repository;

use App\Entity\Schedule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Schedule>
 */
class ScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schedule::class);
    }

    public function findActiveSchedulesByDay(int $dayOfWeek, \DateTimeInterface $date): array
    {
        $dayStart = (clone $date)->setTime(0, 0, 0);
        $dayEnd = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->where('s.dayOfWeek = :day')
            ->andWhere('s.createdAt <= :dayEnd')
            ->andWhere('(s.deleted_at IS NULL OR s.deleted_at > :dayStart)') 
            ->setParameter('day', $dayOfWeek)
            ->setParameter('dayEnd', $dayEnd)
            ->setParameter('dayStart', $dayStart)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Schedule[] Returns an array of Schedule objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Schedule
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
