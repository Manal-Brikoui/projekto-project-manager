<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.containers', 'c')
            ->innerJoin('c.idUser', 'u')
            ->andWhere('u = :user')
            ->setParameter('user', $user)
            ->orderBy('p.startdate', 'DESC')
            ->getQuery()
            ->getResult();
    }

   
    
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.startdate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime un projet par son ID
     *
     * @param int $id
     * @return int Nombre de projets supprimés
     */
    public function deleteById(int $id): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
