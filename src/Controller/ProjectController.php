<?php

namespace App\Controller;

use App\Entity\Project;
use App\Service\ProjectService;
use App\Service\UserService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
class ProjectController extends AbstractController
{
    public function __construct(
        private ProjectService $projectService,
        private UserService $userService,
        private NotificationService $notificationService
    ) {}

    // ROUTES TWIG
    #[Route('/dashboard', name: 'project_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('project/dashboard.html.twig');
    }

    #[Route('/index', name: 'project_list', methods: ['GET'])]
    public function projectList(): Response
    {
        return $this->render('project/index.html.twig');
    }

    #[Route('/show/{id}', name: 'project_show_page', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showPage(int $id): Response
    {
        return $this->render('project/show.html.twig');
    }

    #[Route('/{id}/tasks', name: 'project_tasks_page', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function tasksPage(int $id): Response
    {
        return $this->render('task/tasks.html.twig');
    }

    #[Route('/{id}/tasks-data', name: 'project_tasks_data', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getTasksData(Project $project): Response
    {
        try {
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());
            
            // Vérifier que l'utilisateur est membre du projet
            $isMember = false;
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $user) {
                    if ($user->getId() === $currentUserEntity->getId()) {
                        $isMember = true;
                        break 2;
                    }
                }
            }

            if (!$isMember) {
                return $this->json(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
            }

            $tasks = [];
            if (method_exists($project, 'getTasks')) {
                foreach ($project->getTasks() as $task) {
                    try {
                        $assignedUser = $task->getIdUser();
                        $tasks[] = [
                            'id' => $task->getId(),
                            'title' => $task->getTitle() ?? '',
                            'problemdescription' => $task->getProblemdescription() ?? '',
                            'status' => $task->getStatus() ?? 'to_do',
                            'startdate' => $task->getStartdate() ? $task->getStartdate()->format('Y-m-d') : null,
                            'updatedate' => $task->getUpdatedate() ? $task->getUpdatedate()->format('Y-m-d') : null,
                            'user_id' => $assignedUser ? $assignedUser->getId() : null,
                            'project_id' => $project->getId(),
                            'assigned_user' => $assignedUser ? $assignedUser->getName() : 'Non assigné'
                        ];
                    } catch (\Exception $taskError) {
                        continue;
                    }
                }
            }

            return $this->json($tasks);
            
        } catch (\Exception $e) {
            error_log('[ERROR] getTasksData: ' . $e->getMessage());
            return $this->json([
                'error' => 'Erreur lors du chargement des tâches',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ROUTES API JSON
    #[Route('/', name: 'project_index', methods: ['GET'])]
    public function index(): Response
    {
        $projects = $this->projectService->getAllProjects();

        $data = array_map(function(Project $p) {
            
            $creatorId = $p->getCreatorId();
            $creatorName = null;
            
            // Récupérer le nom du créateur
            try {
                $creator = $this->userService->getUserById($creatorId);
                $creatorName = $creator ? $creator->getName() : null;
            } catch (\Exception $e) {
                $creatorName = null;
            }

            // Récupérer tous les membres
            $allMemberIds = [];
            $allMembers = [];
            foreach ($p->getContainers() as $container) {
                foreach ($container->getIdUser() as $user) {
                    if (!in_array($user->getId(), $allMemberIds)) {
                        $allMemberIds[] = $user->getId();
                        $allMembers[] = [
                            'id' => $user->getId(),
                            'name' => $user->getName(),
                            'email' => $user->getEmail()
                        ];
                    }
                }
            }

            return [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'description' => $p->getDescription(),
                'status' => $p->getStatus(),
                'evaluation' => $p->getEvaluation(),
                'startdate' => $p->getStartdate()?->format('Y-m-d'),
                'enddate' => $p->getEnddate()?->format('Y-m-d'),
                'creator_id' => $creatorId,
                'creator_name' => $creatorName,
                'creator_ids' => $allMemberIds,
                'members' => $allMembers,
            ];
        }, $projects);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'project_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: -1)]
    public function show(Project $project): Response
    {
        $creatorId = $project->getCreatorId();
        $creatorName = null;
        
        try {
            $creator = $this->userService->getUserById($creatorId);
            $creatorName = $creator ? $creator->getName() : null;
        } catch (\Exception $e) {
            $creatorName = null;
        }

        // Récupérer tous les membres
        $allMemberIds = [];
        $allMembers = [];
        foreach ($project->getContainers() as $container) {
            foreach ($container->getIdUser() as $user) {
                if (!in_array($user->getId(), $allMemberIds)) {
                    $allMemberIds[] = $user->getId();
                    $allMembers[] = [
                        'id' => $user->getId(),
                        'name' => $user->getName(),
                        'email' => $user->getEmail()
                    ];
                }
            }
        }

        return $this->json([
            'id' => $project->getId(),
            'title' => $project->getTitle(),
            'description' => $project->getDescription(),
            'status' => $project->getStatus(),
            'evaluation' => $project->getEvaluation(),
            'startdate' => $project->getStartdate()?->format('Y-m-d'),
            'enddate' => $project->getEnddate()?->format('Y-m-d'),
            'creator_id' => $creatorId,
            'creator_name' => $creatorName,
            'creator_ids' => $allMemberIds,
            'members' => $allMembers,
        ]);
    }

    #[Route('/add', name: 'project_add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());

        $data = json_decode($request->getContent(), true);
        if (empty($data['title']) || empty($data['description']) || empty($data['startdate']) || empty($data['enddate'])) {
            return $this->json(['error' => 'Champs manquants'], Response::HTTP_BAD_REQUEST);
        }

        $data['idUsers'] = [$currentUserEntity];
        $project = $this->projectService->addProject($data);

        return $this->json([
            'message' => 'Projet créé',
            'id' => $project->getId()
        ], Response::HTTP_CREATED);
    }

    #[Route('/update/{id}', name: 'project_update', methods: ['PUT'])]
    public function update(Project $project, Request $request): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());
        
        //  Vérification simple avec creator_id
        if ($currentUserEntity->getId() !== $project->getCreatorId()) {
            return $this->json(['message' => 'Accès refusé - seul le propriétaire peut modifier'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $this->projectService->updateProject($project, $data);

        return $this->json(['message' => 'Projet modifié avec succès']);
    }

    #[Route('/delete/{id}', name: 'project_delete', methods: ['DELETE'])]
    public function delete(Project $project): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());
        
        // Vérification simple avec creator_id
        if ($currentUserEntity->getId() !== $project->getCreatorId()) {
            return $this->json(['message' => 'Accès refusé - seul le propriétaire peut supprimer'], Response::HTTP_FORBIDDEN);
        }

        $this->projectService->deleteProject($project);

        return $this->json(['message' => 'Projet supprimé avec succès']);
    }

    #[Route('/{id}/add-member', name: 'project_add_member', methods: ['POST'])]
    public function addMember(Request $request, Project $project): Response
    {
        try {
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());
            
            //  Vérification simple avec creator_id
            if ($currentUserEntity->getId() !== $project->getCreatorId()) {
                return $this->json(['message' => 'Accès refusé - seul le propriétaire peut ajouter des membres'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $userId = $data['user_id'] ?? null;
            
            if (!$userId) {
                return $this->json(['message' => 'user_id manquant'], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userService->getUserById($userId);
            if (!$user) {
                return $this->json(['message' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
            }

            // Vérifier si l'utilisateur est déjà membre
            $existingMembers = [];
            foreach ($project->getContainers() as $container) {
                foreach ($container->getIdUser() as $existingUser) {
                    $existingMembers[] = $existingUser->getId();
                }
            }

            if (in_array($userId, $existingMembers)) {
                return $this->json(['message' => 'L\'utilisateur est déjà membre du projet'], Response::HTTP_CONFLICT);
            }

            $this->projectService->addUserToProject($project, $user);
            
            try {
                $this->notificationService->addNotification(
                    "Vous avez été ajouté au projet : {$project->getTitle()}",
                    'project_member_added',
                    $user,
                    null,
                    $currentUserEntity,
                    $project
                );
            } catch (\Exception $notifError) {
                error_log("[ERROR] Notification: {$notifError->getMessage()}");
            }
            
            return $this->json(['message' => 'Membre ajouté avec succès'], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            error_log("[ERROR] addMember: {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors de l\'ajout du membre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/remove-member', name: 'project_remove_member', methods: ['POST'])]
    public function removeMember(Request $request, Project $project): Response
    {
        try {
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $currentUserEntity = $this->userService->getUserByEmail($currentUser->getUserIdentifier());
            
            // Vérification simple avec creator_id
            if ($currentUserEntity->getId() !== $project->getCreatorId()) {
                return $this->json(['message' => 'Accès refusé - seul le propriétaire peut retirer des membres'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $userId = $data['user_id'] ?? null;
            
            if (!$userId) {
                return $this->json(['message' => 'user_id manquant'], Response::HTTP_BAD_REQUEST);
            }

            //  Empêcher le propriétaire de se retirer 
            if ($userId === $project->getCreatorId()) {
                return $this->json(['message' => 'Le propriétaire ne peut pas se retirer du projet'], Response::HTTP_FORBIDDEN);
            }

            $user = $this->userService->getUserById($userId);
            if (!$user) {
                return $this->json(['message' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $this->projectService->removeUserFromProject($project, $user);
            
            return $this->json(['message' => 'Membre retiré avec succès'], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            error_log("[ERROR] removeMember: {$e->getMessage()}");
            return $this->json([
                'message' => 'Erreur lors du retrait du membre',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}