<?php

namespace App\Repository;

use App\Entity\LienFamilial;
use App\Entity\Licencie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LienFamilial>
 */
class LienFamilialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LienFamilial::class);
    }

    /**
     * Tous les liens (en attente ou acceptés) impliquant ce licencié, comme demandeur ou cible.
     *
     * @return LienFamilial[]
     */
    public function findPourLicencie(Licencie $licencie): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.demandeur = :licencie OR l.cible = :licencie')
            ->setParameter('licencie', $licencie)
            ->orderBy('l.demandeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Un lien (peu importe le sens, en attente ou accepté) existe-t-il déjà entre ces deux
     * licenciés ?
     */
    public function existeEntre(Licencie $a, Licencie $b): bool
    {
        $resultat = $this->createQueryBuilder('l')
            ->andWhere('(l.demandeur = :a AND l.cible = :b) OR (l.demandeur = :b AND l.cible = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $resultat;
    }
}
