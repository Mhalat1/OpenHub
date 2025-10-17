<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;



final class ProjectsController extends AbstractController
{
    private EntityManagerInterface $manager;
    private $userRepository;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
        $this->userRepository = $this->manager->getRepository(User::class);
    }

#[Route('/api/allprojects', name: 'app_all_projects', methods: ['GET'])]
public function projects(EntityManagerInterface $entityManager): JsonResponse
{
    $allprojects = $entityManager->getRepository(Project::class)->findAll();

    $data = [];

    foreach ($allprojects as $project) {
        $data[] = [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'requiredSkills' => $project->getRequiredSkills(),
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
        ];
    }

    return new JsonResponse($data);
}





    #[Route('/api/user/projects', name: 'app_user_projects', methods: ['GET'])]
    public function Userprojects(EntityManagerInterface $entityManager, Security $security): Response
    {


        $user = $security->getUser();

    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }
    // Récupération directe des compétences via la relation ManyToMany
    $projects = $user->getProjects();

    // Conversion en tableau simple
    $data = [];
    foreach ($projects as $project) {
        $data[] = [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'descripyion' => $project->getDescription(),
            'requiredSkills' => $project->getRequiredSkills(),
            'startDate' => $project->getStartDate()?->format('Y-m-d'),
            'endDate' => $project->getEndDate()?->format('Y-m-d'),
        ];
    }

    return new JsonResponse($data);
    }
}
