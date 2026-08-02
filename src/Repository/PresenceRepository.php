<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Presence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Presence>
 */
class PresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presence::class);
    }

    public function findOneByCreneauLicencieEtDate(Creneau $creneau, Licencie $licencie, \DateTimeImmutable $date): ?Presence
    {
        return $this->findOneBy(['creneau' => $creneau, 'licencie' => $licencie, 'date' => $date]);
    }

    /**
     * @return Presence[]
     */
    public function findPourCreneauEtDate(Creneau $creneau, \DateTimeImmutable $date): array
    {
        return $this->findBy(['creneau' => $creneau, 'date' => $date]);
    }

    /**
     * @return Presence[]
     */
    public function findPourLicencieEntre(Licencie $licencie, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.licencie = :licencie')
            ->andWhere('p.date BETWEEN :debut AND :fin')
            ->setParameter('licencie', $licencie)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }
}
