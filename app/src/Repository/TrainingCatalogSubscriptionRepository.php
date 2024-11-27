<?php

namespace App\Repository;

use App\Entity\TrainingCatalogSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingCatalogSubscription>
 *
 * @method TrainingCatalogSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrainingCatalogSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrainingCatalogSubscription[]    findAll()
 * @method TrainingCatalogSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrainingCatalogSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingCatalogSubscription::class);
    }

    public function save(TrainingCatalogSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TrainingCatalogSubscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return TrainingCatalogSubscription[] Returns an array of TrainingCatalogSubscription objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TrainingCatalogSubscription
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
