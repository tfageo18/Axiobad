<?php

namespace App\Repository;

use App\Entity\JournalAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalAudit>
 */
class JournalAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalAudit::class);
    }

    /**
     * @return JournalAudit[]
     */
    public function rechercher(?string $action, ?int $utilisateurId, int $limite = 200): array
    {
        $qb = $this->createQueryBuilder('j')->orderBy('j.horodatage', 'DESC')->setMaxResults($limite);

        if ($action) {
            $qb->andWhere('j.action = :action')->setParameter('action', $action);
        }
        if ($utilisateurId) {
            $qb->andWhere('j.utilisateur = :utilisateur')->setParameter('utilisateur', $utilisateurId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return string[]
     */
    public function findActionsDistinctes(): array
    {
        $resultats = $this->createQueryBuilder('j')
            ->select('DISTINCT j.action')
            ->orderBy('j.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $resultats;
    }
}
