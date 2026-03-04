<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }


    /**
     * @param array $filters Tableau contenant les critères
     * @return User[]
     */
    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.role', 'r') // Utile si on veut filtrer par nom de rôle
            ->addSelect('r');

        // Filtre par nom ou prénom (recherche textuelle)
        if (!empty($filters['search'])) {
            $qb->andWhere('u.firstname LIKE :search OR u.lastname LIKE :search OR u.email LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Filtre par statut (actif/inactif)
        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->andWhere('u.status = :status')
               ->setParameter('status', $filters['status']);
        }

        // Filtre par rôle (ID du rôle)
        if (!empty($filters['role'])) {
            $qb->andWhere('r.id = :roleId')
               ->setParameter('roleId', $filters['role']);
        }

        // Exclure les utilisateurs supprimés (Soft Delete)
        $qb->andWhere('u.deleted_at IS NULL');

        $qb->orderBy('u.lastname', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function save(User $user, bool $flush = false): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);

        if ($flush) {
            $em->flush();
        }
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
