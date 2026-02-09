<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $em, UserRepository $userRepository)
    {
        $this->em = $em;
        $this->userRepository = $userRepository;
    }

    //  Lister tous les utilisateurs
    public function getAllUsers(): array
    {
        $users = $this->userRepository->findAll();
        return array_map(fn(User $user) => [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ], $users);
    }

    //  Récupérer un utilisateur par ID
    public function getUserById(int $id): ?User
    {
        return $this->userRepository->find($id);
    }

    //  Récupérer un utilisateur par email
    public function getUserByEmail(string $email): ?User
    {
        return $this->userRepository->findOneBy(['email' => $email]);
    }

    //  Créer ou mettre à jour un utilisateur
    public function saveUser(User $user): User
    {
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    //  Supprimer un utilisateur
    public function deleteUser(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    //  Authentifier un utilisateur
    public function authenticate(string $email, string $password, UserPasswordHasherInterface $passwordHasher): ?User
    {
        $user = $this->getUserByEmail($email);
        if (!$user) return null;

        return $passwordHasher->isPasswordValid($user, $password) ? $user : null;
    }

    //  Récupérer les tâches d’un utilisateur
    public function getUserTasks(User $user): array
    {
        $tasks = $user->getTasks()->toArray();
        return array_map(fn($task) => [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus(),
            'project' => $task->getContainer()?->getTitle(),
        ], $tasks);
    }

    //  Récupérer les notifications d’un utilisateur
    public function getUserNotifications(User $user): array
    {
        return array_map(fn($n) => [
            'id' => $n->getId(),
            'message' => $n->getMessage(),
            'createdAt' => $n->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $user->getNotifications()->toArray());
    }

    //  Récupérer les projets d’un utilisateur
    public function getUserProjects(User $user): array
    {
        return array_map(fn($container) => [
            'id' => $container->getId(),
            'title' => $container->getTitle(),
            'description' => $container->getDescription(),
        ], $user->getContainers()->toArray());
    }

    //  Récupérer tous les utilisateurs par rôle
    public function getUsersByRole(string $role): array
    {
        $users = $this->userRepository->findBy(['role' => $role]);
        return array_map(fn(User $user) => [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ], $users);
    }

    //  Vérifier si un utilisateur est propriétaire ou a un rôle spécifique
    public function hasRole(User $user, string $role): bool
    {
        return $user->getRole() === $role;
    }
   


public function getUserByResetToken(string $token): ?User
{
    return $this->userRepository->findOneBy(['resetToken' => $token]);
}
}
