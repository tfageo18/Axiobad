<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\CreneauException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreneauException>
 */
class CreneauExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreneauException::class);
    }

    public function findOneByCreneauEtDate(Creneau $creneau, \DateTimeImmutable $date): ?CreneauException
    {
        return $this->findOneBy(['creneau' => $creneau, 'date' => $date]);
    }

    /**
     * @return CreneauException[]
     */
    public function findEntre(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :debut AND e.date <= :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }
}
