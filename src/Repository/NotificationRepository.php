<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.idUser = :user')
            ->setParameter('user', $user)
            ->orderBy('n.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.idTask = :task')
            ->setParameter('task', $task)
            ->orderBy('n.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

   
    public function deleteById(int $id): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
