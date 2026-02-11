<?php

namespace App\Tests\Controller;

use App\Controller\ProjectsController;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;

class ProjectsControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private User&MockObject $user;
    private Project&MockObject $project;
    private ProjectsController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->user = $this->createMock(User::class);
        $this->project = $this->createMock(Project::class);
        
        $this->controller = new ProjectsController($this->em);
    }

    // ========================================
    // TESTS: projects() - GET /api/allprojects
    // ========================================

    public function testProjectsReturnsAllProjects(): void
    {
        $project1 = $this->createMock(Project::class);
        $project1->method('getId')->willReturn(1);
        $project1->method('getName')->willReturn('Project 1');
        $project1->method('getDescription')->willReturn('Description 1');
        $project1->method('getRequiredSkills')->willReturn('PHP, Symfony');
        $project1->method('getStartDate')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $project1->method('getEndDate')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $project2 = $this->createMock(Project::class);
        $project2->method('getId')->willReturn(2);
        $project2->method('getName')->willReturn('Project 2');
        $project2->method('getDescription')->willReturn('Description 2');
        $project2->method('getRequiredSkills')->willReturn('React, Node.js');
        $project2->method('getStartDate')->willReturn(null);
        $project2->method('getEndDate')->willReturn(null);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([$project1, $project2]);
        $this->em->method('getRepository')->willReturn($repository);

        $response = $this->controller->projects();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('Project 1', $data[0]['name']);
        $this->assertEquals('Project 2', $data[1]['name']);
    }

    public function testProjectsReturnsEmptyArrayWhenNoProjects(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);
        $this->em->method('getRepository')->willReturn($repository);

        $response = $this->controller->projects();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    // ========================================
    // TESTS: userProjects() - GET /api/user/projects
    // ========================================

    public function testUserProjectsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->userProjects($this->security);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $data['message']);
    }

    public function testUserProjectsReturnsUserProjects(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('My Project');
        $project->method('getDescription')->willReturn('My Description');
        $project->method('getRequiredSkills')->willReturn('PHP');
        $project->method('getStartDate')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $project->method('getEndDate')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $projects = new ArrayCollection([$project]);
        $this->user->method('getProject')->willReturn($projects);
        $this->security->method('getUser')->willReturn($this->user);

        $response = $this->controller->userProjects($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('My Project', $data[0]['name']);
    }

    public function testUserProjectsReturnsEmptyArrayWhenUserHasNoProjects(): void
    {
        $this->user->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($this->user);

        $response = $this->controller->userProjects($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    // ========================================
    // TESTS: addProject() - POST /api/user/add/project
    // ========================================

    public function testAddProjectReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 1]));

        $response = $this->controller->addProject($request, $this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddProjectReturns400WhenProjectIdMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $this->controller->addProject($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Missing project_id', $data['message']);
    }

    public function testAddProjectReturns404WhenProjectNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 999]));

        $response = $this->controller->addProject($request, $this->security);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project not found', $data['message']);
    }

    public function testAddProjectReturns400WhenProjectAlreadyAdded(): void
    {
        $this->project->method('getName')->willReturn('Existing Project');
        
        $projects = $this->createMock(ArrayCollection::class);
        $projects->method('contains')->with($this->project)->willReturn(true);
        
        $this->user->method('getProject')->willReturn($projects);
        $this->security->method('getUser')->willReturn($this->user);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($this->project);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 1]));

        $response = $this->controller->addProject($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project already added to user', $data['message']);
    }

    public function testAddProjectSuccessfullyAddsProject(): void
    {
        $this->project->method('getName')->willReturn('New Project');
        
        $projects = $this->createMock(ArrayCollection::class);
        $projects->method('contains')->willReturn(false);
        
        $this->user->method('getProject')->willReturn($projects);
        $this->user->expects($this->once())->method('addProject')->with($this->project);
        $this->security->method('getUser')->willReturn($this->user);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($this->project);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->expects($this->once())->method('persist')->with($this->user);
        $this->em->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 1]));

        $response = $this->controller->addProject($request, $this->security);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('New Project', $data['project_name']);
    }

    // ========================================
    // TESTS: createProject() - POST /api/create/new/project
    // ========================================

    public function testCreateProjectReturns400WhenRequiredFieldsMissing(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Test Project'
            // Missing other fields
        ]));

        $response = $this->controller->createProject($request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Missing required fields', $data['message']);
    }

    public function testCreateProjectSuccessfullyCreatesProject(): void
    {
        $projectData = [
            'name' => 'New Project',
            'description' => 'Project Description',
            'requiredSkills' => 'PHP, Symfony',
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31'
        ];

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(Project::class));
        $this->em->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode($projectData));

        $response = $this->controller->createProject($request);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project created successfully', $data['message']);
        $this->assertArrayHasKey('project_id', $data);
    }

    // ========================================
    // TESTS: modifyProject() - PUT/PATCH /api/modify/project/{id}
    // ========================================

    public function testModifyProjectReturns404WhenProjectNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Updated Name']));

        $response = $this->controller->modifyProject(999, $request);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project not found', $data['message']);
    }

    public function testModifyProjectUpdatesName(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->expects($this->once())->method('setName')->with('Updated Name');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Updated Name']));

        $response = $this->controller->modifyProject(1, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project updated successfully', $data['message']);
    }

    public function testModifyProjectUpdatesMultipleFields(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->expects($this->once())->method('setName')->with('Updated Name');
        $project->expects($this->once())->method('setDescription')->with('Updated Description');
        $project->expects($this->once())->method('setRequiredSkills')->with('Updated Skills');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'requiredSkills' => 'Updated Skills'
        ]));

        $response = $this->controller->modifyProject(1, $request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testModifyProjectReturns400WhenInvalidStartDateFormat(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('setStartDate')->willThrowException(new \Exception('Invalid date'));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request([], [], [], [], [], [], json_encode([
            'startDate' => 'invalid-date'
        ]));

        $response = $this->controller->modifyProject(1, $request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid startDate format', $data['message']);
    }

    public function testModifyProjectReturns400WhenInvalidEndDateFormat(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('setEndDate')->willThrowException(new \Exception('Invalid date'));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);
        $this->em->method('getRepository')->willReturn($repository);

        $request = new Request([], [], [], [], [], [], json_encode([
            'endDate' => 'invalid-date'
        ]));

        $response = $this->controller->modifyProject(1, $request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid endDate format', $data['message']);
    }

    // ========================================
    // TESTS: deleteProject() - DELETE /api/delete/project/{id}
    // ========================================

    public function testDeleteProjectReturns404WhenProjectNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);

        $response = $this->controller->deleteProject(999);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project not found', $data['message']);
    }

    public function testDeleteProjectSuccessfullyDeletesProject(): void
    {
        $project = $this->createMock(Project::class);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturn($project);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->expects($this->once())->method('remove')->with($project);
        $this->em->expects($this->once())->method('flush');

        $response = $this->controller->deleteProject(1);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Project deleted successfully', $data['message']);
    }
}