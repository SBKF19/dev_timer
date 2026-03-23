<?php

namespace App\Repository;

use App\Entity\HourEntry;
use App\Entity\User;
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

    /**
     * Récupère les saisies pour le manager avec les filtres appliqués,
     * supporte maintenant le filtrage multiple.
     */
    public function getManagerFilteredQuery(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        mixed $userId = null,
        mixed $projectId = null
    ): \Doctrine\ORM\Query {

        $qb = $this->createQueryBuilder('h')
            ->innerJoin('h.user', 'u')
            ->leftJoin('h.project', 'p')
            ->leftJoin('h.activity', 'a')
            ->addSelect('u', 'p', 'a')
            ->andWhere('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('h.startDate', 'DESC');

        // Gestion du filtre Utilisateur (Simple ou Multiple)
        if ($userId) {
            $operator = is_array($userId) ? 'IN' : '=';
            $qb->andWhere("u.id $operator (:userId)")
                ->setParameter('userId', $userId);
        }

        // Gestion du filtre Projet (Simple ou Multiple)
        if ($projectId) {
            $operator = is_array($projectId) ? 'IN' : '=';
            $qb->andWhere("p.id $operator (:projectId)")
                ->setParameter('projectId', $projectId);
        }

        return $qb->getQuery();
    }

    /**
     * Récupère les saisies d'un utilisateur pour un jour précis
     */
    public function findByUserAndDate(User $user, \DateTimeInterface $date): array
    {
        // On définit le début et la fin de la journée cible
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('h')
            ->andWhere('h.user = :user')
            // On cherche toutes les saisies dont le début est compris dans cette journée
            ->andWhere('h.startDate BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->orderBy('h.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Méthode dédiée à la liste filtrée et triable du Manager
     */
    public function getQueryForManagerList(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        mixed $userId = null,
        mixed $projectId = null
    ): \Doctrine\ORM\Query {

        $qb = $this->createQueryBuilder('h')
            ->innerJoin('h.user', 'u')
            ->leftJoin('h.project', 'p')
            ->leftJoin('h.activity', 'a')
            ->addSelect('u', 'p', 'a')
            ->where('h.startDate >= :start')
            ->andWhere('h.endDate <= :end')
            ->setParameter('start', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('end', $endDate->format('Y-m-d 23:59:59'));

        if ($userId) {
            $operator = is_array($userId) ? 'IN' : '=';
            $qb->andWhere("u.id $operator (:userId)")->setParameter('userId', $userId);
        }

        if ($projectId) {
            $operator = is_array($projectId) ? 'IN' : '=';
            $qb->andWhere("p.id $operator (:projectId)")->setParameter('projectId', $projectId);
        }

        return $qb->getQuery();
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
