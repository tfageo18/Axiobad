<?php

namespace App\Repository;

use App\Entity\NotificationEnvoyee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationEnvoyee>
 */
class NotificationEnvoyeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationEnvoyee::class);
    }

    /**
     * @return NotificationEnvoyee[]
     */
    public function rechercher(?int $destinataireId, int $limite = 200): array
    {
        $qb = $this->createQueryBuilder('n')->orderBy('n.envoyeLe', 'DESC')->setMaxResults($limite);

        if ($destinataireId) {
            $qb->andWhere('n.destinataire = :destinataire')->setParameter('destinataire', $destinataireId);
        }

        return $qb->getQuery()->getResult();
    }
}
