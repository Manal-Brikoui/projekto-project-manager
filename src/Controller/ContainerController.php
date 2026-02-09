<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\ProjectRepository;
use App\Repository\ContainerRepository;
use App\Service\ContainerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/containers')]
class ContainerController extends AbstractController
{
    private ContainerService $containerService;

    public function __construct(ContainerService $containerService)
    {
        $this->containerService = $containerService;
    }

    // Lister toutes les affectations 
    #[Route('/', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $containers = $this->containerService->getAll();
            $result = [];

            foreach ($containers as $container) {
                $usersArray = array_map(fn($user) => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail()
                ], $container->getIdUser()->toArray());

                $projectsArray = array_map(fn($project) => [
                    'id' => $project->getId(),
                    'title' => $project->getTitle()
                ], $container->getIdProject()->toArray());

                $result[] = [
                    'id' => $container->getId(),
                    'users' => $usersArray,
                    'projects' => $projectsArray
                ];
            }

            return $this->json($result, 200);

        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des containers',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    //  Affecter un utilisateur à un projet 
    #[Route('/assign', methods: ['POST'])]
    public function assign(
        Request $request,
        UserRepository $userRepository,
        ProjectRepository $projectRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['user_id']) || empty($data['project_id'])) {
            return $this->json(['message' => 'user_id et project_id requis'], 400);
        }

        $user = $userRepository->find($data['user_id']);
        $project = $projectRepository->find($data['project_id']);

        if (!$user || !$project) {
            return $this->json(['message' => 'User ou Project non trouvé'], 404);
        }

        $container = $this->containerService->assignUserToProject($user, $project);

        return $this->json([
            'message' => 'Utilisateur affecté au projet',
            'container_id' => $container->getId()
        ], 201);
    }

    //  Supprimer une affectation spécifique 
    #[Route('/delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        ContainerRepository $containerRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['container_id']) || empty($data['user_id']) || empty($data['project_id'])) {
            return $this->json(['message' => 'container_id, user_id et project_id requis'], 400);
        }

        $container = $containerRepository->find($data['container_id']);

        if (!$container) {
            return $this->json(['message' => 'Container non trouvé'], 404);
        }

        // Récupérer l'utilisateur et le projet spécifiques dans le container
        $user = $container->getIdUser()->filter(fn($u) => $u->getId() === $data['user_id'])->first();
        $project = $container->getIdProject()->filter(fn($p) => $p->getId() === $data['project_id'])->first();

        if (!$user || !$project) {
            return $this->json(['message' => 'Affectation non trouvée'], 404);
        }

        $this->containerService->removeUserFromProject($container, $user, $project);

        return $this->json(['message' => 'Affectation supprimée'], 200);
    }
}
