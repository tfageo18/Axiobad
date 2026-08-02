<?php

namespace App\Repository;

use App\Entity\PaiementAdhesion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaiementAdhesion>
 */
class PaiementAdhesionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaiementAdhesion::class);
    }
}
