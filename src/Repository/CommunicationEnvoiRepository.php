<?php

namespace App\Repository;

use App\Entity\CommunicationEnvoi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunicationEnvoi>
 */
class CommunicationEnvoiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunicationEnvoi::class);
    }

    /**
     * @return CommunicationEnvoi[]
     */
    public function findRecentes(int $limite = 50): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.envoyeLe', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
