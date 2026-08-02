<?php

namespace App\Repository;

use App\Entity\StockMouvementVolant;
use App\Entity\StockVolant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMouvementVolant>
 */
class StockMouvementVolantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMouvementVolant::class);
    }

    /**
     * @return StockMouvementVolant[]
     */
    public function findPourArticle(StockVolant $article): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.article = :article')
            ->setParameter('article', $article)
            ->orderBy('m.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
