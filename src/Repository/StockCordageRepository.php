<?php

namespace App\Repository;

use App\Entity\StockCordage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockCordage>
 */
class StockCordageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockCordage::class);
    }

    /**
     * Articles avec du stock réellement disponible (quantité > 0) — c'est dans cette liste que
     * les licenciés choisissent leur cordage à la dépose, pour ne jamais demander un cordage que
     * le club n'a pas.
     *
     * @return StockCordage[]
     */
    public function findDisponibles(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.quantite > 0')
            ->orderBy('s.marque', 'ASC')
            ->addOrderBy('s.modele', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
