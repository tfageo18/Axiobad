<?php

namespace App\Repository;

use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saison>
 */
class SaisonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saison::class);
    }

    /**
     * @return Saison[]
     */
    public function findAllTrieesParDate(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findEnCours(): ?Saison
    {
        $aujourdhui = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('s')
            ->andWhere('s.dateDebut <= :aujourdhui')
            ->andWhere('s.dateFin >= :aujourdhui')
            ->setParameter('aujourdhui', $aujourdhui)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
