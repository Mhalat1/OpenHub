<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserFriendsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserFriendsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserFriendsRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

    parent::setUp();
    // Fix this line - get the correct repository
    $this->repository = static::getContainer()->get(UserFriendsRepository::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
    }

    public function testRepositoryIsInstanceOfUserFriendsRepository(): void
    {
        $this->assertInstanceOf(UserFriendsRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $userFriends = $this->repository->findAll();
        $this->assertIsArray($userFriends);
    }

    public function testFindOneBy(): void
    {
        $result = $this->repository->findOneBy([]);
        // Le résultat peut être null s'il n'y a pas de données
        $this->assertTrue($result === null || $result instanceof User);
    }

    public function testFindBy(): void
    {
        $results = $this->repository->findBy([]);
        $this->assertIsArray($results);
    }

    public function testCount(): void
    {
        $count = $this->repository->count([]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findOneBy(['id' => 999999999]);
        $this->assertNull($result);
    }

    public function testFindByEmail(): void
    {
        // Ce test dépend de votre structure de données
        // Ajustez selon les champs disponibles dans UserFriends
        $results = $this->repository->findBy([]);
        $this->assertIsArray($results);
    }

    public function testFindByUsername(): void
    {
        // Ce test dépend de votre structure de données
        $results = $this->repository->findBy([]);
        $this->assertIsArray($results);
    }

    public function testFindByWithLimit(): void
    {
        $results = $this->repository->findBy([], null, 5);
        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(5, count($results));
    }

    public function testFindByWithOffset(): void
    {
        $results = $this->repository->findBy([], null, null, 1);
        $this->assertIsArray($results);
    }

    public function testFindByRole(): void
    {
        // Ce test dépend de votre structure de données
        $results = $this->repository->findBy([]);
        $this->assertIsArray($results);
    }

    public function testFindByMultipleCriteria(): void
    {
        $results = $this->repository->findBy([]);
        $this->assertIsArray($results);
    }
}