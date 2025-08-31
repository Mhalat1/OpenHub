<?php

namespace App\Entity;

use App\Repository\ContributeurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContributeurRepository::class)]
class Contributeur extends Utilisateur
{
    public function getRoles(): array
    {
        return ['ROLE_CONTRIB'];
    }


    #[ORM\Column(length: 25)]
    private ?string $projetParticipe = null;


    public function getProjetParticipe(): ?string
    {
        return $this->projetParticipe;
    }

    public function setProjetParticipe(string $projetParticipe): static
    {
        $this->projetParticipe = $projetParticipe;

        return $this;
    }




    #[ORM\Column(type: "string", nullable: true)]
    private $tokenapi;

    // Getter et setter pour tokenapi
    public function getTokenapi(): ?string
    {
        return $this->tokenapi;
    }

    public function setTokenapi(?string $tokenapi): self
    {
        $this->tokenapi = $tokenapi;
        return $this;

    }

}
