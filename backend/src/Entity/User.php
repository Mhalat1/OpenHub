<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $nom = null;


    #[ORM\Column(length: 10, nullable: true)]
    private ?DateTimeImmutable $debutDispo = null;
    #[ORM\Column(length: 10, nullable: true)]
    private ?DateTimeImmutable $finDispo = null;
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $competences = null;

    public function getCompetences(): ?string
    {
        return $this->competences;
    }
    public function setCompetences(?string $competences): static
    {
        $this->competences = $competences;
        return $this;
    }

    public function getDebutDispo(): ?DateTimeImmutable
    {
        return $this->debutDispo;
    }
    public function setDebutDispo(?DateTimeImmutable $debutDispo): static
    {
        $this->debutDispo = $debutDispo;
        return $this;
    }
    public function getFinDispo(): ?DateTimeImmutable
    {
        return $this->finDispo;
    }
    public function setFinDispo(?DateTimeImmutable $finDispo): static
    {
        $this->finDispo = $finDispo;
        return $this;
    }


    public function getPrenom(): ?string
    {       
        return $this->prenom;
    }
    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(?string $nom): static
    {
        $this->nom = $nom;  
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
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string  // PHP 8.0
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

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
     * Removes sensitive data from the user.
     *
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

}
