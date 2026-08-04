<?php

namespace App\Repository;

use App\Entity\InventaireCampagne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InventaireCampagne>
 */
class InventaireCampagneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InventaireCampagne::class);
    }

    /**
     * @return InventaireCampagne[]
     */
    public function findRecentes(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
