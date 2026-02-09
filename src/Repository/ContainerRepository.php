<?php

namespace App\Repository;

use App\Entity\Container;
use App\Entity\User;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContainerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Container::class);
    }

    
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere(':user MEMBER OF c.idUser')
            ->setParameter('user', $user)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

   
    public function findOneByProject(Project $project): ?Container
    {
        return $this->createQueryBuilder('c')
            ->andWhere(':project MEMBER OF c.idProject')
            ->setParameter('project', $project)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

   
    public function deleteById(int $id): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();
    }
}
