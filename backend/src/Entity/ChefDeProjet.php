<?php

namespace App\Entity;

use App\Repository\ChefDeProjetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChefDeProjetRepository::class)]
class ChefDeProjet extends Utilisateur
{
    public function getRoles(): array
    {
        return ['ROLE_CHEF'];
    }


    #[ORM\Column(length: 25)]
    private ?string $projetCree = null;


    public function getProjetsCree(): ?string
    {
        return $this->projetCree;
    }

    public function setProjetsCree(string $projetsCrees): static
    {
        $this->projetCree = $projetsCrees;

        return $this;
    }
}
