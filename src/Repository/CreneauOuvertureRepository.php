<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\CreneauOuverture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreneauOuverture>
 */
class CreneauOuvertureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreneauOuverture::class);
    }

    public function findOneByCreneauEtDate(Creneau $creneau, \DateTimeImmutable $date): ?CreneauOuverture
    {
        return $this->findOneBy(['creneau' => $creneau, 'date' => $date]);
    }
}
