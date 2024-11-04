<?php

namespace App\Repository;

use App\Entity\PostTraining;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostTraining>
 *
 * @method PostTraining|null find($id, $lockMode = null, $lockVersion = null)
 * @method PostTraining|null findOneBy(array $criteria, array $orderBy = null)
 * @method PostTraining[]    findAll()
 * @method PostTraining[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PostTrainingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostTraining::class);
    }
}
