<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;


final class ProjectsController extends AbstractController
{
    #[Route('/api/projects', name: 'app_projects', methods: ['GET'])]
    public function projects(EntityManagerInterface $entityManager): Response
    {

        $projects = $entityManager->getRepository(Project::class)->findAll();
        return $this->json($projects);
    }
}
