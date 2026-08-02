<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Presence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Presence>
 */
class PresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presence::class);
    }

    public function findOneByCreneauAndLicencie(Creneau $creneau, Licencie $licencie): ?Presence
    {
        return $this->findOneBy(['creneau' => $creneau, 'licencie' => $licencie]);
    }
}
