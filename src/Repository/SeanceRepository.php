<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Seance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Seance>
 */
class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    public function findOneByCreneauEtDate(Creneau $creneau, \DateTimeImmutable $date): ?Seance
    {
        return $this->findOneBy(['creneau' => $creneau, 'date' => $date]);
    }

    /**
     * Dernière séance renseignée (avec contenu) de ce créneau, avant une date donnée — sert de
     * base pour "dupliquer la séance précédente".
     */
    public function findDernierePourCreneau(Creneau $creneau, \DateTimeImmutable $avant): ?Seance
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.creneau = :creneau')
            ->andWhere('s.date < :avant')
            ->andWhere('s.objectifs IS NOT NULL OR s.contenu IS NOT NULL')
            ->setParameter('creneau', $creneau)
            ->setParameter('avant', $avant)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
