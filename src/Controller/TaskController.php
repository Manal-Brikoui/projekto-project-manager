<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use App\Service\TaskService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    private TaskService $taskService;
    private NotificationService $notificationService;

    public function __construct(
        TaskService $taskService,
        NotificationService $notificationService
    ) {
        $this->taskService = $taskService;
        $this->notificationService = $notificationService;
    }

    //  Créer une tâche (seul créateur du projet)
    #[Route('/add', methods: ['POST'])]
    public function add(
        Request $request,
        UserRepository $userRepository,
        ProjectRepository $projectRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }
              //validation des taches 
            if (empty($data['title']) || empty($data['user_id']) || empty($data['project_id'])) {
                return $this->json(['message' => 'Données manquantes'], 400);
            }

            $user = $userRepository->find($data['user_id']);
            $project = $projectRepository->find($data['project_id']);
            
            if (!$user || !$project) {
                return $this->json(['message' => 'Utilisateur ou projet invalide'], 404);
            }

            // Vérifier que le user courant est le créateur du projet
            $creator = null;
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $u) {
                    $creator = $u;
                    break 2;
                }
            }
            //validation des permission 
            if (!$creator || $currentUser->getId() !== $creator->getId()) {
                return $this->json(['message' => 'Accès refusé : seul le créateur peut créer une tâche'], 403);
            }

            //  CRÉER ET PERSISTER LA TÂCHE 
            $task = $this->taskService->addTask($data, $user, $project);
            
            // Vérifier que la tâche a bien un ID (donc persistée)
            if (!$task->getId()) {
                throw new \Exception('La tâche n\'a pas pu être créée correctement');
            }

            // MAINTENANT créer la notification 
            if ($user->getId() !== $currentUser->getId()) {
                try {
                    $this->notificationService->addNotification(
                        " Nouvelle tâche assignée : {$task->getTitle()}",
                        'task_assigned',
                        $user,           // Destinataire
                        $task,           // Tâche
                        $currentUser,    // Expéditeur
                        $project         // Projet
                    );
                    error_log(" Notification créée pour tâche #{$task->getId()}");
                } catch (\Exception $notifError) {
                    // Log l'erreur mais ne pas faire échouer la création de tâche
                    error_log(" Erreur création notification: {$notifError->getMessage()}");
                }
            }

            return $this->json([
                'message' => 'Tâche créée',
                'id' => $task->getId()
            ], 201);
            
        } catch (\Exception $e) {
            error_log(" Erreur TaskController::add - {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    //  Modifier une tâche
    #[Route('/edit/{id}', methods: ['PUT'])]
    public function edit(
        Request $request, 
        int $id,
        UserRepository $userRepository
    ): JsonResponse {
        try {
            $task = $this->taskService->getTaskById($id);
            if (!$task) {
                return $this->json(['message' => 'Tâche non trouvée'], 404);
            }

            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }

            $project = $task->getIdProject();
            if (!$project) {
                return $this->json(['message' => 'Projet non trouvé'], 404);
            }

            // Vérifier si le user courant est le créateur du projet
            $creator = null;
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $u) {
                    $creator = $u;
                    break 2;
                }
            }
                //validation lors de modification 
            $isCreator = $creator && $currentUser->getId() === $creator->getId();
            $isAssignedUser = $task->getIdUser() && $task->getIdUser()->getId() === $currentUser->getId();

            if (!$isCreator && !$isAssignedUser) {
                return $this->json(['message' => 'Accès refusé'], 403);
            }

            $data = json_decode($request->getContent(), true);
            
            //  Sauvegarder les valeurs précédentes AVANT modification
            $previousStatus = $task->getStatus();
            $previousUserId = $task->getIdUser() ? $task->getIdUser()->getId() : null;
            $taskTitle = $task->getTitle();

            // Si ce n'est pas le créateur, seul le status peut être modifié
            if (!$isCreator) {
                $data = ['status' => $data['status'] ?? $task->getStatus()];
            }

            //  Utilisateur assigné change le statut et notifier le propriétaire
            if ($isAssignedUser && !$isCreator && isset($data['status']) && $data['status'] !== $previousStatus) {
                $statusLabels = [
                    'to_do' => 'À faire',
                    'in_progress' => 'En cours',
                    'done' => 'Terminée'
                ];
                
                $statusLabel = $statusLabels[$data['status']] ?? $data['status'];
                
                try {
                    $this->notificationService->addNotification(
                        " {$currentUser->getName()} a changé le statut de '{$taskTitle}' → {$statusLabel}",
                        'task_status_changed',
                        $creator,        
                        $task,           
                        $currentUser,    
                        $project         
                    );
                    error_log(" Notification 'task_status_changed' créée pour le propriétaire");
                } catch (\Exception $e) {
                    error_log(" Erreur notification changement statut: {$e->getMessage()}");
                }
            }

            //  Tâche terminée 
            if (isset($data['status']) && $data['status'] === 'done' && $previousStatus !== 'done') {
                if ($creator && $creator->getId() !== $currentUser->getId()) {
                    // Ne créer cette notification QUE si ce n'est PAS l'utilisateur assigné qui change le statut
    
                    if (!$isAssignedUser || $isCreator) {
                        try {
                            $this->notificationService->addNotification(
                                " Tâche terminée : {$taskTitle} par {$currentUser->getName()}",
                                'task_completed',
                                $creator,        
                                $task,           
                                $currentUser,    
                                $project         
                            );
                            error_log(" Notification 'task_completed' créée");
                        } catch (\Exception $e) {
                            error_log(" Erreur notification task_completed: {$e->getMessage()}");
                        }
                    }
                }
            }

            // Utilisateur réassigné
            if ($isCreator && isset($data['user_id']) && $data['user_id'] != $previousUserId) {
                $newUser = $userRepository->find($data['user_id']);
                
                if ($newUser && $newUser->getId() !== $currentUser->getId()) {
                    try {
                        $this->notificationService->addNotification(
                            " Tâche réassignée : {$taskTitle}",
                            'task_updated',
                            $newUser,        
                            $task,           
                            $currentUser,    
                            $project         
                        );
                        error_log(" Notification 'task_updated' (réassignation) créée");
                    } catch (\Exception $e) {
                        error_log(" Erreur notification réassignation: {$e->getMessage()}");
                    }
                }
            }

            //  Mise à jour importante (titre, description, date)
            if ($isCreator && (isset($data['title']) || isset($data['problemdescription']) || isset($data['startdate']))) {
                $assignedUser = $task->getIdUser();
                if ($assignedUser && $assignedUser->getId() !== $currentUser->getId()) {
                    try {
                        $this->notificationService->addNotification(
                            " Tâche mise à jour : {$taskTitle}",
                            'task_updated',
                            $assignedUser,   
                            $task,           
                            $currentUser,    
                            $project         
                        );
                        error_log(" Notification 'task_updated' (modification) créée");
                    } catch (\Exception $e) {
                        error_log(" Erreur notification mise à jour: {$e->getMessage()}");
                    }
                }
            }

            //  Mettre à jour la tâche APRÈS avoir créé les notifications
            $this->taskService->updateTask($task, $data);
            
            return $this->json(['message' => 'Tâche mise à jour']);
            
        } catch (\Exception $e) {
            error_log("Erreur TaskController::edit - {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors de la modification: ' . $e->getMessage()
            ], 500);
        }
    }

    //  Supprimer une tâche (seul créateur du projet)
    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskById($id);
            if (!$task) {
                return $this->json(['message' => 'Tâche non trouvée'], 404);
            }

            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }

            $project = $task->getIdProject();
            if (!$project) {
                return $this->json(['message' => 'Projet non trouvé'], 404);
            }

            $creator = null;
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $u) {
                    $creator = $u;
                    break 2;
                }
            }

            if (!$creator || $currentUser->getId() !== $creator->getId()) {
                return $this->json(['message' => 'Accès refusé : seul le créateur peut supprimer'], 403);
            }

            $this->taskService->deleteTask($task);
            
            return $this->json(['message' => 'Tâche supprimée']);
            
        } catch (\Exception $e) {
            error_log(" Erreur TaskController::delete - {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    //  Lister toutes les tâches
    #[Route('/', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }

            $tasks = $this->taskService->getAllTasks();
            
            $data = array_map(function($task) {
                try {
                    $assignedUser = $task->getIdUser();
                    $project = $task->getIdProject();
                    
                    // Récupérer le créateur du projet de manière sécurisée
                    $creator = null;
                    if ($project) {
                        try {
                            foreach ($project->getContainers() as $container) {
                                foreach ($container->getIdUser() as $u) {
                                    $creator = $u;
                                    break 2;
                                }
                            }
                        } catch (\Exception $e) {
                            $creator = null;
                        }
                    }

                    return [
                        'id' => $task->getId(),
                        'title' => $task->getTitle() ?? '',
                        'problemdescription' => $task->getProblemdescription() ?? '',
                        'status' => $task->getStatus() ?? 'to_do',
                        'startdate' => $task->getStartdate() ? $task->getStartdate()->format('Y-m-d') : null,
                        'updatedate' => $task->getUpdatedate() ? $task->getUpdatedate()->format('Y-m-d') : null,
                        'user_id' => $assignedUser ? $assignedUser->getId() : null,
                        'assigned_user' => $assignedUser ? $assignedUser->getName() : null,
                        'project_id' => $project ? $project->getId() : null,
                        'project_title' => $project ? $project->getTitle() : null,
                        'creator_id' => $creator ? $creator->getId() : null,
                    ];
                } catch (\Exception $e) {
                    return [
                        'id' => $task->getId(),
                        'title' => $task->getTitle() ?? 'Erreur',
                        'problemdescription' => 'Erreur de chargement',
                        'status' => 'to_do',
                        'startdate' => null,
                        'updatedate' => null,
                        'user_id' => null,
                        'assigned_user' => null,
                        'project_id' => null,
                        'project_title' => null,
                        'creator_id' => null,
                    ];
                }
            }, $tasks);

            return $this->json($data);
            
        } catch (\Exception $e) {
            error_log(" Erreur TaskController::list - {$e->getMessage()}");
            error_log("Stack trace: {$e->getTraceAsString()}");
            
            return $this->json([
                'message' => 'Erreur lors du chargement des tâches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //  Récupérer une tâche par ID
    #[Route('/{id}', methods: ['GET'])]
    public function getTaskById(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskById($id);
            if (!$task) {
                return $this->json(['message' => 'Tâche non trouvée'], 404);
            }

            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }

            $project = $task->getIdProject();
            if (!$project) {
                return $this->json(['message' => 'Projet associé non trouvé'], 404);
            }

            // Récupérer le créateur du projet
            $creator = null;
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $u) {
                    $creator = $u;
                    break 2;
                }
            }

            if (!$creator) {
                return $this->json(['message' => 'Créateur du projet introuvable'], 404);
            }

            $assignedUser = $task->getIdUser();

            $response = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'problemdescription' => $task->getProblemdescription(),
                'startdate' => $task->getStartdate() ? $task->getStartdate()->format('Y-m-d') : null,
                'updatedate' => $task->getUpdatedate() ? $task->getUpdatedate()->format('Y-m-d') : null,
                'user_id' => $assignedUser ? $assignedUser->getId() : null,
                'assigned_user' => $assignedUser ? $assignedUser->getName() : null,
                'project_id' => $project->getId(),
                'project_title' => $project->getTitle(),
                'creator_id' => $creator->getId(),
                'project_creator' => $creator->getName(),
            ];

            return $this->json($response);
            
        } catch (\Exception $e) {
            error_log(" Erreur TaskController::getTaskById - {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors du chargement: ' . $e->getMessage()
            ], 500);
        }
    }
}