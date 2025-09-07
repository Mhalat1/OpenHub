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





}
