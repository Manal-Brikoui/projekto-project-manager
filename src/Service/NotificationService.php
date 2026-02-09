<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Task;
use App\Entity\Project;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private EntityManagerInterface $em;
    private NotificationRepository $notificationRepository;

    public function __construct(
        EntityManagerInterface $em,
        NotificationRepository $notificationRepository
    ) {
        $this->em = $em;
        $this->notificationRepository = $notificationRepository;
    }

    //Lister toutes les notifications 
    public function getAllNotifications(): array
    {
        return array_map(
            fn(Notification $n) => $this->formatNotification($n),
            $this->notificationRepository->findAll()
        );
    }

    // Récupérer les notifications d'un utilisateur 
    public function getNotificationsByUser(User $user, bool $unreadOnly = false): array
    {
        $criteria = ['idUser' => $user];
        
        if ($unreadOnly) {
            $criteria['isRead'] = false;
        }

        $notifications = $this->notificationRepository->findBy(
            $criteria,
            ['date' => 'DESC']
        );

        return array_map(
            fn(Notification $n) => $this->formatNotification($n),
            $notifications
        );
    }

    // Récupérer une notification par ID
    public function getNotificationById(int $id): ?Notification
    {
        try {
            return $this->notificationRepository->find($id);
        } catch (\Exception $e) {
            error_log(" Erreur getNotificationById: {$e->getMessage()}");
            return null;
        }
    }

    // Créer une nouvelle notification
    public function addNotification(
        string $message,
        string $type,
        ?User $recipient = null,
        ?Task $task = null,
        ?User $sender = null,
        ?Project $project = null
    ): Notification {
        $notification = new Notification();
        $notification->setMessage($message)
                     ->setType($type)
                     ->setDate(new \DateTime())
                     ->setIsRead(false)
                     ->setIdUser($recipient)
                     ->setIdTask($task)
                     ->setSender($sender)
                     ->setProject($project);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    // Marquer une notification comme lue
    public function markAsRead(Notification $notification): void
    {
        try {
            $notification->setIsRead(true);
            $this->em->flush();
            error_log(" Notification #{$notification->getId()} marquée comme lue");
        } catch (\Exception $e) {
            error_log(" Erreur markAsRead: {$e->getMessage()}");
            throw $e;
        }
    }

    // Marquer toutes les notifications d'un utilisateur comme lues

    public function markAllAsRead(User $user): void
    {
        try {
            $notifications = $this->notificationRepository->findBy([
                'idUser' => $user,
                'isRead' => false
            ]);

            foreach ($notifications as $notification) {
                $notification->setIsRead(true);
            }

            $this->em->flush();
            error_log(" Toutes les notifications de l'utilisateur #{$user->getId()} marquées comme lues");
        } catch (\Exception $e) {
            error_log(" Erreur markAllAsRead: {$e->getMessage()}");
            throw $e;
        }
    }

    //Compter les notifications non lues d'un utilisateur
    public function getUnreadCount(User $user): int
    {
        try {
            return $this->notificationRepository->count([
                'idUser' => $user,
                'isRead' => false
            ]);
        } catch (\Exception $e) {
            error_log(" Erreur getUnreadCount: {$e->getMessage()}");
            return 0;
        }
    }

    //Notifier un utilisateur de l'assignation d'une tâche
    public function notifyTaskAssignment(Task $task, User $assignedUser, User $assigner): void
    {
        $project = $task->getIdProject();
        
        if (!$project) {
            $message = sprintf(
                '%s vous a assigné la tâche "%s"',
                $assigner->getName(),
                $task->getTitle()
            );
        } else {
            $message = sprintf(
                '%s vous a assigné la tâche "%s" dans le projet "%s"',
                $assigner->getName(),
                $task->getTitle(),
                $project->getTitle()
            );
        }

        $this->addNotification(
            $message,
            'task_assigned',
            $assignedUser,
            $task,
            $assigner,
            $project
        );
    }

    //Notifier les membres d'un projet d'une mise à jour de tâche
    public function notifyTaskUpdate(Task $task, User $updater, array $members): void
    {
        $project = $task->getIdProject();
        
        if (!$project) {
            $message = sprintf(
                '%s a modifié la tâche "%s"',
                $updater->getName(),
                $task->getTitle()
            );
        } else {
            $message = sprintf(
                '%s a modifié la tâche "%s" dans le projet "%s"',
                $updater->getName(),
                $task->getTitle(),
                $project->getTitle()
            );
        }

        foreach ($members as $member) {
            if ($member->getId() !== $updater->getId()) {
                $this->addNotification(
                    $message,
                    'task_updated',
                    $member,
                    $task,
                    $updater,
                    $project
                );
            }
        }
    }

    //Notifier de la complétion d'une tâche

    public function notifyTaskCompletion(Task $task, User $completer, array $members): void
    {
        $project = $task->getIdProject();
        
        if (!$project) {
            $message = sprintf(
                '%s a marqué la tâche "%s" comme terminée',
                $completer->getName(),
                $task->getTitle()
            );
        } else {
            $message = sprintf(
                '%s a marqué la tâche "%s" comme terminée dans le projet "%s"',
                $completer->getName(),
                $task->getTitle(),
                $project->getTitle()
            );
        }

        foreach ($members as $member) {
            if ($member->getId() !== $completer->getId()) {
                $this->addNotification(
                    $message,
                    'task_completed',
                    $member,
                    $task,
                    $completer,
                    $project
                );
            }
        }
    }

    // Notifier l'ajout d'un membre à un projet
    public function notifyProjectMemberAdded(Project $project, User $newMember, User $addedBy): void
    {
        $message = sprintf(
            '%s vous a ajouté au projet "%s"',
            $addedBy->getName(),
            $project->getTitle()
        );

        $this->addNotification(
            $message,
            'project_update',
            $newMember,
            null,
            $addedBy,
            $project
        );
    }

    //Notifier d'une deadline proche
    public function notifyDeadlineSoon(Task $task, User $assignedUser): void
    {
        $project = $task->getIdProject();
        
        if (!$project) {
            $message = sprintf(
                'La tâche "%s" arrive bientôt à échéance',
                $task->getTitle()
            );
        } else {
            $message = sprintf(
                'La tâche "%s" du projet "%s" arrive bientôt à échéance',
                $task->getTitle(),
                $project->getTitle()
            );
        }

        $this->addNotification(
            $message,
            'deadline_soon',
            $assignedUser,
            $task,
            null,
            $project
        );
    }

    //Supprimer une notification

    public function deleteNotification(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }

    // Supprimer toutes les notifications d'un utilisateur
    public function deleteAllNotifications(User $user): void
    {
        $notifications = $this->notificationRepository->findBy([
            'idUser' => $user
        ]);

        foreach ($notifications as $notification) {
            $this->em->remove($notification);
        }

        $this->em->flush();
    }

    //Supprimer les anciennes notifications
    public function deleteOldNotifications(int $daysOld = 30): void
    {
        $date = new \DateTime();
        $date->modify("-{$daysOld} days");

        $qb = $this->notificationRepository->createQueryBuilder('n')
            ->where('n.date < :date')
            ->setParameter('date', $date)
            ->getQuery();

        $oldNotifications = $qb->getResult();

        foreach ($oldNotifications as $notification) {
            $this->em->remove($notification);
        }

        $this->em->flush();
    }

    //Format commun pour le JSON
    private function formatNotification(Notification $n): array
    {
        return [
            'id' => $n->getId(),
            'message' => $n->getMessage(),
            'type' => $n->getType(),
            'date' => $n->getDate()?->format('Y-m-d H:i:s'),
            'is_read' => $n->isRead(),
            
            // Destinataire
            'user_id' => $n->getIdUser()?->getId(),
            'user_name' => $n->getIdUser()?->getName(),
            'user_email' => $n->getIdUser()?->getEmail(),
            
            // Tâche
            'task_id' => $n->getIdTask()?->getId(),
            'task_title' => $n->getIdTask()?->getTitle(),
            
            // Projet
            'project_id' => $n->getProject()?->getId(),
            'project_title' => $n->getProject()?->getTitle(),
            
            // Expéditeur
            'sender_id' => $n->getSender()?->getId(),
            'sender_name' => $n->getSender()?->getName(),
            'sender_email' => $n->getSender()?->getEmail(),
        ];
    }
}