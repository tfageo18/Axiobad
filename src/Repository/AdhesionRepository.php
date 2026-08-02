<?php

namespace App\Repository;

use App\Entity\Adhesion;
use App\Entity\Licencie;
use App\Entity\Saison;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Adhesion>
 */
class AdhesionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adhesion::class);
    }

    public function findOneByLicencieEtSaison(Licencie $licencie, Saison $saison): ?Adhesion
    {
        return $this->findOneBy(['licencie' => $licencie, 'saison' => $saison]);
    }

    /**
     * @return array<int, Adhesion> indexé par id du licencié
     */
    public function findParLicenciePourSaison(Saison $saison): array
    {
        $resultat = [];
        foreach ($this->findBy(['saison' => $saison]) as $adhesion) {
            $resultat[$adhesion->getLicencie()->getId()] = $adhesion;
        }

        return $resultat;
    }
}
