<?php

namespace App\Repository;

use App\Entity\StockMouvementVetement;
use App\Entity\StockVetement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMouvementVetement>
 */
class StockMouvementVetementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMouvementVetement::class);
    }

    /**
     * @return StockMouvementVetement[]
     */
    public function findPourArticle(StockVetement $article): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.article = :article')
            ->setParameter('article', $article)
            ->orderBy('m.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
