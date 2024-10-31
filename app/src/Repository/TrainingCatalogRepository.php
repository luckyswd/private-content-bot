<?php

namespace App\Repository;

use App\Entity\TrainingCatalog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingCatalog>
 *
 * @method TrainingCatalog|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrainingCatalog|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrainingCatalog[]    findAll()
 * @method TrainingCatalog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrainingCatalogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingCatalog::class);
    }

    public function getNextCategories(TrainingCatalog $trainingCatalog): array {
        return $this->createQueryBuilder('c')
            ->andWhere('c.subCatalog = :subCatalog')
            ->setParameter('subCatalog', $trainingCatalog)
            ->getQuery()
            ->getResult();
    }
}
