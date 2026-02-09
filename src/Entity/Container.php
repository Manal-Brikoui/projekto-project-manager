<?php

namespace App\Entity;

use App\Repository\ContainerRepository;
use App\Entity\User;
use App\Entity\Project;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContainerRepository::class)]
class Container
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'containers')]
    private Collection $idUser;

    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'containers', cascade: ['persist'])]
    private Collection $idProject;

    public function __construct()
    {
        $this->idUser = new ArrayCollection();
        $this->idProject = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdUser(): Collection
    {
        return $this->idUser;
    }

    public function addIdUser(User $user): self
    {
        if (!$this->idUser->contains($user)) {
            $this->idUser->add($user);
        }
        return $this;
    }

    public function removeIdUser(User $user): self
    {
        $this->idUser->removeElement($user);
        return $this;
    }

    public function getIdProject(): Collection
    {
        return $this->idProject;
    }

    public function addIdProject(Project $project): self
    {
        if (!$this->idProject->contains($project)) {
            $this->idProject->add($project);
        }
        return $this;
    }

    public function removeIdProject(Project $project): self
    {
        $this->idProject->removeElement($project);
        return $this;
    }
}