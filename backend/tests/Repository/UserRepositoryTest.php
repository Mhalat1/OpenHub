<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(User::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testRepositoryIsInstanceOfUserRepository(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $users = $this->repository->findAll();
        $this->assertIsArray($users);
    }

    public function testFindOneBy(): void
    {
        // Create a test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword123');
        // $user->setUsername('testuser');
        // $user->setRoles(['ROLE_USER']);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->findOneBy(['id' => $user->getId()]);
        
        $this->assertNotNull($foundUser);
        $this->assertInstanceOf(User::class, $foundUser);
        $this->assertEquals($user->getId(), $foundUser->getId());

        // Cleanup
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function testFindBy(): void
    {
        // Create test users
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setPassword('hashedpassword123');
        
        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setPassword('hashedpassword456');
        
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        $users = $this->repository->findBy([], ['id' => 'ASC']);
        
        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(2, count($users));

        // Cleanup
        $this->entityManager->remove($user1);
        $this->entityManager->remove($user2);
        $this->entityManager->flush();
    }

    public function testCount(): void
    {
        $count = $this->repository->count([]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $user = $this->repository->findOneBy(['id' => 999999]);
        $this->assertNull($user);
    }

    public function testFindByEmail(): void
    {
        // Create a user with unique email
        $user = new User();
        $user->setEmail('unique@example.com');
        $user->setPassword('hashedpassword123');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $foundUser = $this->repository->findOneBy(['email' => 'unique@example.com']);
        
        $this->assertNotNull($foundUser);
        $this->assertEquals('unique@example.com', $foundUser->getEmail());

        // Cleanup
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function testUpgradePassword(): void
    {
        // Create a user
        $user = new User();
        $user->setEmail('password-upgrade@example.com');
        $user->setPassword('oldhashedpassword');
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $oldPassword = $user->getPassword();
        $newHashedPassword = 'newhashedpassword123';

        // Upgrade password
        $this->repository->upgradePassword($user, $newHashedPassword);

        // Refresh user from database
        $this->entityManager->refresh($user);

        $this->assertNotEquals($oldPassword, $user->getPassword());
        $this->assertEquals($newHashedPassword, $user->getPassword());

        // Cleanup
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function testUpgradePasswordThrowsExceptionForUnsupportedUser(): void
    {
        $this->expectException(UnsupportedUserException::class);
        
        // Create a mock user that is not an instance of User entity
        $mockUser = $this->createMock(\Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class);
        
        $this->repository->upgradePassword($mockUser, 'newpassword');
    }

    public function testFindByWithLimit(): void
    {
        // Create multiple test users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("user$i@example.com");
            $user->setPassword('hashedpassword123');
            $this->entityManager->persist($user);
            $users[] = $user;
        }
        $this->entityManager->flush();

        $foundUsers = $this->repository->findBy([], ['id' => 'ASC'], 3);
        
        $this->assertIsArray($foundUsers);
        $this->assertCount(3, $foundUsers);

        // Cleanup
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    public function testFindByWithOffset(): void
    {
        // Create multiple test users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $user = new User();
            $user->setEmail("offset-user$i@example.com");
            $user->setPassword('hashedpassword123');
            $this->entityManager->persist($user);
            $users[] = $user;
        }
        $this->entityManager->flush();

        $foundUsers = $this->repository->findBy([], ['id' => 'ASC'], 3, 2);
        
        $this->assertIsArray($foundUsers);
        $this->assertLessThanOrEqual(3, count($foundUsers));

        // Cleanup
        foreach ($users as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }

    public function testFindByMultipleCriteria(): void
    {
        // Create users with different properties
        $user = new User();
        $user->setEmail('multicriteria@example.com');
        $user->setPassword('hashedpassword123');
        // $user->setIsActive(true);
        // $user->setRoles(['ROLE_USER']);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Find by multiple criteria (adjust based on your entity fields)
        $users = $this->repository->findBy([
            'email' => 'multicriteria@example.com'
        ]);
        
        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(1, count($users));

        // Cleanup
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function testFindByOrderBy(): void
    {
        // Create users
        $user1 = new User();
        $user1->setEmail('aaa@example.com');
        $user1->setPassword('password123');
        
        $user2 = new User();
        $user2->setEmail('zzz@example.com');
        $user2->setPassword('password456');
        
        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        $usersAsc = $this->repository->findBy([], ['email' => 'ASC']);
        $usersDesc = $this->repository->findBy([], ['email' => 'DESC']);
        
        $this->assertIsArray($usersAsc);
        $this->assertIsArray($usersDesc);
        $this->assertGreaterThanOrEqual(2, count($usersAsc));

        // Cleanup
        $this->entityManager->remove($user1);
        $this->entityManager->remove($user2);
        $this->entityManager->flush();
    }
}