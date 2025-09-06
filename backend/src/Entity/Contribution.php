<?php

namespace App\Entity;

use App\Repository\ContributionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContributionRepository::class)]
class Contribution
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

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    private ?Utilisateur $contribution = null;

    #[ORM\ManyToOne(inversedBy: 'contributions')]
    private ?Projet $contributionprojet = null;
   

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
    public function getCompetencesNecessaires(): ?string
    {
        return $this->competencesNecessaires;
    }

    public function setCompetencesNecessaires(string $competencesNecessaires): static
    {
        $this->competencesNecessaires = $competencesNecessaires;
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

    public function getContribution(): ?Utilisateur
    {
        return $this->contribution;
    }

    public function setContribution(?Utilisateur $contribution): static
    {
        $this->contribution = $contribution;
        return $this;
    }

    public function getContributionprojet(): ?Projet
    {
        return $this->contributionprojet;
    }

    public function setContributionprojet(?Projet $contributionprojet): static
    {
        $this->contributionprojet = $contributionprojet;
        return $this;
    }
}
