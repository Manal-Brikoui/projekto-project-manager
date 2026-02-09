<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\TaskRepository;
use App\Repository\ProjectRepository;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    #[Route('/page', methods: ['GET'])]
    public function page(): Response
    {
        return $this->render('notification/notification.html.twig');
    }

    #[Route('/', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json($this->notificationService->getAllNotifications());
    }

 
    #[Route('/me', methods: ['GET'])]
    public function myNotifications(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        try {
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();

            if (!$currentUser) {
                return $this->json(['message' => 'Non authentifié'], 401);
            }

            // Option pour filtrer uniquement les non lues
            $unreadOnly = $request->query->get('unread_only', 'false') === 'true';

            // Récupérer les notifications via le repository
            $notificationsEntities = $unreadOnly 
                ? $notificationRepository->findBy(['idUser' => $currentUser, 'isRead' => false], ['date' => 'DESC'])
                : $notificationRepository->findBy(['idUser' => $currentUser], ['date' => 'DESC']);

            // Formater les données avec protection contre les valeurs nulles
            $notifications = [];
            
            foreach ($notificationsEntities as $notif) {
                try {
                    //  Protection contre les relations nulles
                    $sender = null;
                    $project = null;
                    $task = null;
                    
                    try {
                        $sender = $notif->getSender();
                    } catch (\Exception $e) {
                        error_log(" Erreur récupération sender: " . $e->getMessage());
                    }
                    
                    try {
                        $project = $notif->getProject();
                    } catch (\Exception $e) {
                        error_log(" Erreur récupération project: " . $e->getMessage());
                    }
                    
                    try {
                        $task = $notif->getIdTask();
                    } catch (\Exception $e) {
                        error_log(" Erreur récupération task: " . $e->getMessage());
                    }
                    
                    // Formater la date correctement
                    $date = $notif->getDate();
                    $dateString = null;
                    $timestamp = null;
                    
                    if ($date) {
                        // Format ISO 8601 pour JavaScript
                        $dateString = $date->format('Y-m-d\TH:i:s');
                        // Timestamp Unix pour calculs précis
                        $timestamp = $date->getTimestamp();
                    }
                    
                    $notifications[] = [
                        'id' => $notif->getId(),
                        'message' => $notif->getMessage() ?? '',
                        'type' => $notif->getType() ?? 'info',
                        'is_read' => $notif->isRead(),
                        'created_at' => $dateString, 
                        'timestamp' => $timestamp,
                        'sender_name' => $sender ? $sender->getName() : null,
                        'sender_email' => $sender ? $sender->getEmail() : null,
                        'sender_id' => $sender ? $sender->getId() : null,
                        'project_title' => $project ? $project->getTitle() : null,
                        'project_id' => $project ? $project->getId() : null,
                        'task_title' => $task ? $task->getTitle() : null,
                        'task_id' => $task ? $task->getId() : null,
                    ];
                } catch (\Exception $notifError) {
                    // Si une notification individuelle échoue, on continue avec les autres
                    error_log(" Erreur formatage notification ID {$notif->getId()}: " . $notifError->getMessage());
                    continue;
                }
            }

            return $this->json($notifications);

        } catch (\Exception $e) {
            error_log(" ERREUR dans myNotifications: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return $this->json([
                'error' => 'Erreur serveur',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        try {
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();

            if (!$currentUser) {
                return $this->json(['count' => 0], 200);
            }

            $count = $this->notificationService->getUnreadCount($currentUser);

            return $this->json(['count' => $count]);
        } catch (\Exception $e) {
            error_log(" Erreur unreadCount: " . $e->getMessage());
            return $this->json(['count' => 0], 200);
        }
    }

    #[Route('/mark-read/{id}', methods: ['PUT', 'PATCH', 'POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $notification = $this->notificationService->getNotificationById($id);

        if (!$notification) {
            return $this->json(['message' => 'Notification non trouvée'], 404);
        }

        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->getIdUser()->getId() !== $currentUser->getId()) {
            return $this->json(['message' => 'Accès non autorisé'], 403);
        }

        $this->notificationService->markAsRead($notification);

        return $this->json(['message' => 'Notification marquée comme lue']);
    }

    #[Route('/mark-all-read', methods: ['PUT', 'POST'])]
    public function markAllAsRead(): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $this->notificationService->markAllAsRead($currentUser);

        return $this->json(['message' => 'Toutes les notifications ont été marquées comme lues']);
    }

    #[Route('/delete-all', methods: ['DELETE'])]
    public function deleteAll(): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $this->notificationService->deleteAllNotifications($currentUser);

        return $this->json(['message' => 'Toutes les notifications ont été supprimées']);
    }

    #[Route('/add', methods: ['POST'])]
    //validation 
    public function add(
        Request $request,
        UserRepository $userRepository,
        TaskRepository $taskRepository,
        ProjectRepository $projectRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validation des données obligatoires
        if (empty($data['message']) || empty($data['type']) || empty($data['user_id'])) {
            return $this->json(['message' => 'Données invalides (message, type et user_id requis)'], 400);
        }

        // Récupérer le destinataire
        $recipient = $userRepository->find($data['user_id']);
        if (!$recipient) {
            return $this->json(['message' => 'Utilisateur destinataire non trouvé'], 404);
        }

        // Récupérer la tâche 
        $task = null;
        if (!empty($data['task_id'])) {
            $task = $taskRepository->find($data['task_id']);
        }

        // Récupérer l'expéditeur 
        $sender = null;
        if (!empty($data['sender_id'])) {
            $sender = $userRepository->find($data['sender_id']);
        }

        // Récupérer le projet 
        $project = null;
        if (!empty($data['project_id'])) {
            $project = $projectRepository->find($data['project_id']);
        }

        // Créer la notification
        $notification = $this->notificationService->addNotification(
            $data['message'],
            $data['type'],
            $recipient,
            $task,
            $sender,
            $project
        );

        return $this->json([
            'message' => 'Notification créée avec succès',
            'notification' => [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'type' => $notification->getType(),
                'is_read' => $notification->isRead(),
                'recipient' => $recipient->getName(),
                'sender' => $sender?->getName(),
                'project' => $project?->getTitle(),
                'task' => $task?->getTitle()
            ]
        ], 201);
    }

    #[Route('/delete/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $notification = $this->notificationService->getNotificationById($id);

        if (!$notification) {
            return $this->json(['message' => 'Notification non trouvée'], 404);
        }

        // Vérifier que la notification appartient à l'utilisateur
        if ($notification->getIdUser()->getId() !== $currentUser->getId()) {
            return $this->json(['message' => 'Accès non autorisé'], 403);
        }

        $this->notificationService->deleteNotification($notification);

        return $this->json(['message' => 'Notification supprimée avec succès']);
    }

    #[Route('/test', methods: ['POST'])]
    public function createTestNotification(
        UserRepository $userRepository,
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository
    ): JsonResponse {
        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        // Créer une notification de test pour l'utilisateur courant
        $projects = $projectRepository->findAll();
        $project = !empty($projects) ? $projects[0] : null;

        $tasks = $taskRepository->findAll();
        $task = !empty($tasks) ? $tasks[0] : null;

        $notification = $this->notificationService->addNotification(
            sprintf(' Notification de test envoyée par %s', $currentUser->getName()),
            'comment',
            $currentUser,
            $task,
            $currentUser,
            $project
        );

        return $this->json([
            'message' => 'Notification de test créée',
            'notification' => [
                'id' => $notification->getId(),
                'message' => $notification->getMessage(),
                'is_read' => $notification->isRead(),
                'created_at' => $notification->getDate()->format('Y-m-d\TH:i:s'),
                'timestamp' => $notification->getDate()->getTimestamp(),
                'sender_name' => $notification->getSender()?->getName(),
                'sender_email' => $notification->getSender()?->getEmail(),
                'project_title' => $notification->getProject()?->getTitle(),
                'task_title' => $notification->getIdTask()?->getTitle()
            ]
        ], 201);
    }

    #[Route('/timezone-check', methods: ['GET'])]
    public function timezoneCheck(): JsonResponse
    {
        $phpTimezone = date_default_timezone_get();
        $now = new \DateTime('now');
        $nowMorocco = new \DateTime('now', new \DateTimeZone('Africa/Casablanca'));
        
        return $this->json([
            'php_timezone' => $phpTimezone,
            'server_time' => $now->format('Y-m-d H:i:s P'),
            'morocco_time' => $nowMorocco->format('Y-m-d H:i:s P'),
            'timestamp_now' => time(),
            'expected' => 'Morocco time should show +01:00 (heure d\'hiver) ou +00:00 (heure d\'été)'
        ]);
    }
}