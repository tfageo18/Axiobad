<?php

namespace App\Repository;

use App\Entity\ParametresClub;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParametresClub>
 */
class ParametresClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParametresClub::class);
    }

    /**
     * Ligne unique de réglages (id=1), créée à la volée si elle n'existe pas encore.
     */
    public function obtenir(): ParametresClub
    {
        $parametres = $this->find(1);
        if (!$parametres) {
            $parametres = new ParametresClub();
            $em = $this->getEntityManager();
            $em->persist($parametres);
            $em->flush();
        }

        return $parametres;
    }
}
