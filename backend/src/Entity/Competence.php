<?php

namespace App\Entity;

use App\Repository\CompetenceRepository;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Date;

#[ORM\Entity(repositoryClass: CompetenceRepository::class)]
class Competence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 25)]
    private ?string $nom = null;
    #[ORM\Column(length: 25)]
    private ?string $categorie = null;
    #[ORM\Column(length: 25)]
    private ?int $niveau = null;
    #[ORM\Column(length: 4)]
    private ?int $dureeDePratique = null;

    #[ORM\ManyToOne(inversedBy: 'competences')]
    private ?Utilisateur $competence = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }













    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): self
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getNiveau(): ?int
    {
        return $this->niveau;
    }

    public function setNiveau(?int $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getDureeDePratique(): ?string
    {
        return $this->dureeDePratique;
    }

    public function setDureeDePratique(?string $dureeDePratique): self
    {
        $this->dureeDePratique = $dureeDePratique;
        return $this;
    }

    public function getCompetence(): ?Utilisateur
    {
        return $this->competence;
    }

    public function setCompetence(?Utilisateur $competence): static
    {
        $this->competence = $competence;

        return $this;
    }
}
