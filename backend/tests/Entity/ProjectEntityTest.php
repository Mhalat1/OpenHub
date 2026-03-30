<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ProjectEntityTest extends TestCase
{
    private Project $project;

    protected function setUp(): void
    {
        $this->project = new Project();
        // Initialiser toutes les propriétés requises pour éviter les erreurs d'accès
        $this->project->setName('Test Project');
        $this->project->setDescription('Test Description');
        $this->project->setRequiredSkills('PHP, Symfony');
        $this->project->setStartDate(new DateTimeImmutable());
        $this->project->setEndDate(new DateTimeImmutable());
    }

    public function testEntityCanBeInstantiated(): void
    {
        $project = new Project();
        $this->assertInstanceOf(Project::class, $project);
        
        // Initialiser pour éviter les erreurs dans les tests suivants
        $project->setName('Test');
    }


    public function testSetAndGetId(): void
    {
        $id = 42;
        
        // Créer un projet avec des valeurs initiales
        $project = new Project();
        $project->setName('Test');
        
        $result = $project->setId($id);
        
        $this->assertSame($project, $result);
        $this->assertEquals($id, $project->getId());
    }

    public function testSetAndGetName(): void
    {
        $project = new Project();
        $name = 'Projet Alpha';
        
        $result = $project->setName($name);
        
        $this->assertSame($project, $result);
        $this->assertEquals($name, $project->getName());
    }

    public function testNameCanBeEmptyString(): void
    {
        $project = new Project();
        $name = '';
        
        $project->setName($name);
        
        $this->assertEquals('', $project->getName());
    }

    public function testSetAndGetDescription(): void
    {
        $project = new Project();
        $project->setName('Test');
        $description = 'Ceci est une description détaillée du projet';
        
        $result = $project->setDescription($description);
        
        $this->assertSame($project, $result);
        $this->assertEquals($description, $project->getDescription());
    }

    public function testDescriptionCanBeEmptyString(): void
    {
        $project = new Project();
        $project->setName('Test');
        $description = '';
        
        $project->setDescription($description);
        
        $this->assertEquals('', $project->getDescription());
    }

    public function testSetAndGetRequiredSkills(): void
    {
        $project = new Project();
        $project->setName('Test');
        $skills = 'PHP, Symfony, MySQL';
        
        $result = $project->setRequiredSkills($skills);
        
        $this->assertSame($project, $result);
        $this->assertEquals($skills, $project->getRequiredSkills());
    }



    public function testRequiredSkillsCanBeEmptyString(): void
    {
        $project = new Project();
        $project->setName('Test');
        $project->setRequiredSkills('');
        
        $this->assertEquals('', $project->getRequiredSkills());
    }

    public function testSetAndGetStartDate(): void
    {
        $project = new Project();
        $project->setName('Test');
        $date = new DateTimeImmutable('2024-01-15 10:30:00');
        
        $result = $project->setStartDate($date);
        
        $this->assertSame($project, $result);
        $this->assertSame($date, $project->getStartDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $project->getStartDate());
    }



    public function testSetAndGetEndDate(): void
    {
        $project = new Project();
        $project->setName('Test');
        $date = new DateTimeImmutable('2024-12-31 23:59:59');
        
        $result = $project->setEndDate($date);
        
        $this->assertSame($project, $result);
        $this->assertSame($date, $project->getEndDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $project->getEndDate());
    }



    public function testCompleteProjectCreation(): void
    {
        $project = new Project();
        $id = 1;
        $name = 'Projet Test Complet';
        $description = 'Description complète du projet de test';
        $skills = 'PHP, Symfony, Docker, Kubernetes';
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-06-30');

        $project->setId($id)
                ->setName($name)
                ->setDescription($description)
                ->setRequiredSkills($skills)
                ->setStartDate($startDate)
                ->setEndDate($endDate);

        $this->assertEquals($id, $project->getId());
        $this->assertEquals($name, $project->getName());
        $this->assertEquals($description, $project->getDescription());
        $this->assertEquals($skills, $project->getRequiredSkills());
        $this->assertSame($startDate, $project->getStartDate());
        $this->assertSame($endDate, $project->getEndDate());
    }

    public function testDatesAreImmutable(): void
    {
        $project = new Project();
        $project->setName('Test');
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $project->setStartDate($startDate);
        $project->setEndDate($endDate);
        
        $this->assertInstanceOf(DateTimeImmutable::class, $project->getStartDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $project->getEndDate());
        
        $modifiedStart = $project->getStartDate()->modify('+1 day');
        $modifiedEnd = $project->getEndDate()->modify('-1 month');
        
        $this->assertNotEquals($modifiedStart, $project->getStartDate());
        $this->assertNotEquals($modifiedEnd, $project->getEndDate());
    }

    public function testEndDateCanBeAfterStartDate(): void
    {
        $project = new Project();
        $project->setName('Test');
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $project->setStartDate($startDate);
        $project->setEndDate($endDate);
        
        $this->assertTrue($project->getEndDate() > $project->getStartDate());
    }

    public function testEndDateCanBeSameAsStartDate(): void
    {
        $project = new Project();
        $project->setName('Test');
        $date = new DateTimeImmutable('2024-01-01');
        
        $project->setStartDate($date);
        $project->setEndDate($date);
        
        $this->assertEquals($project->getStartDate(), $project->getEndDate());
    }

    public function testStartDateCanBeAfterEndDate(): void
    {
        $project = new Project();
        $project->setName('Test');
        $startDate = new DateTimeImmutable('2024-12-31');
        $endDate = new DateTimeImmutable('2024-01-01');
        
        $project->setStartDate($startDate);
        $project->setEndDate($endDate);
        
        $this->assertTrue($project->getStartDate() > $project->getEndDate());
    }

    public function testRequiredSkillsIsString(): void
    {
        $project = new Project();
        $project->setName('Test');
        $skills = 'PHP, Symfony';
        
        $project->setRequiredSkills($skills);
        
        $this->assertIsString($project->getRequiredSkills());
    }

    public function testNameMaxLength(): void
    {
        $project = new Project();
        $name = str_repeat('a', 25);
        
        $project->setName($name);
        
        $this->assertEquals(25, strlen($project->getName()));
    }


    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass(Project::class);
        
        $classAttributes = $reflection->getAttributes();
        $hasEntityAttribute = false;
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Entity') {
                $hasEntityAttribute = true;
                break;
            }
        }
        $this->assertTrue($hasEntityAttribute, 'Class should have Entity attribute');
        
        $properties = ['id', 'name', 'description', 'requiredSkills', 'startDate', 'endDate'];
        
        foreach ($properties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName), "Property $propertyName should exist");
            
            $property = $reflection->getProperty($propertyName);
            $attributes = $property->getAttributes();
            
            $hasColumnAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Column') {
                    $hasColumnAttribute = true;
                    break;
                }
            }
            $this->assertTrue($hasColumnAttribute, "Property $propertyName should have Column attribute");
        }
        
        $nameProperty = $reflection->getProperty('name');
        $nameAttributes = $nameProperty->getAttributes();
        $hasLengthConstraint = false;
        foreach ($nameAttributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Column') {
                $args = $attribute->getArguments();
                if (isset($args['length']) && $args['length'] === 25) {
                    $hasLengthConstraint = true;
                }
                break;
            }
        }
        $this->assertTrue($hasLengthConstraint, 'name property should have length=25 constraint');
    }

    public function testFluentInterfaceChain(): void
    {
        $project = new Project();
        $result = $project
            ->setId(1)
            ->setName('Test')
            ->setDescription('Description')
            ->setRequiredSkills('Skills')
            ->setStartDate(new DateTimeImmutable())
            ->setEndDate(new DateTimeImmutable());

        $this->assertSame($project, $result);
    }

    public function testRepositoryClass(): void
    {
        $reflection = new \ReflectionClass(Project::class);
        $attributes = $reflection->getAttributes();
        
        $hasRepositoryClass = false;
        $repositoryClass = null;
        
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Entity') {
                $args = $attribute->getArguments();
                if (isset($args['repositoryClass'])) {
                    $hasRepositoryClass = true;
                    $repositoryClass = $args['repositoryClass'];
                }
                break;
            }
        }
        
        $this->assertTrue($hasRepositoryClass, 'Entity should have repositoryClass');
        $this->assertEquals('App\\Repository\\ProjectRepository', $repositoryClass);
    }

}