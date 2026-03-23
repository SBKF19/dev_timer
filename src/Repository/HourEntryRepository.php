<?php

namespace App\Repository;

use App\Entity\HourEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HourEntry>
 */
class HourEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HourEntry::class);
    }

    public function hasEntriesForDate($user, \DateTimeInterface $date): bool
    {
        // On transforme l'interface en un vrai objet DateTimeImmutable
        $dateImmutable = \DateTimeImmutable::createFromInterface($date);

        // L'éditeur sait maintenant que $dateImmutable possède la méthode setTime()
        $startOfDay = $dateImmutable->setTime(0, 0, 0);
        $endOfDay = $dateImmutable->setTime(23, 59, 59);

        $count = $this->createQueryBuilder('h') // 'h' pour HourEntry
            ->select('COUNT(h.id)')
            ->andWhere('h.user = :user')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.startDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0; // Renvoie vrai s'il y a au moins 1 saisie
    }

    //    /**
    //     * @return HourEntry[] Returns an array of HourEntry objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('h.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?HourEntry
    //    {
    //        return $this->createQueryBuilder('h')
    //            ->andWhere('h.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
