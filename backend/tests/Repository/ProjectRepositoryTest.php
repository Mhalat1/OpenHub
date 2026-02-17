<?php

namespace App\Tests\Repository;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Project::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    private function createTestProject(
        string $name = 'Test Project', 
        array $requiredSkills = ['PHP', 'Symfony'],
        string $description = 'Test Description'
    ): Project {
        $project = new Project();
        $project->setName($name);
        $project->setRequiredSkills(json_encode($requiredSkills));
        $project->setDescription($description);
        $project->setStartDate(new \DateTimeImmutable('2024-01-01'));
        $project->setEndDate(new \DateTimeImmutable('2024-12-31')); // Champ requis ajouté

        
        
        $this->entityManager->persist($project);
        return $project;
    }

    public function testRepositoryIsInstanceOfProjectRepository(): void
    {
        $this->assertInstanceOf(ProjectRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $projects = $this->repository->findAll();
        $this->assertIsArray($projects);
    }

public function testFindOneBy(): void
{
    $project = $this->createTestProject('Unique Project', ['PHP', 'MySQL'], 'A unique test project');
    $this->entityManager->flush();

    $foundProject = $this->repository->findOneBy(['id' => $project->getId()]);
    
    $this->assertNotNull($foundProject);
    $this->assertInstanceOf(Project::class, $foundProject);
    $this->assertEquals($project->getId(), $foundProject->getId());
    $this->assertEquals('Unique Project', $foundProject->getName());
    
    // Correction : vérifier que c'est bien un array ET qu'il contient les bonnes valeurs
    $requiredSkills = json_decode($foundProject->getRequiredSkills(), true);
    $this->assertIsArray($requiredSkills);
    $this->assertContains('PHP', $requiredSkills);
    $this->assertContains('MySQL', $requiredSkills);
    $this->assertCount(2, $requiredSkills);
    
    $this->assertNotNull($foundProject->getStartDate());
    $this->assertNotNull($foundProject->getEndDate());

    // Cleanup
    $this->entityManager->remove($foundProject);
    $this->entityManager->flush();
}
    public function testFindBy(): void
    {
        // Create test projects with all required fields
        $project1 = $this->createTestProject('Project 1', ['PHP'], 'Description 1');
        $project2 = $this->createTestProject('Project 2', ['JavaScript'], 'Description 2');
        $this->entityManager->flush();

        $projects = $this->repository->findBy([], ['id' => 'ASC']);
        
        $this->assertIsArray($projects);
        $this->assertGreaterThanOrEqual(2, count($projects));

        // Vérifier que nos projets sont bien dans la liste
        $foundProjects = array_filter($projects, fn($p) => 
            $p->getId() === $project1->getId() || $p->getId() === $project2->getId()
        );
        $this->assertCount(2, $foundProjects);

        // Cleanup
        $this->entityManager->remove($project1);
        $this->entityManager->remove($project2);
        $this->entityManager->flush();
    }

    public function testCount(): void
    {
        $initialCount = $this->repository->count([]);
        
        // Add a test project
        $project = $this->createTestProject();
        $this->entityManager->flush();
        
        $newCount = $this->repository->count([]);
        $this->assertEquals($initialCount + 1, $newCount);

        // Cleanup
        $this->entityManager->remove($project);
        $this->entityManager->flush();
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $project = $this->repository->findOneBy(['id' => 999999]);
        $this->assertNull($project);
    }

    public function testFindByWithCriteria(): void
    {
        // Create test projects with specific criteria
        $project1 = $this->createTestProject('Active Project', ['PHP'], 'Active project description');
        // $project1->setStatus('active'); // Décommentez si vous avez un champ status
        
        $project2 = $this->createTestProject('Another Project', ['JavaScript'], 'Another description');
        // $project2->setStatus('inactive'); // Décommentez si vous avez un champ status
        
        $this->entityManager->flush();

        // Find by name criteria (ajustez selon vos champs)
        $foundProjects = $this->repository->findBy(['name' => 'Active Project']);
        
        $this->assertIsArray($foundProjects);
        $this->assertCount(1, $foundProjects);
        $this->assertEquals('Active Project', $foundProjects[0]->getName());

        // Cleanup
        $this->entityManager->remove($project1);
        $this->entityManager->remove($project2);
        $this->entityManager->flush();
    }

    public function testFindByWithLimit(): void
    {
        // Create multiple test projects
        $projects = [];
        for ($i = 1; $i <= 3; $i++) {
            $project = $this->createTestProject("Project $i", ['PHP'], "Description $i");
            $projects[] = $project;
        }
        $this->entityManager->flush();

        $foundProjects = $this->repository->findBy([], ['id' => 'ASC'], 2);
        
        $this->assertIsArray($foundProjects);
        $this->assertCount(2, $foundProjects);

        // Cleanup
        foreach ($projects as $project) {
            $this->entityManager->remove($project);
        }
        $this->entityManager->flush();
    }

    public function testFindByWithOffset(): void
    {
        // Create multiple test projects
        $projects = [];
        for ($i = 1; $i <= 3; $i++) {
            $project = $this->createTestProject("Project $i", ['PHP'], "Description $i");
            $projects[] = $project;
        }
        $this->entityManager->flush();

        // Récupérer tous les projets triés par ID
        $allProjects = $this->repository->findBy([], ['id' => 'ASC']);
        
        // Tester avec offset 1, limit 2
        $foundProjects = $this->repository->findBy([], ['id' => 'ASC'], 2, 1);
        
        $this->assertIsArray($foundProjects);
        $this->assertLessThanOrEqual(2, count($foundProjects));
        
        // Vérifier que le premier projet trouvé n'est pas celui avec le plus petit ID
        if (count($allProjects) > 1 && count($foundProjects) > 0) {
            $this->assertNotEquals($allProjects[0]->getId(), $foundProjects[0]->getId());
        }

        // Cleanup
        foreach ($projects as $project) {
            $this->entityManager->remove($project);
        }
        $this->entityManager->flush();
    }

    /**
     * Test pour trouver des projets par compétences requises
     */
    public function testFindByRequiredSkills(): void
    {
        $project1 = $this->createTestProject('PHP Project', ['PHP', 'Symfony'], 'PHP project');
        $project2 = $this->createTestProject('JS Project', ['JavaScript', 'React'], 'JS project');
        $project3 = $this->createTestProject('Full Stack', ['PHP', 'JavaScript'], 'Full stack project');
        $this->entityManager->flush();

        // Note: Cette requête peut nécessiter une méthode personnalisée dans le repository
        // Pour l'instant, on teste juste la création
        
        $this->assertNotNull($project1->getId());
        $this->assertNotNull($project2->getId());
        $this->assertNotNull($project3->getId());

        // Cleanup
        $this->entityManager->remove($project1);
        $this->entityManager->remove($project2);
        $this->entityManager->remove($project3);
        $this->entityManager->flush();
    }
}