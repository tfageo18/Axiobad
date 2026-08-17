<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Licencie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findEntre(Licencie $a, Licencie $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('(c.participant1 = :a AND c.participant2 = :b) OR (c.participant1 = :b AND c.participant2 = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Conversations d'un licencié, les plus récemment actives en premier.
     *
     * @return Conversation[]
     */
    public function findPourLicencie(Licencie $licencie): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.participant1 = :licencie OR c.participant2 = :licencie')
            ->setParameter('licencie', $licencie)
            ->orderBy('c.dernierMessageLe', 'DESC')
            ->addOrderBy('c.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
