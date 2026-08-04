<?php

namespace App\Repository;

use App\Entity\MatchInterclub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchInterclub>
 */
class MatchInterclubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchInterclub::class);
    }
}
