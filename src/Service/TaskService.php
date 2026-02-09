<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Project;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskService
{
    private EntityManagerInterface $em;
    private TaskRepository $taskRepository;
    private ?LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $em, 
        TaskRepository $taskRepository,
        ?LoggerInterface $logger = null
    ) {
        $this->em = $em;
        $this->taskRepository = $taskRepository;
        $this->logger = $logger;
    }

    //  Lister toutes les tâches
    public function getAllTasks(): array
    {
        try {
            return $this->taskRepository->findAll();
        } catch (\Exception $e) {
            $this->logError('getAllTasks', $e);
            throw new \RuntimeException('Erreur lors de la récupération des tâches: ' . $e->getMessage());
        }
    }

    //  Récupérer une tâche par ID
    public function getTaskById(int $id): ?Task
    {
        try {
            return $this->taskRepository->find($id);
        } catch (\Exception $e) {
            $this->logError('getTaskById', $e);
            return null;
        }
    }

    //  Créer une tâche
    public function addTask(array $data, User $user, Project $project): Task
    {
        try {
            // Validation des données
            if (empty($data['title'])) {
                throw new \InvalidArgumentException('Le titre est obligatoire');
            }

            $task = new Task();
            $task->setTitle($data['title']);
            $task->setStatus($data['status'] ?? 'to_do');
            $task->setProblemdescription($data['problemdescription'] ?? null);
            $task->setIdUser($user);
            $task->setIdProject($project);

            // Gestion des dates avec valeurs par défaut
            if (!empty($data['startdate'])) {
                try {
                    $task->setStartdate(new \DateTime($data['startdate']));
                } catch (\Exception $e) {
                    $this->logError('addTask - startdate invalide', $e);
                    $task->setStartdate(new \DateTime());
                }
            } else {
                $task->setStartdate(new \DateTime());
            }

            if (!empty($data['updatedate'])) {
                try {
                    $task->setUpdatedate(new \DateTime($data['updatedate']));
                } catch (\Exception $e) {
                    $this->logError('addTask - updatedate invalide', $e);
                    $task->setUpdatedate(new \DateTime());
                }
            } else {
                $task->setUpdatedate(new \DateTime());
            }

         
            $this->em->persist($task);
            $this->em->flush();

            //  Vérifier que l'ID existe bien
            if (!$task->getId()) {
                throw new \RuntimeException('La tâche n\'a pas reçu d\'ID après le flush');
            }

            $this->logInfo('addTask', "Tâche #{$task->getId()} créée avec succès");

            return $task;

        } catch (\Exception $e) {
            $this->logError('addTask', $e);
            throw new \RuntimeException('Erreur lors de la création de la tâche: ' . $e->getMessage());
        }
    }

    //  Mettre à jour une tâche
    public function updateTask(Task $task, array $data): Task
    {
        try {
            if (isset($data['title']) && !empty($data['title'])) {
                $task->setTitle($data['title']);
            }

            if (isset($data['status'])) {
                $task->setStatus($data['status']);
            }

            if (isset($data['problemdescription'])) {
                $task->setProblemdescription($data['problemdescription']);
            }

            if (isset($data['startdate'])) {
                try {
                    $task->setStartdate($data['startdate'] ? new \DateTime($data['startdate']) : null);
                } catch (\Exception $e) {
                    $this->logError('updateTask - startdate invalide', $e);
                }
            }

            if (isset($data['updatedate'])) {
                try {
                    $task->setUpdatedate($data['updatedate'] ? new \DateTime($data['updatedate']) : new \DateTime());
                } catch (\Exception $e) {
                    $this->logError('updateTask - updatedate invalide', $e);
                    $task->setUpdatedate(new \DateTime());
                }
            } else {
                // Toujours mettre à jour la date de modification
                $task->setUpdatedate(new \DateTime());
            }

            $this->em->flush();

            $this->logInfo('updateTask', "Tâche #{$task->getId()} mise à jour avec succès");

            return $task;

        } catch (\Exception $e) {
            $this->logError('updateTask', $e);
            throw new \RuntimeException('Erreur lors de la mise à jour de la tâche: ' . $e->getMessage());
        }
    }

    // Supprimer une tâche
    public function deleteTask(Task $task): void
    {
        try {
            $taskId = $task->getId();
            $this->em->remove($task);
            $this->em->flush();
            
            $this->logInfo('deleteTask', "Tâche #{$taskId} supprimée avec succès");
        } catch (\Exception $e) {
            $this->logError('deleteTask', $e);
            throw new \RuntimeException('Erreur lors de la suppression de la tâche: ' . $e->getMessage());
        }
    }

    //  Tâches d'un utilisateur
    public function getTasksByUser(User $user): array
    {
        try {
            $tasks = $user->getTasks();
            return $tasks ? $tasks->toArray() : [];
        } catch (\Exception $e) {
            $this->logError('getTasksByUser', $e);
            return [];
        }
    }

    // Tâches d'un projet
    public function getTasksByProject(Project $project): array
    {
        try {
            $tasks = $project->getTasks();
            return $tasks ? $tasks->toArray() : [];
        } catch (\Exception $e) {
            $this->logError('getTasksByProject', $e);
            return [];
        }
    }

    //  Format JSON sécurisé 
    public function formatTask(Task $task): array
    {
        try {
            $user = null;
            $project = null;
            $creator = null;

            try {
                $user = $task->getIdUser();
            } catch (\Exception $e) {
                $this->logError('formatTask - getIdUser', $e);
            }

            try {
                $project = $task->getIdProject();
            } catch (\Exception $e) {
                $this->logError('formatTask - getIdProject', $e);
            }

            if ($project) {
                try {
                    foreach ($project->getContainers() as $container) {
                        foreach ($container->getIdUser() as $u) {
                            $creator = $u;
                            break 2;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logError('formatTask - getCreator', $e);
                }
            }

            return [
                'id' => $task->getId(),
                'title' => $task->getTitle() ?? '',
                'status' => $task->getStatus() ?? 'to_do',
                'problemdescription' => $task->getProblemdescription() ?? '',
                'startdate' => $task->getStartdate() ? $task->getStartdate()->format('Y-m-d') : null,
                'updatedate' => $task->getUpdatedate() ? $task->getUpdatedate()->format('Y-m-d') : null,
                'user_id' => $user ? $user->getId() : null,
                'assigned_user' => $user ? $user->getName() : null,
                'project_id' => $project ? $project->getId() : null,
                'project_title' => $project ? $project->getTitle() : null,
                'creator_id' => $creator ? $creator->getId() : null,
            ];

        } catch (\Exception $e) {
            $this->logError('formatTask', $e);
            
            return [
                'id' => $task->getId(),
                'title' => 'Erreur',
                'status' => 'to_do',
                'problemdescription' => 'Erreur de chargement',
                'startdate' => null,
                'updatedate' => null,
                'user_id' => null,
                'assigned_user' => null,
                'project_id' => null,
                'project_title' => null,
                'creator_id' => null,
            ];
        }
    }

    //  Logger les erreurs
    private function logError(string $method, \Exception $e): void
    {
        $message = sprintf(
            ' TaskService::%s - %s (File: %s, Line: %d)',
            $method,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        if ($this->logger) {
            $this->logger->error($message, ['exception' => $e]);
        } else {
            error_log($message);
        }
    }

    //  Logger les infos
    private function logInfo(string $method, string $message): void
    {
        $fullMessage = sprintf(' TaskService::%s - %s', $method, $message);

        if ($this->logger) {
            $this->logger->info($fullMessage);
        } else {
            error_log($fullMessage);
        }
    }
}