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

    /**
     * La prochaine saison à venir (dateDebut dans le futur, la plus proche) — utile pour afficher
     * l'adhésion d'un licencié qui a déjà réglé sa saison avant qu'elle ne commence.
     */
    public function findProchaine(): ?Saison
    {
        $aujourdhui = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('s')
            ->andWhere('s.dateDebut > :aujourdhui')
            ->setParameter('aujourdhui', $aujourdhui)
            ->orderBy('s.dateDebut', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
