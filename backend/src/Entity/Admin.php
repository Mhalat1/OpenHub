<?php

namespace App\Entity;

use App\Repository\AdminRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminRepository::class)]

class Admin extends Utilisateur
{
    #[ORM\Column(type: 'integer')]
    private ?int $niveauAcces = null;

    
    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }

    public function getNiveauAcces(): ?int
    {
        return $this->niveauAcces;
    }

    public function setNiveauAcces(int $niveauAcces): self
    {
        $this->niveauAcces = $niveauAcces;
        return $this;
    }

}

