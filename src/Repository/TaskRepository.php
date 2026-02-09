<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

  
    public function findAll(): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('u', 'p')
                ->orderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findAll - ' . $e->getMessage());
            return [];
        }
    }

    
    public function findWithRelations(int $id): ?Task
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('u', 'p')
                ->where('t.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findWithRelations - ' . $e->getMessage());
            return null;
        }
    }

    
    public function findByUser(User $user): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('p')
                ->where('t.idUser = :user')
                ->setParameter('user', $user)
                ->orderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findByUser - ' . $e->getMessage());
            return [];
        }
    }

    public function findByProject(Project $project): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->addSelect('u')
                ->where('t.idProject = :project')
                ->setParameter('project', $project)
                ->orderBy('t.status', 'ASC')
                ->addOrderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findByProject - ' . $e->getMessage());
            return [];
        }
    }

   
    public function findByStatus(string $status): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('u', 'p')
                ->where('t.status = :status')
                ->setParameter('status', $status)
                ->orderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findByStatus - ' . $e->getMessage());
            return [];
        }
    }

    public function findByProjectAndStatus(Project $project, string $status): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->addSelect('u')
                ->where('t.idProject = :project')
                ->andWhere('t.status = :status')
                ->setParameter('project', $project)
                ->setParameter('status', $status)
                ->orderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findByProjectAndStatus - ' . $e->getMessage());
            return [];
        }
    }

    
    public function countByStatusForProject(Project $project): array
    {
        try {
            $result = $this->createQueryBuilder('t')
                ->select('t.status, COUNT(t.id) as count')
                ->where('t.idProject = :project')
                ->setParameter('project', $project)
                ->groupBy('t.status')
                ->getQuery()
                ->getResult();

            $counts = [
                'to_do' => 0,
                'in_progress' => 0,
                'done' => 0
            ];

            foreach ($result as $row) {
                $counts[$row['status']] = (int)$row['count'];
            }

            return $counts;
        } catch (\Exception $e) {
            error_log(' TaskRepository::countByStatusForProject - ' . $e->getMessage());
            return ['to_do' => 0, 'in_progress' => 0, 'done' => 0];
        }
    }

    
    public function findRecent(int $limit = 10): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('u', 'p')
                ->orderBy('t.updatedate', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::findRecent - ' . $e->getMessage());
            return [];
        }
    }

    public function deleteById(int $id): int
    {
        try {
            return $this->createQueryBuilder('t')
                ->delete()
                ->where('t.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->execute();
        } catch (\Exception $e) {
            error_log(' TaskRepository::deleteById - ' . $e->getMessage());
            return 0;
        }
    }

    public function search(string $searchTerm): array
    {
        try {
            return $this->createQueryBuilder('t')
                ->leftJoin('t.idUser', 'u')
                ->leftJoin('t.idProject', 'p')
                ->addSelect('u', 'p')
                ->where('t.title LIKE :search')
                ->orWhere('t.problemdescription LIKE :search')
                ->setParameter('search', '%' . $searchTerm . '%')
                ->orderBy('t.updatedate', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            error_log(' TaskRepository::search - ' . $e->getMessage());
            return [];
        }
    }
}