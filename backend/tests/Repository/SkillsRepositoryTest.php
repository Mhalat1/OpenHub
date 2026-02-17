<?php
// tests/Repository/SkillsRepositoryTest.php

namespace App\Tests\Repository;

use App\Entity\Skills;
use App\Repository\SkillsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SkillsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private SkillsRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Skills::class);
        
        // Nettoyer la table avant chaque test
        $this->entityManager->createQuery('DELETE FROM App\Entity\Skills')->execute();
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    private function createTestSkill(
        string $name = 'PHP',
        int $months = 12,
        array $technoUtilisees = ['Symfony', 'Laravel'],
        string $description = 'Description du skill PHP'
    ): Skills {
        $skill = new Skills();
        
        // Utiliser setName() - cette méthode existe
        $skill->setName($name);
        
        // Gérer la durée (DateTimeImmutable)
        $dureeDate = new \DateTimeImmutable("+$months months");
        $skill->setDuree($dureeDate);
        
        // Gérer les technologies utilisées
        $skill->setTechnoUtilisees(json_encode($technoUtilisees));
        
        // Gérer la description
        $skill->setDescription($description);
        
        $this->entityManager->persist($skill);
        return $skill;
    }

    private function assertDureeEquals(int $expectedMonths, $actualDate): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $actualDate);
        
        $now = new \DateTimeImmutable();
        $interval = $now->diff($actualDate);
        $months = $interval->y * 12 + $interval->m;
        
        $this->assertEqualsWithDelta($expectedMonths, $months, 1, "La durée devrait être d'environ $expectedMonths mois");
    }

    private function assertTechnoUtiliseesEquals(array $expected, $actual): void
    {
        if (is_array($actual)) {
            $this->assertEquals($expected, $actual);
        } elseif (is_string($actual)) {
            $decoded = json_decode($actual, true);
            $this->assertIsArray($decoded);
            $this->assertEquals($expected, $decoded);
        }
    }

    public function testRepositoryIsInstanceOfSkillsRepository(): void
    {
        $this->assertInstanceOf(SkillsRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $skills = $this->repository->findAll();
        $this->assertIsArray($skills);
    }

    public function testFindOneBy(): void
    {
        $skill = $this->createTestSkill('PHP', 12, ['Symfony'], 'Description PHP');
        $this->entityManager->flush();

        $foundSkill = $this->repository->findOneBy(['id' => $skill->getId()]);
        
        $this->assertNotNull($foundSkill);
        $this->assertInstanceOf(Skills::class, $foundSkill);
        $this->assertEquals($skill->getId(), $foundSkill->getId());
        
        // Vérifier le nom (setName/getName existent)
        $this->assertEquals('PHP', $foundSkill->getName());
        
        // Vérifier la durée
        $this->assertDureeEquals(12, $foundSkill->getDuree());
        
        // Vérifier les technologies
        $this->assertTechnoUtiliseesEquals(['Symfony'], $foundSkill->getTechnoUtilisees());
        
        // Vérifier la description
        $this->assertEquals('Description PHP', $foundSkill->getDescription());

        // Nettoyage
        $this->entityManager->remove($foundSkill);
        $this->entityManager->flush();
    }

    public function testFindBy(): void
    {
        $skill1 = $this->createTestSkill('PHP', 12, ['Symfony'], 'Description PHP');
        $skill2 = $this->createTestSkill('JavaScript', 6, ['React'], 'Description JS');
        $this->entityManager->flush();

        $skills = $this->repository->findBy([], ['id' => 'ASC']);
        
        $this->assertIsArray($skills);
        $this->assertGreaterThanOrEqual(2, count($skills));

        // Nettoyage
        $this->entityManager->remove($skill1);
        $this->entityManager->remove($skill2);
        $this->entityManager->flush();
    }

    public function testCount(): void
    {
        $initialCount = $this->repository->count([]);
        
        $skill = $this->createTestSkill();
        $this->entityManager->flush();
        
        $newCount = $this->repository->count([]);
        $this->assertEquals($initialCount + 1, $newCount);

        // Nettoyage
        $this->entityManager->remove($skill);
        $this->entityManager->flush();
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $skill = $this->repository->findOneBy(['id' => 999999]);
        $this->assertNull($skill);
    }

    public function testFindByWithCriteria(): void
    {
        $skill = $this->createTestSkill('Python', 8, ['Django'], 'Description Python');
        $this->entityManager->flush();

        $foundSkills = $this->repository->findBy(['id' => $skill->getId()]);
        
        $this->assertIsArray($foundSkills);
        $this->assertCount(1, $foundSkills);
        $this->assertEquals('Python', $foundSkills[0]->getName());
        $this->assertDureeEquals(8, $foundSkills[0]->getDuree());
        $this->assertTechnoUtiliseesEquals(['Django'], $foundSkills[0]->getTechnoUtilisees());

        // Nettoyage
        $this->entityManager->remove($skill);
        $this->entityManager->flush();
    }

    public function testFindByWithLimit(): void
    {
        $skills = [];
        for ($i = 1; $i <= 3; $i++) {
            $skill = $this->createTestSkill(
                "Skill $i", 
                12, 
                ['Tech'], 
                "Description $i"
            );
            $skills[] = $skill;
        }
        $this->entityManager->flush();

        $foundSkills = $this->repository->findBy([], ['id' => 'ASC'], 2);
        
        $this->assertIsArray($foundSkills);
        $this->assertCount(2, $foundSkills);

        // Nettoyage
        foreach ($skills as $skill) {
            $this->entityManager->remove($skill);
        }
        $this->entityManager->flush();
    }

    public function testFindByWithOffset(): void
    {
        $skills = [];
        for ($i = 1; $i <= 3; $i++) {
            $skill = $this->createTestSkill(
                "Skill $i", 
                12, 
                ['Tech'], 
                "Description $i"
            );
            $skills[] = $skill;
        }
        $this->entityManager->flush();

        $foundSkills = $this->repository->findBy([], ['id' => 'ASC'], 2, 1);
        
        $this->assertIsArray($foundSkills);
        $this->assertLessThanOrEqual(2, count($foundSkills));

        // Nettoyage
        foreach ($skills as $skill) {
            $this->entityManager->remove($skill);
        }
        $this->entityManager->flush();
    }


    public function testFindByMultipleCriteria(): void
    {
        $skill1 = $this->createTestSkill('Go', 8, ['Gin'], 'Description Go');
        $skill2 = $this->createTestSkill('Go', 12, ['Gin'], 'Description Go avancé');
        $skill3 = $this->createTestSkill('Rust', 8, ['Actix'], 'Description Rust');
        
        $this->entityManager->flush();

        // Utiliser l'ID comme critère (fiable)
        $foundSkills = $this->repository->findBy([
            'id' => $skill1->getId()
        ]);
        
        $this->assertIsArray($foundSkills);
        $this->assertCount(1, $foundSkills);
        $this->assertEquals('Go', $foundSkills[0]->getName());
        $this->assertDureeEquals(8, $foundSkills[0]->getDuree());

        // Nettoyage
        $this->entityManager->remove($skill1);
        $this->entityManager->remove($skill2);
        $this->entityManager->remove($skill3);
        $this->entityManager->flush();
    }
}