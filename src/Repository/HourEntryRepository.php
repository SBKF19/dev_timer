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

    public function save(HourEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HourEntry $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    /**
     * Vérifie s'il existe une saisie qui chevauche la période donnée pour un utilisateur
     */
    public function hasOverlappingEntry(
        \App\Entity\User $user, 
        \DateTimeInterface $start, 
        \DateTimeInterface $end, 
        ?int $ignoreId = null
    ): bool {
        $qb = $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->where('h.user = :user')
            // Logique de chevauchement : (Debut1 < Fin2) ET (Fin1 > Debut2)
            ->andWhere('h.startDate < :end')
            ->andWhere('h.endDate > :start')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        // Si on est en mode édition, on exclut l'ID actuel
        if ($ignoreId) {
            $qb->andWhere('h.id != :id')
               ->setParameter('id', $ignoreId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
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
