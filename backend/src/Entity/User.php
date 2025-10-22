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

    /**
     * @var list<string> User roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
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



    /**
     * @var Collection<int, Skills>
     */
    #[ORM\ManyToMany(targetEntity: Skills::class)]
    private Collection $Skills;

    /**
     * @var Collection<int, project>
     */
    #[ORM\ManyToMany(targetEntity: project::class, inversedBy: 'users')]
    private Collection $projects;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'user_invitations')]
    private Collection $Invitation;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'user_friends')]
    private Collection $Friends;




    public function __construct()
    {
        $this->Skills = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->Invitation = new ArrayCollection();
        $this->Friends = new ArrayCollection();
    }

    public function getFriends(): Collection
    {
        return $this->Friends;
    }
    public function addFriend(User $friend): static
    {
        if (!$this->Friends->contains($friend)) {
            $this->Friends->add($friend);
        }       
        return $this;
    }  
    public function removeFriend(User $friend): static
    {
        $this->Friends->removeElement($friend); 
        return $this;
    }

    public function getInvitation(): Collection
    {
        return $this->Invitation;
    }
    public function addInvitation(User $invitation): static
    {
        if (!$this->Invitation->contains($invitation)) {
            $this->Invitation->add($invitation);
        }

        return $this;
    }  
    public function removeInvitation(User $invitation): static
    {
        $this->Invitation->removeElement($invitation);

        return $this;
    }



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

    /**
     * Returns the identifier for this user (email).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER'; // Every user has at least ROLE_USER
        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Clears sensitive data from the user.
     */
    public function eraseCredentials(): void
    {
        // Clear temporary sensitive data if any, e.g. $this->plainPassword = null;
    }


    /**
     * @return Collection<int, Skills>
     */
    public function getSkills(): Collection
    {
        return $this->Skills;
    }

    public function addSkill(Skills $skill): static
    {
        if (!$this->Skills->contains($skill)) {
            $this->Skills->add($skill);
        }

        return $this;
    }

    public function removeSkill(Skills $skill): static
    {
        $this->Skills->removeElement($skill);

        return $this;
    }

    /**
     * @return Collection<int, project>
     */
    public function getProject(): Collection
    {
        return $this->projects;
    }

    public function addProject(project $userProject): static
    {
        if (!$this->projects->contains($userProject)) {
            $this->projects->add($userProject);
        }

        return $this;
    }

    public function removeUserProject(project $userProject): static
    {
        $this->projects->removeElement($userProject);

        return $this;
    }


}
