<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Licencie;
use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Les derniers messages d'une conversation, du plus ancien au plus récent (ordre
     * d'affichage), limités aux `$limite` plus récents pour éviter de tout recharger sur de
     * longues conversations.
     *
     * Si `$pour` est fourni et que ce participant a été ajouté "sans l'historique", les messages
     * antérieurs à son ajout dans la conversation sont exclus.
     *
     * @return Message[]
     */
    public function findRecents(Conversation $conversation, int $limite = 200, ?Licencie $pour = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation);

        $depuis = $pour ? $conversation->getVoitHistoriqueDepuisPour($pour) : null;
        if ($depuis) {
            $qb->andWhere('m.envoyeLe >= :depuis')->setParameter('depuis', $depuis);
        }

        $messages = $qb
            ->orderBy('m.envoyeLe', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();

        return array_reverse($messages);
    }
}
