<?php

namespace App\Repository;

use App\Entity\StockCordage;
use App\Entity\StockMouvementCordage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMouvementCordage>
 */
class StockMouvementCordageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMouvementCordage::class);
    }

    /**
     * @return StockMouvementCordage[]
     */
    public function findPourArticle(StockCordage $article): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.article = :article')
            ->setParameter('article', $article)
            ->orderBy('m.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
