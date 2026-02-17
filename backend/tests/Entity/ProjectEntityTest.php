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
    }

    public function testEntityCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Project::class, $this->project);
    }

    public function testIdIsInitiallyNull(): void
    {
        $this->assertNull($this->project->getId());
    }

    public function testSetAndGetId(): void
    {
        $id = 42;
        
        $result = $this->project->setId($id);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertEquals($id, $this->project->getId());
    }

    public function testSetAndGetName(): void
    {
        $name = 'Projet Alpha';
        
        $this->assertNull($this->project->getName());
        
        $result = $this->project->setName($name);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertEquals($name, $this->project->getName());
    }

    public function testNameCanBeEmptyString(): void
    {
        $name = '';
        
        $this->project->setName($name);
        
        $this->assertEquals('', $this->project->getName());
    }

    public function testSetAndGetDescription(): void
    {
        $description = 'Ceci est une description détaillée du projet';
        
        $this->assertNull($this->project->getDescription());
        
        $result = $this->project->setDescription($description);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertEquals($description, $this->project->getDescription());
    }

    public function testDescriptionCanBeEmptyString(): void
    {
        $description = '';
        
        $this->project->setDescription($description);
        
        $this->assertEquals('', $this->project->getDescription());
    }

    public function testSetAndGetRequiredSkills(): void
    {
        $skills = 'PHP, Symfony, MySQL';
        
        $this->assertNull($this->project->getRequiredSkills());
        
        $result = $this->project->setRequiredSkills($skills);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertEquals($skills, $this->project->getRequiredSkills());
    }

    public function testRequiredSkillsCanBeNull(): void
    {
        $this->project->setRequiredSkills(null);
        
        $this->assertNull($this->project->getRequiredSkills());
    }

    public function testRequiredSkillsCanBeEmptyString(): void
    {
        $this->project->setRequiredSkills('');
        
        $this->assertEquals('', $this->project->getRequiredSkills());
    }

    public function testSetAndGetStartDate(): void
    {
        $date = new DateTimeImmutable('2024-01-15 10:30:00');
        
        $this->assertNull($this->project->getStartDate());
        
        $result = $this->project->setStartDate($date);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertSame($date, $this->project->getStartDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->project->getStartDate());
    }

    public function testStartDateCanBeNull(): void
    {
        $this->project->setStartDate(null);
        
        $this->assertNull($this->project->getStartDate());
    }

    public function testSetAndGetEndDate(): void
    {
        $date = new DateTimeImmutable('2024-12-31 23:59:59');
        
        $this->assertNull($this->project->getEndDate());
        
        $result = $this->project->setEndDate($date);
        
        $this->assertSame($this->project, $result); // Test fluent interface
        $this->assertSame($date, $this->project->getEndDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->project->getEndDate());
    }

    public function testEndDateCanBeNull(): void
    {
        $this->project->setEndDate(null);
        
        $this->assertNull($this->project->getEndDate());
    }

    public function testCompleteProjectCreation(): void
    {
        $id = 1;
        $name = 'Projet Test Complet';
        $description = 'Description complète du projet de test';
        $skills = 'PHP, Symfony, Docker, Kubernetes';
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-06-30');

        $this->project->setId($id)
                      ->setName($name)
                      ->setDescription($description)
                      ->setRequiredSkills($skills)
                      ->setStartDate($startDate)
                      ->setEndDate($endDate);

        $this->assertEquals($id, $this->project->getId());
        $this->assertEquals($name, $this->project->getName());
        $this->assertEquals($description, $this->project->getDescription());
        $this->assertEquals($skills, $this->project->getRequiredSkills());
        $this->assertSame($startDate, $this->project->getStartDate());
        $this->assertSame($endDate, $this->project->getEndDate());
    }

    public function testDatesAreImmutable(): void
    {
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $this->project->setStartDate($startDate);
        $this->project->setEndDate($endDate);
        
        // Vérifier que les dates sont bien des DateTimeImmutable
        $this->assertInstanceOf(DateTimeImmutable::class, $this->project->getStartDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->project->getEndDate());
        
        // Tenter de modifier les dates retournées ne devrait pas affecter l'entité
        $modifiedStart = $this->project->getStartDate()->modify('+1 day');
        $modifiedEnd = $this->project->getEndDate()->modify('-1 month');
        
        $this->assertNotEquals($modifiedStart, $this->project->getStartDate());
        $this->assertNotEquals($modifiedEnd, $this->project->getEndDate());
    }

    public function testEndDateCanBeAfterStartDate(): void
    {
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $this->project->setStartDate($startDate);
        $this->project->setEndDate($endDate);
        
        $this->assertTrue($this->project->getEndDate() > $this->project->getStartDate());
    }

    public function testEndDateCanBeSameAsStartDate(): void
    {
        $date = new DateTimeImmutable('2024-01-01');
        
        $this->project->setStartDate($date);
        $this->project->setEndDate($date);
        
        $this->assertEquals($this->project->getStartDate(), $this->project->getEndDate());
    }

    public function testStartDateCanBeAfterEndDate(): void
    {
        // Ce test valide que l'entité permet cette configuration
        // (la validation métier devrait être faite ailleurs)
        $startDate = new DateTimeImmutable('2024-12-31');
        $endDate = new DateTimeImmutable('2024-01-01');
        
        $this->project->setStartDate($startDate);
        $this->project->setEndDate($endDate);
        
        $this->assertTrue($this->project->getStartDate() > $this->project->getEndDate());
    }

    public function testRequiredSkillsIsString(): void
    {
        $skills = 'PHP, Symfony';
        
        $this->project->setRequiredSkills($skills);
        
        $this->assertIsString($this->project->getRequiredSkills());
    }

    public function testNameMaxLength(): void
    {
        // Créer un nom de 25 caractères (la limite dans l'entité)
        $name = str_repeat('a', 25);
        
        $this->project->setName($name);
        
        $this->assertEquals(25, strlen($this->project->getName()));
    }

    /**
     * Vérifie que les propriétés ont les types corrects
     */
    public function testPropertyTypes(): void
    {
        $reflection = new \ReflectionClass($this->project);
        
        // Vérifier le type de l'ID
        $idProperty = $reflection->getProperty('id');
        $idType = $idProperty->getType();
        $this->assertNotNull($idType);
        $this->assertTrue($idType->allowsNull());
        $this->assertEquals('int', $idType->getName());
        
        // Vérifier le type du nom
        $nameProperty = $reflection->getProperty('name');
        $nameType = $nameProperty->getType();
        $this->assertNotNull($nameType);
        // Correction: name peut être null d'après le setter/getter (?string)
        $this->assertTrue($nameType->allowsNull(), 'name property should allow null');
        $this->assertEquals('string', $nameType->getName());
        
        // Vérifier le type de la description
        $descProperty = $reflection->getProperty('description');
        $descType = $descProperty->getType();
        $this->assertNotNull($descType);
        // Correction: description peut être null d'après le setter/getter (?string)
        $this->assertTrue($descType->allowsNull(), 'description property should allow null');
        $this->assertEquals('string', $descType->getName());
        
        // Vérifier le type des compétences requises
        $skillsProperty = $reflection->getProperty('requiredSkills');
        $skillsType = $skillsProperty->getType();
        $this->assertNotNull($skillsType);
        $this->assertTrue($skillsType->allowsNull()); // requiredSkills peut être null
        $this->assertEquals('string', $skillsType->getName());
        
        // Vérifier le type des dates
        $startDateProperty = $reflection->getProperty('startDate');
        $startDateType = $startDateProperty->getType();
        $this->assertNotNull($startDateType);
        $this->assertTrue($startDateType->allowsNull()); // startDate peut être null
        $this->assertEquals(DateTimeImmutable::class, $startDateType->getName());
        
        $endDateProperty = $reflection->getProperty('endDate');
        $endDateType = $endDateProperty->getType();
        $this->assertNotNull($endDateType);
        $this->assertTrue($endDateType->allowsNull()); // endDate peut être null
        $this->assertEquals(DateTimeImmutable::class, $endDateType->getName());
    }

    /**
     * Vérifie les attributs Doctrine
     */
    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass($this->project);
        
        // Vérifier que la classe a l'attribut Entity
        $classAttributes = $reflection->getAttributes();
        $hasEntityAttribute = false;
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Entity') {
                $hasEntityAttribute = true;
                break;
            }
        }
        $this->assertTrue($hasEntityAttribute, 'Class should have Entity attribute');
        
        // Vérifier les propriétés principales
        $properties = ['id', 'name', 'description', 'requiredSkills', 'startDate', 'endDate'];
        
        foreach ($properties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName), "Property $propertyName should exist");
            
            $property = $reflection->getProperty($propertyName);
            $attributes = $property->getAttributes();
            
            // Chaque propriété devrait avoir l'attribut Column
            $hasColumnAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Column') {
                    $hasColumnAttribute = true;
                    break;
                }
            }
            $this->assertTrue($hasColumnAttribute, "Property $propertyName should have Column attribute");
        }
        
        // Vérification spécifique pour les contraintes de longueur
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
        $result = $this->project
            ->setId(1)
            ->setName('Test')
            ->setDescription('Description')
            ->setRequiredSkills('Skills')
            ->setStartDate(new DateTimeImmutable())
            ->setEndDate(new DateTimeImmutable());

        $this->assertSame($this->project, $result);
    }

    /**
     * Teste la cohérence avec le repository (via l'attribut)
     */
    public function testRepositoryClass(): void
    {
        $reflection = new \ReflectionClass($this->project);
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

    /**
     * Teste que les valeurs par défaut sont correctes
     */
    public function testDefaultValues(): void
    {
        $project = new Project();
        
        $this->assertNull($project->getId());
        $this->assertNull($project->getName());
        $this->assertNull($project->getDescription());
        $this->assertNull($project->getRequiredSkills());
        $this->assertNull($project->getStartDate());
        $this->assertNull($project->getEndDate());
    }
}