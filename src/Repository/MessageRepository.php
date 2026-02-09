<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    //Récupérer toutes les conversations d'un utilisateur
    public function findConversations(User $user): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('DISTINCT 
                CASE 
                    WHEN m.sender = :user THEN IDENTITY(m.receiver)
                    ELSE IDENTITY(m.sender)
                END as user_id')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->getQuery();

        return $qb->getResult();
    }

    // Récupérer les messages entre deux utilisateurs
    public function findConversationBetween(User $user1, User $user2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->orderBy('m.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Compter les messages non lus pour un utilisateur
    
    public function countUnreadMessages(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // Marquer tous les messages d'une conversation comme lus
    public function markConversationAsRead(User $receiver, User $sender): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', true)
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.isRead = false')
            ->setParameter('receiver', $receiver)
            ->setParameter('sender', $sender)
            ->getQuery()
            ->execute();
    }
}
