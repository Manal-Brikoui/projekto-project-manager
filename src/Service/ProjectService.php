<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Entity\Container;
use Doctrine\ORM\EntityManagerInterface;

class ProjectService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function addProject(array $data): Project
    {
        $project = new Project();
        $project->setTitle($data['title']);
        $project->setDescription($data['description']);
        $project->setStatus($data['status'] ?? 'en_cours');
        $project->setEvaluation($data['evaluation'] ?? null);
        $project->setStartdate(new \DateTime($data['startdate']));
        $project->setEnddate(new \DateTime($data['enddate']));

        // ✅ IMPORTANT : Définir le créateur (ne change JAMAIS)
        if (!empty($data['idUsers']) && is_array($data['idUsers'])) {
            $owner = $data['idUsers'][0];
            if ($owner instanceof User) {
                $project->setCreatorId($owner->getId());
            }
        }

        $this->em->persist($project);

        // Créer le container avec le propriétaire
        if (!empty($data['idUsers']) && is_array($data['idUsers'])) {
            $container = new Container();
            $container->addIdProject($project);

            // Ajouter uniquement le propriétaire
            $owner = $data['idUsers'][0];
            if ($owner instanceof User) {
                $container->addIdUser($owner);
            }

            $this->em->persist($container);
        }

        $this->em->flush();
        return $project;
    }

    public function updateProject(Project $project, array $data): Project
    {
        if (!empty($data['title'])) $project->setTitle($data['title']);
        if (!empty($data['description'])) $project->setDescription($data['description']);
        if (!empty($data['status'])) $project->setStatus($data['status']);
        if (isset($data['evaluation'])) $project->setEvaluation($data['evaluation']);
        if (!empty($data['startdate'])) $project->setStartdate(new \DateTime($data['startdate']));
        if (!empty($data['enddate'])) $project->setEnddate(new \DateTime($data['enddate']));

        $this->em->flush();
        return $project;
    }

    public function deleteProject(Project $project): void
    {
        $this->em->remove($project);
        $this->em->flush();
    }

    public function getAllProjects(): array
    {
        return $this->em->getRepository(Project::class)->findAll();
    }

    public function addUserToProject(Project $project, User $user): void
    {
        // ✅ Créer un NOUVEAU container pour chaque membre ajouté
        // Cela garantit que le premier container (propriétaire) reste intact
        
        $newContainer = new Container();
        $newContainer->addIdProject($project);
        $newContainer->addIdUser($user);
        
        $this->em->persist($newContainer);
        $this->em->flush();
    }

    public function removeUserFromProject(Project $project, User $user): void
    {
        //  Ne jamais supprimer le propriétaire
        if ($user->getId() === $project->getCreatorId()) {
            return;
        }

        $containers = $project->getContainers();
        
        foreach ($containers as $container) {
            // Retirer l'utilisateur du container
            if ($container->getIdUser()->contains($user)) {
                $container->removeIdUser($user);
                
                // Si le container devient vide, le supprimer
                if ($container->getIdUser()->isEmpty()) {
                    $this->em->remove($container);
                }
            }
        }
        
        $this->em->flush();
    }

    public function getProjectOwner(Project $project): ?User
    {
        $creatorId = $project->getCreatorId();
        if (!$creatorId) {
            return null;
        }

        return $this->em->getRepository(User::class)->find($creatorId);
    }

    public function isProjectOwner(Project $project, User $user): bool
    {
        // Comparaison simple avec creator_id
        return $user->getId() === $project->getCreatorId();
    }

    public function getAllMembers(Project $project): array
    {
        $members = [];
        $memberIds = [];
        
        foreach ($project->getContainers() as $container) {
            foreach ($container->getIdUser() as $user) {
                if (!in_array($user->getId(), $memberIds)) {
                    $memberIds[] = $user->getId();
                    $members[] = $user;
                }
            }
        }
        
        return $members;
    }

    public function isMember(Project $project, User $user): bool
    {
        foreach ($project->getContainers() as $container) {
            if ($container->getIdUser()->contains($user)) {
                return true;
            }
        }
        return false;
    }

    public function save(Project $project): void
    {
        $this->em->persist($project);
        $this->em->flush();
    }
}