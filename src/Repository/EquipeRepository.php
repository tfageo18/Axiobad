<?php

namespace App\Repository;

use App\Entity\Equipe;
use App\Entity\Licencie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipe>
 */
class EquipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipe::class);
    }

    /**
     * @return Equipe[] équipes dont le licencié est membre, triées par nom
     */
    public function findByMembre(Licencie $licencie): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin('e.membres', 'm')
            ->andWhere('m = :licencie')
            ->setParameter('licencie', $licencie)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
