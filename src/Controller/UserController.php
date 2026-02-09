<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users')]
class UserController extends AbstractController
{
    private UserService $userService;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTTokenManagerInterface $jwtManager;
    private LoggerInterface $logger;

    public function __construct(
        UserService $userService,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        LoggerInterface $logger
    ) {
        $this->userService = $userService;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
        $this->logger = $logger;
    }

    // pour valider un mot de passe fort
    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 8) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>?\/\[\]\\|`~]/', $password)) return false;
        return true;
    }

    //  Liste tous les utilisateurs
    #[Route('/', name: 'user_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json($this->userService->getAllUsers());
    }

    // Page de paramètres/profil (Twig) - SANS JWT (navigation classique)
    #[Route('/settings', name: 'user_settings', methods: ['GET'])]
    public function settings(): Response
    {
       
        return $this->render('user/profil.html.twig');
    }

    //  Affiche un utilisateur par ID
    #[Route('/view/{id}', name: 'user_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ]);
    }

    //  Enregistrement d'un utilisateur
    #[Route('/register', name: 'user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            return $this->json(['message' => 'Données manquantes'], 400);
        }

        if (!in_array($data['role'], [User::ROLE_INGENIEUR, User::ROLE_CHEF_PROJET])) {
            return $this->json(['message' => 'Rôle invalide'], 400);
        }

        if (!$this->isStrongPassword($data['password'])) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
            ], 400);
        }

        $existingUser = $this->userService->getUserByEmail($data['email']);
        if ($existingUser) {
            return $this->json(['message' => 'Cet email est déjà utilisé'], 400);
        }

        $user = new User();
        $user->setName($data['name'])
             ->setEmail($data['email'])
             ->setRole($data['role'])
             ->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $this->userService->saveUser($user);
        $token = $this->jwtManager->create($user);

        $this->logger->info('Nouvel utilisateur créé', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $this->json([
            'message' => 'Utilisateur créé avec succès',
            'id' => $user->getId(),
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
            ]
        ], 201);
    }

    //  Formulaire d'inscription 
    #[Route('/register-form', name: 'user_register_form', methods: ['GET'])]
    public function registerForm(): Response
    {
        return $this->render('user/register.html.twig');
    }

    // Formulaire de connexion 
    #[Route('/login-form', name: 'user_login_form', methods: ['GET'])]
    public function loginForm(): Response
    {
        return $this->render('user/login.html.twig');
    }

    //  Login avec JWT 
    #[Route('/login', name: 'user_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->logger->info(' Tentative de connexion', [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent')
        ]);

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            $this->logger->warning(' Format de données invalide');
            return $this->json(['message' => 'Format de données invalide'], 400);
        }

        if (empty($data['email']) || empty($data['password'])) {
            $this->logger->warning('Données manquantes', ['email_present' => !empty($data['email'])]);
            return $this->json(['message' => 'Email et mot de passe requis'], 400);
        }

        $user = $this->userService->getUserByEmail($data['email']);
        
        if (!$user) {
            $this->logger->warning(' Utilisateur non trouvé', ['email' => $data['email']]);
            return $this->json(['message' => 'Email ou mot de passe incorrect'], 401);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            $this->logger->warning(' Mot de passe incorrect', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            return $this->json(['message' => 'Email ou mot de passe incorrect'], 401);
        }

        try {
            $token = $this->jwtManager->create($user);
            
            $this->logger->info(' Connexion réussie', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'token_preview' => substr($token, 0, 20) . '...'
            ]);

            return $this->json([
                'message' => 'Connexion réussie',
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole(),
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error(' Erreur génération token JWT', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            return $this->json([
                'message' => 'Erreur lors de la génération du token'
            ], 500);
        }
    }

    //  Endpoint pour vérifier le token actuel 
    #[Route('/me', name: 'user_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
        ]);
    }

    //  Changement de mot de passe 
    #[Route('/change-password', name: 'user_change_password', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['currentPassword']) || empty($data['newPassword'])) {
            return $this->json(['message' => 'Ancien et nouveau mot de passe requis'], 400);
        }

        $currentPassword = $data['currentPassword'];
        $newPassword = $data['newPassword'];

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->logger->warning('Tentative de changement de mot de passe avec mauvais mot de passe actuel', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
            return $this->json(['message' => 'Mot de passe actuel incorrect'], 401);
        }

        if (!$this->isStrongPassword($newPassword)) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
            ], 400);
        }

        if ($this->passwordHasher->isPasswordValid($user, $newPassword)) {
            return $this->json(['message' => 'Le nouveau mot de passe doit être différent de l\'ancien'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->userService->saveUser($user);

        $this->logger->info('Mot de passe changé avec succès', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $this->json([
            'message' => 'Mot de passe changé avec succès'
        ]);
    }

    // Page de changement de mot de passe 
    #[Route('/change-password-form', name: 'user_change_password_form', methods: ['GET'])]
    public function changePasswordForm(): Response
    {
        return $this->render('user/change_password.html.twig');
    }

    //  Mot de passe oublié
    #[Route('/forgot-password', name: 'user_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return $this->json(['message' => 'Email requis'], 400);
        }

        $email = $data['email'];
        $user = $this->userService->getUserByEmail($email);

        if (!$user) {
            $this->logger->info('Tentative de reset pour email inexistant', ['email' => $email]);
            return $this->json([
                'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé'
            ]);
        }

        $resetToken = bin2hex(random_bytes(32));
        $resetTokenExpiry = new \DateTime('+1 hour');

        $user->setResetToken($resetToken);
        $user->setResetTokenExpiry($resetTokenExpiry);
        $this->userService->saveUser($user);

        $this->logger->info('Token de réinitialisation généré', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'token_preview' => substr($resetToken, 0, 10) . '...'
        ]);

        $resetLink = sprintf(
            '%s/users/reset-password?token=%s',
            $request->getSchemeAndHttpHost(),
            $resetToken
        );
        
        $this->logger->info('Lien de réinitialisation', [
            'user_email' => $user->getEmail(),
            'reset_link' => $resetLink
        ]);

        return $this->json([
            'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé',
            'dev_reset_link' => $resetLink
        ]);
    }

    // Page de réinitialisation de mot de passe
    #[Route('/reset-password', name: 'user_reset_password_form', methods: ['GET'])]
    public function resetPasswordForm(Request $request): Response
    {
        $token = $request->query->get('token');
        
        if (!$token) {
            return $this->render('user/reset_password_invalid.html.twig', [
                'error' => 'Token de réinitialisation manquant'
            ]);
        }

        $user = $this->userService->getUserByResetToken($token);
        if (!$user || !$user->isResetTokenValid()) {
            return $this->render('user/reset_password_invalid.html.twig', [
                'error' => 'Token invalide ou expiré'
            ]);
        }
        
        return $this->render('user/reset_password.html.twig', [
            'token' => $token
        ]);
    }

    // Réinitialisation effective du mot de passe
    #[Route('/reset-password/confirm', name: 'user_reset_password_confirm', methods: ['POST'])]
    public function resetPasswordConfirm(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['token']) || empty($data['password'])) {
            return $this->json(['message' => 'Token et nouveau mot de passe requis'], 400);
        }

        $token = $data['token'];
        $newPassword = $data['password'];

        if (!$this->isStrongPassword($newPassword)) {
            return $this->json([
                'message' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
            ], 400);
        }

        $user = $this->userService->getUserByResetToken($token);
        
        if (!$user) {
            $this->logger->warning('Token de reset invalide', ['token_preview' => substr($token, 0, 10) . '...']);
            return $this->json(['message' => 'Token invalide ou expiré'], 400);
        }

        if (!$user->isResetTokenValid()) {
            $this->logger->warning('Token de reset expiré', [
                'user_id' => $user->getId(),
                'expiry' => $user->getResetTokenExpiry()->format('Y-m-d H:i:s')
            ]);
            return $this->json(['message' => 'Token expiré'], 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setResetToken(null);
        $user->setResetTokenExpiry(null);
        $this->userService->saveUser($user);

        $this->logger->info('Mot de passe réinitialisé', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $this->json([
            'message' => 'Mot de passe réinitialisé avec succès',
            'redirect' => '/users/login-form'
        ]);
    }

    //  Modification d'un utilisateur 
    #[Route('/edit/{id}', name: 'user_edit', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        $data = json_decode($request->getContent(), true);

        if (isset($data['role'])) $user->setRole($data['role']);
        if (isset($data['name'])) $user->setName($data['name']);
        if (isset($data['email'])) $user->setEmail($data['email']);
        
        if (isset($data['password'])) {
            if (!$this->isStrongPassword($data['password'])) {
                return $this->json([
                    'message' => 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial'
                ], 400);
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $this->userService->saveUser($user);

        $this->logger->info('Utilisateur mis à jour', ['user_id' => $id]);

        return $this->json(['message' => 'Utilisateur mis à jour']);
    }

    //  Suppression d'un utilisateur 
    #[Route('/delete/{id}', name: 'user_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        $email = $user->getEmail();
        $this->userService->deleteUser($user);

        $this->logger->info('Utilisateur supprimé', ['user_id' => $id, 'email' => $email]);

        return $this->json(['message' => 'Utilisateur supprimé']);
    }

    //  Récupération des projets d'un utilisateur
    #[Route('/view/{id}/projects', name: 'user_projects', methods: ['GET'])]
    public function projects(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        $projects = $user->getContainers()->map(fn($c) => [
            'container_id' => $c->getId(),
            'project_id' => $c->getIdProject()?->getId(),
        ])->toArray();

        return $this->json($projects);
    }

    //  Récupération des tâches d'un utilisateur
    #[Route('/view/{id}/tasks', name: 'user_tasks', methods: ['GET'])]
    public function tasks(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        $tasks = $user->getTasks()->map(fn($t) => [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'status' => $t->getStatus(),
        ])->toArray();

        return $this->json($tasks);
    }

    //  Récupération des notifications d'un utilisateur
    #[Route('/view/{id}/notifications', name: 'user_notifications', methods: ['GET'])]
    public function notifications(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);
        if (!$user) return $this->json(['message' => 'Utilisateur non trouvé'], 404);

        $notifications = $user->getNotifications()->map(fn($n) => [
            'id' => $n->getId(),
            'message' => $n->getMessage(),
        ])->toArray();

        return $this->json($notifications);
    }
}