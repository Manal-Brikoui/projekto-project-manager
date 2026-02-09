<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_INGENIEUR = 'ROLE_INGENIEUR';
    public const ROLE_CHEF_PROJET = 'ROLE_CHEF_PROJET';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $role = null;

    //   pour la réinitialisation de mot de passe
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiry = null;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'idUser', orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'idUser')]
    private Collection $notifications;

    #[ORM\ManyToMany(targetEntity: Container::class, mappedBy: 'idUser')]
    private Collection $containers;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->containers = new ArrayCollection();
    }

   
    public function getId(): ?int 
    { 
        return $this->id; 
    }

    public function getName(): ?string 
    { 
        return $this->name; 
    }

    public function setName(string $name): static 
    { 
        $this->name = $name; 
        return $this; 
    }

    public function getEmail(): ?string 
    { 
        return $this->email; 
    }

    public function setEmail(string $email): static 
    { 
        $this->email = $email; 
        return $this; 
    }

    public function getPassword(): ?string 
    { 
        return $this->password; 
    }

    public function setPassword(string $password): static 
    { 
        $this->password = $password; 
        return $this; 
    }

    public function getRole(): ?string 
    { 
        return $this->role; 
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, [self::ROLE_INGENIEUR, self::ROLE_CHEF_PROJET])) {
            throw new \InvalidArgumentException("Rôle invalide : $role");
        }
        $this->role = $role;
        return $this;
    }

    //  Getters & Setters pour la réinitialisation de mot de passe
    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiry(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiry;
    }

    public function setResetTokenExpiry(?\DateTimeInterface $resetTokenExpiry): static
    {
        $this->resetTokenExpiry = $resetTokenExpiry;
        return $this;
    }

    // Méthode helper pour vérifier si le token est valide
    public function isResetTokenValid(): bool
    {
        if (!$this->resetToken || !$this->resetTokenExpiry) {
            return false;
        }
        
        return $this->resetTokenExpiry > new \DateTime();
    }

    public function getTasks(): Collection 
    { 
        return $this->tasks; 
    }

    public function getNotifications(): Collection 
    { 
        return $this->notifications; 
    }

    public function getContainers(): Collection 
    { 
        return $this->containers; 
    }


    // Méthodes requises par UserInterface

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return [$this->role ?? 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // Si tu stockes des données temporaires sensibles, les effacer ici
    }


    // Relations helpers (Tasks, Notifications, Containers)
   
    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setIdUser($this);
        }
        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task) && $task->getIdUser() === $this) {
            $task->setIdUser(null);
        }
        return $this;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setIdUser($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification) && $notification->getIdUser() === $this) {
            $notification->setIdUser(null);
        }
        return $this;
    }

    public function addContainer(Container $container): static
    {
        if (!$this->containers->contains($container)) {
            $this->containers->add($container);
            $container->addIdUser($this);
        }
        return $this;
    }

    public function removeContainer(Container $container): static
    {
        if ($this->containers->removeElement($container)) {
            $container->removeIdUser($this);
        }
        return $this;
    }
}