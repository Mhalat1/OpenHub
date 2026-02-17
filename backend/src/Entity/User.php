<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $availabilityStart = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $availabilityEnd = null;

    #[ORM\ManyToMany(targetEntity: Skills::class)]
    private Collection $skills;

    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'users')]
    private Collection $projects;

// Les invitations que J'AI ENVOYÉES
#[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'receivedInvitations')]
#[ORM\JoinTable(name: 'user_invitations')]
private Collection $sentInvitations;

// Les invitations que J'AI REÇUES (côté inverse)
#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'sentInvitations')]
private Collection $receivedInvitations;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'user_friends')]
    private Collection $friends;

    #[ORM\ManyToMany(targetEntity: Conversation::class, mappedBy: 'users')]
    private Collection $conversations;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'author')]
    private Collection $messages;

    public function __construct()
    {
        $this->skills = new ArrayCollection();
        $this->projects = new ArrayCollection();
    $this->sentInvitations = new ArrayCollection();
    $this->receivedInvitations = new ArrayCollection();
        $this->friends = new ArrayCollection();
        $this->conversations = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    // Friends methods
    public function getFriends(): Collection
    {
        return $this->friends;
    }

    public function addFriend(User $friend): static
    {
        if (!$this->friends->contains($friend)) {
            $this->friends->add($friend);
        }
        return $this;
    }

    public function removeFriend(User $friend): static
    {
        $this->friends->removeElement($friend);
        return $this;
    }

// Méthodes pour les invitations ENVOYÉES
public function getSentInvitations(): Collection
{
    return $this->sentInvitations;
}

public function addSentInvitation(User $user): static
{
    if (!$this->sentInvitations->contains($user)) {
        $this->sentInvitations->add($user);
    }
    return $this;
}

public function removeSentInvitation(User $user): static
{
    $this->sentInvitations->removeElement($user);
    return $this;
}

// Méthodes pour les invitations REÇUES
public function getReceivedInvitations(): Collection
{
    return $this->receivedInvitations;
}

    // Availability methods
    public function getAvailabilityStart(): ?DateTimeImmutable
    {
        return $this->availabilityStart;
    }

    public function setAvailabilityStart(?DateTimeImmutable $availabilityStart): static
    {
        $this->availabilityStart = $availabilityStart;
        return $this;
    }

    public function getAvailabilityEnd(): ?DateTimeImmutable
    {
        return $this->availabilityEnd;
    }

    public function setAvailabilityEnd(?DateTimeImmutable $availabilityEnd): static
    {
        $this->availabilityEnd = $availabilityEnd;
        return $this;
    }

    // Name methods
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    // Basic user methods
    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
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

    public function eraseCredentials(): void
    {
        // Clear temporary sensitive data
    }

    // Skills methods
    public function getSkills(): Collection
    {
        return $this->skills;
    }

    public function addSkill(Skills $skill): static
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
        }
        return $this;
    }

    public function removeSkill(Skills $skill): static
    {
        $this->skills->removeElement($skill);
        return $this;
    }

    // Projects methods
    public function getProject(): Collection
    {
        return $this->projects;
    }

    public function removeUserProject(Project $project): static
    {
        $this->projects->removeElement($project);
        return $this;
    }

    // Conversations methods
    public function getConversations(): Collection
    {
        return $this->conversations;
    }

    public function addConversation(Conversation $conversation): static
    {
        if (!$this->conversations->contains($conversation)) {
            $this->conversations->add($conversation);
            $conversation->addUser($this);
        }
        return $this;
    }

    public function removeConversation(Conversation $conversation): static
    {
        if ($this->conversations->removeElement($conversation)) {
            $conversation->removeUser($this);
        }
        return $this;
    }

    // Messages methods
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setAuthor($this);
        }
        return $this;
    }

    public function removeMessage(Message $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getAuthor() === $this) {
                $message->setAuthor(null);
            }
        }
        return $this;
    }



    
    public function getProjects(): Collection  // Changé de getProject() à getProjects()
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
        }
        return $this;
    }

    public function removeProject(Project $project): static  // Changé de removeUserProject() à removeProject()
    {
        if ($this->projects->removeElement($project)) {
        }
        return $this;
    }

}