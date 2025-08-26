<?php

namespace App\Entity;

use App\Repository\ProjetRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
class Projet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;
    #[ORM\Column(length: 255)]
    private ?string $competencesNecessaires = null;
    #[ORM\Column(length: 255)]
    private ?DateTimeImmutable $dateDeCreation = null;
    #[ORM\Column(length: 255)]
    private ?DateTimeImmutable $dateDeFin = null;

    #[ORM\ManyToOne(inversedBy: 'projets')]
    private ?Utilisateur $projet = null;

    /**
     * @var Collection<int, Contribution>
     */
    #[ORM\OneToMany(targetEntity: Contribution::class, mappedBy: 'contributionprojet')]
    private Collection $contributions;

    public function __construct()
    {
        $this->contributions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

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


public function getCompetencesNecessaires(): ?string
{
    return $this->competencesNecessaires;
}

public function setCompetencesNecessaires(?string $competencesNecessaires): self
{
    $this->competencesNecessaires = $competencesNecessaires;
    return $this;
}

public function getDateDeCreation(): ?\DateTimeImmutable
{
    return $this->dateDeCreation;
}

public function setDateDeCreation(?\DateTimeImmutable $dateDeCreation): self
{
    $this->dateDeCreation = $dateDeCreation;
    return $this;
}

public function getDateDeFin(): ?\DateTimeImmutable
{
    return $this->dateDeFin;
}

public function setDateDeFin(?\DateTimeImmutable $dateDeFin): self
{
    $this->dateDeFin = $dateDeFin;
    return $this;
}

public function getProjet(): ?Utilisateur
{
    return $this->projet;
}

public function setProjet(?Utilisateur $projet): static
{
    $this->projet = $projet;

    return $this;
}

/**
 * @return Collection<int, Contribution>
 */
public function getContributions(): Collection
{
    return $this->contributions;
}

public function addContribution(Contribution $contribution): static
{
    if (!$this->contributions->contains($contribution)) {
        $this->contributions->add($contribution);
        $contribution->setContributionprojet($this);
    }

    return $this;
}

public function removeContribution(Contribution $contribution): static
{
    if ($this->contributions->removeElement($contribution)) {
        // set the owning side to null (unless already changed)
        if ($contribution->getContributionprojet() === $this) {
            $contribution->setContributionprojet(null);
        }
    }

    return $this;
}


}
