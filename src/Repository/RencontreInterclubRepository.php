<?php

namespace App\Repository;

use App\Entity\RencontreInterclub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RencontreInterclub>
 */
class RencontreInterclubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RencontreInterclub::class);
    }
}
