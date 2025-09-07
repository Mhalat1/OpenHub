<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Projet;
use Doctrine\ORM\EntityManagerInterface;


final class ProjetController extends AbstractController
{
    #[Route('/api/projet', name: 'app_projet', methods: ['GET'])]
    public function projet(EntityManagerInterface $entityManager): Response
    {

        $projet = $entityManager->getRepository(Projet::class)->findAll();
        return $this->json($projet);
    }
}
