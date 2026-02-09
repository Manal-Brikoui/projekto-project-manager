<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/messages')]
class MessageController extends AbstractController
{
    #[Route('/conversations', name: 'messages_conversations', methods: ['GET'])]
    public function getConversations(
        MessageRepository $messageRepo,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer toutes les conversations
        $conversationIds = $messageRepo->findConversations($user);
        
        $conversations = [];
        foreach ($conversationIds as $conv) {
            $userId = $conv['user_id'];
            $otherUser = $userRepo->find($userId);
            
            if (!$otherUser) continue;

            // Récupérer le dernier message
            $lastMessage = $messageRepo->createQueryBuilder('m')
                ->where('(m.sender = :user1 AND m.receiver = :user2) OR (m.sender = :user2 AND m.receiver = :user1)')
                ->setParameter('user1', $user)
                ->setParameter('user2', $otherUser)
                ->orderBy('m.sentAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            // Compter les messages non lus de cet utilisateur
            $unreadCount = $messageRepo->createQueryBuilder('m')
                ->select('COUNT(m.id)')
                ->where('m.receiver = :receiver')
                ->andWhere('m.sender = :sender')
                ->andWhere('m.isRead = false')
                ->setParameter('receiver', $user)
                ->setParameter('sender', $otherUser)
                ->getQuery()
                ->getSingleScalarResult();

            $conversations[] = [
                'id' => $otherUser->getId(),
                'name' => $otherUser->getName(),
                'email' => $otherUser->getEmail(),
                'lastMessage' => $lastMessage ? [
                    'content' => $lastMessage->getContent(),
                    'sentAt' => $lastMessage->getSentAt()->format('Y-m-d H:i:s'),
                    'isFromMe' => $lastMessage->getSender() === $user
                ] : null,
                'unreadCount' => (int) $unreadCount
            ];
        }

        return $this->json($conversations);
    }

    #[Route('/conversation/{userId}', name: 'messages_conversation', methods: ['GET'])]
    public function getConversation(
        int $userId,
        MessageRepository $messageRepo,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $otherUser = $userRepo->find($userId);
        if (!$otherUser) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Marquer les messages comme lus
        $messageRepo->markConversationAsRead($user, $otherUser);

        // Récupérer les messages
        $messages = $messageRepo->findConversationBetween($user, $otherUser);

        $data = array_map(function(Message $message) use ($user) {
            $sender = $message->getSender();
            
            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sentAt' => $message->getSentAt()->format('Y-m-d H:i:s'),
                'isFromMe' => $sender === $user,
                'isRead' => $message->isRead(),
                'sender' => [
                    'id' => $sender->getId(),
                    'name' => $sender->getName()
                ]
            ];
        }, $messages);

        return $this->json($data);
    }

    #[Route('/send', name: 'messages_send', methods: ['POST'])]
    //validation
    public function sendMessage(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!is_array($data)) {
            return $this->json(['error' => 'Format de données invalide'], Response::HTTP_BAD_REQUEST);
        }
        
        $receiverId = $data['receiverId'] ?? null;
        $content = $data['content'] ?? null;

        if (!$receiverId || !$content) {
            return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        $receiver = $userRepo->find($receiverId);
        if (!$receiver) {
            return $this->json(['error' => 'Destinataire introuvable'], Response::HTTP_NOT_FOUND);
        }

        $message = new Message();
        $message->setContent($content);
        $message->setSender($user);
        $message->setReceiver($receiver);

        $em->persist($message);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sentAt' => $message->getSentAt()->format('Y-m-d H:i:s'),
                'isFromMe' => true
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/unread-count', name: 'messages_unread_count', methods: ['GET'])]
    public function getUnreadCount(MessageRepository $messageRepo): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $count = $messageRepo->countUnreadMessages($user);

        return $this->json(['count' => (int) $count]);
    }

    #[Route('/search-users', name: 'messages_search_users', methods: ['GET'])]
    public function searchUsers(
        Request $request,
        UserRepository $userRepo
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $search = $request->query->get('q', '');

        $users = $userRepo->createQueryBuilder('u')
            ->where('u.id != :currentUser')
            ->andWhere('u.email LIKE :search OR u.name LIKE :search')
            ->setParameter('currentUser', $user->getId())
            ->setParameter('search', '%' . $search . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = array_map(function(User $u) {
            return [
                'id' => $u->getId(),
                'name' => $u->getName(),
                'email' => $u->getEmail()
            ];
        }, $users);

        return $this->json($data);
    }

    #[Route('/delete/{messageId}', name: 'messages_delete', methods: ['DELETE'])]
    public function deleteMessage(
        int $messageId,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $message = $messageRepo->find($messageId);
        
        if (!$message) {
            return $this->json(['error' => 'Message introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est soit l'expéditeur soit le destinataire
        if ($message->getSender() !== $user && $message->getReceiver() !== $user) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($message);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Message supprimé avec succès'
        ]);
    }

    #[Route('/mark-read/{messageId}', name: 'messages_mark_read', methods: ['POST'])]
    public function markAsRead(
        int $messageId,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $message = $messageRepo->find($messageId);
        
        if (!$message) {
            return $this->json(['error' => 'Message introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'utilisateur est le destinataire
        if ($message->getReceiver() !== $user) {
            return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
        }

        $message->setIsRead(true);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Message marqué comme lu'
        ]);
    }
}