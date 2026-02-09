<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $startdate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $enddate = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $evaluation = null;

    #[ORM\Column(nullable: false)]
    private ?int $creatorId = null;

    #[ORM\OneToMany(
        targetEntity: Task::class, 
        mappedBy: 'idProject',
        cascade: ['remove'],
        orphanRemoval: true
    )]
    private Collection $tasks;

    #[ORM\ManyToMany(
        targetEntity: Container::class, 
        mappedBy: 'idProject',
        cascade: ['remove'],
        orphanRemoval: true
    )]
    private Collection $containers;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->containers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStartdate(): ?\DateTime
    {
        return $this->startdate;
    }

    public function setStartdate(\DateTime $startdate): static
    {
        $this->startdate = $startdate;
        return $this;
    }

    public function getEnddate(): ?\DateTime
    {
        return $this->enddate;
    }

    public function setEnddate(\DateTime $enddate): static
    {
        $this->enddate = $enddate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getEvaluation(): ?string
    {
        return $this->evaluation;
    }

    public function setEvaluation(?string $evaluation): static
    {
        $this->evaluation = $evaluation;
        return $this;
    }

    //  Getter et Setter pour creatorId
    public function getCreatorId(): ?int
    {
        return $this->creatorId;
    }

    public function setCreatorId(int $creatorId): static
    {
        $this->creatorId = $creatorId;
        return $this;
    }

    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setIdProject($this);
        }
        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getIdProject() === $this) {
                $task->setIdProject(null);
            }
        }
        return $this;
    }

    public function getContainers(): Collection
    {
        return $this->containers;
    }

    public function addContainer(Container $container): static
    {
        if (!$this->containers->contains($container)) {
            $this->containers->add($container);
            $container->addIdProject($this);
        }
        return $this;
    }

    public function removeContainer(Container $container): static
    {
        if ($this->containers->removeElement($container)) {
            $container->removeIdProject($this);
        }
        return $this;
    }
}