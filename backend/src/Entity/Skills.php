<?php

namespace App\Entity;

use App\Repository\SkillsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SkillsRepository::class)]
class Skills
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $Nom = null;

    #[ORM\Column(length: 50)]
    private ?string $ContextApprentissage = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $Duree = null;

    #[ORM\Column(length: 255)]
    private ?string $TechnoUtilisees = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->Nom;
    }

    public function setNom(string $Nom): static
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function getContextApprentissage(): ?string
    {
        return $this->ContextApprentissage;
    }

    public function setContextApprentissage(string $ContextApprentissage): static
    {
        $this->ContextApprentissage = $ContextApprentissage;

        return $this;
    }

    public function getDuree(): ?\DateTimeImmutable
    {
        return $this->Duree;
    }

    public function setDuree(\DateTimeImmutable $Duree): static
    {
        $this->Duree = $Duree;

        return $this;
    }

    public function getTechnoUtilisees(): ?string
    {
        return $this->TechnoUtilisees;
    }

    public function setTechnoUtilisees(string $TechnoUtilisees): static
    {
        $this->TechnoUtilisees = $TechnoUtilisees;

        return $this;
    }


}