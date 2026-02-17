<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository $userRepository;
    private UserService $userService;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userService = new UserService($this->userRepository);
    }

    public function testFindAllReturnsEmptyArray(): void
    {
        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn([]);

        $result = $this->userService->findAll();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindAllReturnsSingleUser(): void
    {
        $user = $this->createMock(User::class);

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn([$user]);

        $result = $this->userService->findAll();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($user, $result[0]);
    }

    public function testFindAllReturnsMultipleUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        $user3 = $this->createMock(User::class);

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn([$user1, $user2, $user3]);

        $result = $this->userService->findAll();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertSame($user2, $result[1]);
        $this->assertSame($user3, $result[2]);
    }

    public function testFindAllCallsRepositoryWithCorrectParameters(): void
    {
        $expectedCriteria = [];
        $expectedOrderBy = ['firstName' => 'ASC'];

        $this->userRepository
            ->method('findBy')
            ->with($expectedCriteria, $expectedOrderBy)
            ->willReturn([]);

        $this->userService->findAll();

        // Le test passe si aucune exception n'est levée
        $this->assertTrue(true);
    }

    public function testFindAllOrdersByFirstNameAscending(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getFirstName')->willReturn('Alice');

        $user2 = $this->createMock(User::class);
        $user2->method('getFirstName')->willReturn('Bob');

        $user3 = $this->createMock(User::class);
        $user3->method('getFirstName')->willReturn('Charlie');

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn([$user1, $user2, $user3]);

        $result = $this->userService->findAll();

        $this->assertEquals('Alice', $result[0]->getFirstName());
        $this->assertEquals('Bob', $result[1]->getFirstName());
        $this->assertEquals('Charlie', $result[2]->getFirstName());
    }

    public function testConstructorAcceptsUserRepository(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userService = new UserService($userRepository);

        $this->assertInstanceOf(UserService::class, $userService);
    }

    public function testFindAllReturnsArrayOfUsers(): void
    {
        $users = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn($users);

        $result = $this->userService->findAll();

        $this->assertIsArray($result);
        foreach ($result as $user) {
            $this->assertInstanceOf(User::class, $user);
        }
    }

    public function testFindAllDelegatesCorrectlyToRepository(): void
    {
        $expectedUsers = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn($expectedUsers);

        $result = $this->userService->findAll();

        $this->assertSame($expectedUsers, $result);
    }

    public function testFindAllWithManyUsers(): void
    {
        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = $this->createMock(User::class);
        }

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn($users);

        $result = $this->userService->findAll();

        $this->assertCount(10, $result);
        $this->assertIsArray($result);
    }

    public function testFindAllReturnsSameOrderAsRepository(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        $user3 = $this->createMock(User::class);

        $orderedUsers = [$user1, $user2, $user3];

        $this->userRepository
            ->method('findBy')
            ->with([], ['firstName' => 'ASC'])
            ->willReturn($orderedUsers);

        $result = $this->userService->findAll();

        $this->assertSame($orderedUsers[0], $result[0]);
        $this->assertSame($orderedUsers[1], $result[1]);
        $this->assertSame($orderedUsers[2], $result[2]);
    }
}