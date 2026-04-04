<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Service\AxiomService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ProjectsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AxiomService $AxiomLogger,
    ) {}

    #[Route('/api/allprojects', name: 'app_all_projects', methods: ['GET'])]
    public function projects(): JsonResponse
    {
        try {
            $allProjects = $this->manager->getRepository(Project::class)->findAll();

            $this->AxiomLogger->info('All projects fetched', [
                'count' => count($allProjects),
            ]);

            $data = array_map(fn(Project $p) => [
                'id'             => $p->getId(),
                'name'           => $p->getName(),
                'description'    => $p->getDescription(),
                'requiredSkills' => $p->getRequiredSkills(),
                'startDate'      => $p->getStartDate()?->format('Y-m-d'),
                'endDate'        => $p->getEndDate()?->format('Y-m-d'),
            ], $allProjects);

            return new JsonResponse($data);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('allprojects crashed', [
                'exception' => $e->getMessage(),
                'class'     => get_class($e),
            ]);
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }


    #[Route('/api/user/add/project', name: 'app_add_project', methods: ['POST'])]
    public function addProject(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('Unauthenticated access to add project');
           
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $data      = json_decode($request->getContent(), true);
        $projectId = $data['project_id'] ?? null;

        if (!$projectId) {
            $this->AxiomLogger->warning('Add project - missing project_id', [
                'user_id' => $user->getId(),
            ]);
            return new JsonResponse(['message' => 'Missing project_id'], 400);
        }

        $project = $this->manager->getRepository(Project::class)->find($projectId);
        if (!$project) {
            $this->AxiomLogger->warning('Add project - project not found', [
                'user_id'    => $user->getId(),
                'project_id' => $projectId,
            ]);
            return new JsonResponse(['message' => 'Project not found'], 404);
        }

        if ($user->getProject()->contains($project)) {
            $this->AxiomLogger->warning('Add project - already added', [
                'user_id'      => $user->getId(),
                'project_id'   => $projectId,
                'project_name' => $project->getName(),
            ]);
            return new JsonResponse(['message' => 'Project already added to user'], 400);
        }

        $user->addProject($project);
        $this->manager->persist($user);
        $this->manager->flush();
        $this->AxiomLogger->info('✅ Project added to user', [
            'user_id'      => $user->getId(),
            'project_id'   => $project->getId(),
            'project_name' => $project->getName(),
        ]);
        return new JsonResponse([
            'success'      => true,
            'message'      => 'Compétence ajoutée avec succès',
            'project_name' => $project->getName()
        ], 201);
    }
#[Route('/api/create/new/project', name: 'app_remove_project', methods: ['POST'])]
public function createProject(Request $request, Security $security): JsonResponse
{
    $user = $security->getUser();

    if (!$user instanceof User) {
        $this->AxiomLogger->warning('Unauthenticated access to create project');

        return new JsonResponse(['message' => 'User not authenticated'], 401);

    }

    $data = json_decode($request->getContent(), true);

    $name           = $data['name']           ?? null;
    $description    = $data['description']    ?? null;
    $requiredSkills = $data['requiredSkills'] ?? null;

    if (!$name || !$description || !$requiredSkills || !isset($data['startDate']) || !isset($data['endDate'])) {
           
        $this->AxiomLogger->warning('Create project - missing required fields', [
            'has_name'           => !empty($name),
            'has_description'    => !empty($description),
            'has_requiredSkills' => !empty($requiredSkills),
            'has_startDate'      => isset($data['startDate']),
            'has_endDate'        => isset($data['endDate']),
        ]);
        return new JsonResponse(['message' => 'Missing required fields'], 400);
    }

    try {
        $startDate = new \DateTimeImmutable($data['startDate']);
        $endDate   = new \DateTimeImmutable($data['endDate']);
    } catch (\Exception $e) {
        $this->AxiomLogger->warning('Create project - invalid date format', [
            'error' => $e->getMessage(),
        ]);

        return new JsonResponse(['message' => 'Invalid date format'], 400);
    }

    $project = new Project();
    $project->setName($name);
    $project->setDescription($description);
    $project->setRequiredSkills($requiredSkills);
    $project->setStartDate($startDate);
    $project->setEndDate($endDate);
    $project->setUser($user);

    try {
        $this->manager->persist($project);
        $this->manager->flush();
    } catch (\Exception $e) {
        $this->AxiomLogger->error('❌ Failed to save project to database', [
            'error'        => $e->getMessage(),
            'project_name' => $name,
            'user_id'      => $user->getId(),
        ]);
        return new JsonResponse(['message' => 'Failed to create project'], 500);
    }
    $this->AxiomLogger->info('✅ Project created', [
        'project_id'   => $project->getId(),
        'project_name' => $project->getName(),
    ]);

    return new JsonResponse([
        'message'    => 'Project created successfully',
        'project_id' => $project->getId()
    ], 201);
}

    #[Route('/api/modify/project/{id}', name: 'app_modify_project', methods: ['PUT', 'PATCH'])]
    public function modifyProject(int $id, Request $request): JsonResponse
    {
        $project = $this->manager->getRepository(Project::class)->find($id);

        if (!$project) {
            $this->AxiomLogger->warning('Modify project - project not found', [
                'project_id' => $id,
            ]);

            return new JsonResponse(['message' => 'Project not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $project->setName($data['name']);
        }

        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }

        if (isset($data['requiredSkills'])) {
            $project->setRequiredSkills($data['requiredSkills']);
        }

        if (isset($data['startDate'])) {
            try {
                $project->setStartDate(new \DateTimeImmutable($data['startDate']));
            } catch (\Exception $e) {
                $this->AxiomLogger->warning('Modify project - invalid startDate', [
                    'project_id' => $id,
                    'value'      => $data['startDate'],
                    'error'      => $e->getMessage(),
                ]);

                return new JsonResponse(['message' => 'Invalid startDate format'], 400);
            }
        }

        if (isset($data['endDate'])) {
            try {
                $project->setEndDate(new \DateTimeImmutable($data['endDate']));
            } catch (\Exception $e) {
                $this->AxiomLogger->warning('Modify project - invalid endDate', [
                    'project_id' => $id,
                    'value'      => $data['endDate'],
                    'error'      => $e->getMessage(),
                ]);
                return new JsonResponse(['message' => 'Invalid endDate format'], 400);
            }
        }

        $this->manager->flush();
        $this->AxiomLogger->info('✅ Project updated', [
            'project_id'   => $project->getId(),
            'project_name' => $project->getName(),
            'fields'       => array_keys($data),
        ]);
        return new JsonResponse([
            'message'    => 'Project updated successfully',
            'project_id' => $project->getId() 
        ], 200);
    }

    #[Route('/api/delete/project/{id}', name: 'app_delete_project', methods: ['DELETE'])]
    public function deleteProject(int $id): JsonResponse
    {
        $project = $this->manager->getRepository(Project::class)->find($id);
        if (!$project) {
            $this->AxiomLogger->warning('Delete project - project not found', [
                'project_id' => $id,
            ]);
            return new JsonResponse(['message' => 'Project not found'], 404);
        }

        $projectName = $project->getName();

        $this->manager->remove($project);
        $this->manager->flush();
        //Le vrai souci c'est que dans les tests unitaires (avec mocks), 
        //Doctrine ne fait pas de vrai flush donc l'ID n'est jamais assigné par la BDD donc plante

        $this->AxiomLogger->info('✅ Project deleted', [
            'project_id'   => $id,
            'project_name' => $projectName,
        ]);


        return new JsonResponse(['message' => 'Project deleted successfully'], 200);
    }
}