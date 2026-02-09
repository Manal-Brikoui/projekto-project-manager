<?php

namespace App\Service;

use App\Entity\Container;
use App\Entity\User;
use App\Entity\Project;
use App\Repository\ContainerRepository;
use Doctrine\ORM\EntityManagerInterface;

class ContainerService
{
    private EntityManagerInterface $em;
    private ContainerRepository $containerRepository;

    public function __construct(
        EntityManagerInterface $em,
        ContainerRepository $containerRepository
    ) {
        $this->em = $em;
        $this->containerRepository = $containerRepository;
    }

    //  Affecter un utilisateur à un projet
    public function assignUserToProject(User $user, Project $project): Container
    {
        $container = new Container();
        $container->addIdUser($user);
        $container->addIdProject($project);

        $this->em->persist($container);
        $this->em->flush();

        return $container;
    }

    //  Retirer un utilisateur d’un projet
    public function removeUserFromProject(Container $container, User $user, Project $project): void
    {
        $container->removeIdUser($user);
        $container->removeIdProject($project);
        $this->em->flush();
    }

    // Lister tous les containers
    public function getAll(): array
    {
        return $this->containerRepository->findAll();
    }
}
