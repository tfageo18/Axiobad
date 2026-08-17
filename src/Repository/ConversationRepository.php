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

    /**
     * Conversation à deux (sans titre de groupe) déjà existante entre exactement ces deux
     * licenciés, pour éviter d'en recréer une à chaque fois qu'on démarre une discussion privée
     * classique. Les discussions de groupe (3 participants ou plus) ne sont jamais réutilisées.
     */
    public function findEntre(Licencie $a, Licencie $b): ?Conversation
    {
        $candidates = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p1')
            ->innerJoin('c.participants', 'p2')
            ->andWhere('p1.licencie = :a')
            ->andWhere('p2.licencie = :b')
            ->andWhere('c.titre IS NULL')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->getQuery()
            ->getResult();

        foreach ($candidates as $conversation) {
            if (2 === $conversation->getParticipants()->count()) {
                return $conversation;
            }
        }

        return null;
    }

    /**
     * Conversations d'un licencié, les plus récemment actives en premier.
     *
     * @return Conversation[]
     */
    public function findPourLicencie(Licencie $licencie): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->andWhere('p.licencie = :licencie')
            ->setParameter('licencie', $licencie)
            ->orderBy('c.dernierMessageLe', 'DESC')
            ->addOrderBy('c.creeLe', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
