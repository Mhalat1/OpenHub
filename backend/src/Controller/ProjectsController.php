<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ProjectsController extends AbstractController
{
    private EntityManagerInterface $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
    }

    #[Route('/api/allprojects', name: 'app_all_projects', methods: ['GET'])]
    public function projects(): JsonResponse
    {
        $allProjects = $this->manager->getRepository(Project::class)->findAll();

        $data = [];
        foreach ($allProjects as $project) {
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
    public function userProjects(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $projects = $user->getProject();

        $data = [];
        foreach ($projects as $project) {
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

    #[Route('/api/add/project', name: 'app_add_project', methods: ['POST'])]
    public function addProject(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $projectId = $data['project_id'] ?? null;

        if (!$projectId) {
            return new JsonResponse(['message' => 'Missing project_id'], 400);
        }

        $project = $this->manager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            return new JsonResponse(['message' => 'Project not found'], 404);
        }

        // Vérifie si l'utilisateur a déjà ce projet
        if ($user->getProject()->contains($project)) {
            return new JsonResponse(['message' => 'Project already added to user'], 400);
        }

        // Ajout du projet à l'utilisateur
        $user->addProject($project);
        $this->manager->persist($user);
        $this->manager->flush();

        
    // 8. Réponse de succès
    return new JsonResponse([
        'success' => true,
        'message' => 'Compétence ajoutée avec succès',
        'project_name' => $project->getName()
    ], 201);

    }
}