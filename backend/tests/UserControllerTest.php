<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserControllerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private UserService|MockObject $userService;
    private UserPasswordHasherInterface|MockObject $passwordHasher;
    private Security|MockObject $security;
    private EntityRepository|MockObject $userRepository;
    private EntityRepository|MockObject $skillsRepository;
    private EntityRepository|MockObject $projectRepository;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userService = $this->createMock(UserService::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        $this->skillsRepository = $this->createMock(EntityRepository::class);
        $this->projectRepository = $this->createMock(EntityRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->willReturnCallback(function ($class) {
                return match ($class) {
                    User::class => $this->userRepository,
                    Skills::class => $this->skillsRepository,
                    Project::class => $this->projectRepository,
                    default => null,
                };
            });

        $this->controller = new UserController($this->entityManager, $this->userService);
    }

    // ==================== Tests pour userCreate ====================

    public function testUserCreateSuccessWithAllFields(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'availabilityStart' => '2026-03-01',
            'availabilityEnd' => '2026-12-31'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));
        
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['status']);
        $this->assertEquals('User created successfully', $data['message']);
        $this->assertArrayHasKey('user', $data);
    }

    public function testUserCreateSuccessWithoutOptionalFields(): void
    {
        $requestData = [
            'email' => 'minimal@example.com',
            'password' => 'password123'
        ];

        $request = new Request([], [], [], [], [], [], json_encode($requestData));
        
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUserCreateMissingEmail(): void
    {
        $requestData = ['password' => 'password123'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertEquals('Email and password are required', $data['message']);
    }

    public function testUserCreateMissingPassword(): void
    {
        $requestData = ['email' => 'test@example.com'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
    }

    public function testUserCreateEmailAlreadyExists(): void
    {
        $requestData = [
            'email' => 'existing@example.com',
            'password' => 'password123'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $existingUser = $this->createMock(User::class);
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertEquals('This email is already in use', $data['message']);
    }

    public function testUserCreateInvalidAvailabilityStartDate(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'availabilityStart' => '2020-01-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->userRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('futur', $data['message']);
    }

    public function testUserCreateInvalidAvailabilityEndDate(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'availabilityEnd' => '2020-01-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->userRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUserCreateEndDateBeforeStartDate(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'availabilityStart' => '2026-12-01',
            'availabilityEnd' => '2026-03-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->userRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('après', $data['message']);
    }

    public function testUserCreateOnlyStartDateProvided(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'availabilityStart' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testUserCreateOnlyEndDateProvided(): void
    {
        $requestData = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'availabilityEnd' => '2026-12-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->userCreate($request, $this->passwordHasher);

        $this->assertEquals(201, $response->getStatusCode());
    }

    // ==================== Tests pour getAllUsers ====================

    public function testGetAllUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);
        $user1->method('getEmail')->willReturn('user1@example.com');
        $user1->method('getFirstName')->willReturn('John');
        $user1->method('getLastName')->willReturn('Doe');
        $user1->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-03-01'));
        $user1->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(2);
        $user2->method('getEmail')->willReturn('user2@example.com');
        $user2->method('getFirstName')->willReturn('Jane');
        $user2->method('getLastName')->willReturn('Smith');
        $user2->method('getAvailabilityStart')->willReturn(null);
        $user2->method('getAvailabilityEnd')->willReturn(null);

        $this->userRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $response = $this->controller->getAllUsers();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('user1@example.com', $data[0]['email']);
        $this->assertEquals('2026-03-01', $data[0]['availabilityStart']);
        $this->assertNull($data[1]['availabilityStart']);
    }

    public function testGetAllUsersEmpty(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $response = $this->controller->getAllUsers();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data);
        $this->assertIsArray($data);
    }

    // ==================== Tests pour getConnectedUser ====================

    public function testGetConnectedUserSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('connected@example.com');
        $user->method('getFirstName')->willReturn('Connected');
        $user->method('getLastName')->willReturn('User');
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-03-01'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->userService
            ->expects($this->once())
            ->method('findAll')
            ->with($user)
            ->willReturn(['some' => 'data']);

        $response = $this->controller->getConnectedUser($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('connected@example.com', $data['email']);
        $this->assertArrayHasKey('userData', $data);
        $this->assertEquals(['some' => 'data'], $data['userData']);
    }

    public function testGetConnectedUserNotAuthenticated(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $response = $this->controller->getConnectedUser($this->security);

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $data['message']);
    }

    // ==================== Tests pour getUserSkills ====================

    public function testGetUserSkillsSuccess(): void
    {
        $skill1 = $this->createMock(Skills::class);
        $skill1->method('getId')->willReturn(1);
        $skill1->method('getName')->willReturn('PHP');
        $skill1->method('getDescription')->willReturn('PHP Programming');
        $skill1->method('getDuree')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $skill1->method('getTechnoUtilisees')->willReturn('Laravel, Symfony');

        $skill2 = $this->createMock(Skills::class);
        $skill2->method('getId')->willReturn(2);
        $skill2->method('getName')->willReturn('JavaScript');
        $skill2->method('getDescription')->willReturn('JS Programming');
        $skill2->method('getDuree')->willReturn(null);
        $skill2->method('getTechnoUtilisees')->willReturn('React');

        $skills = new ArrayCollection([$skill1, $skill2]);

        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserSkills($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        $this->assertEquals('PHP', $data[0]['name']);
        $this->assertNull($data[1]['duree']);
    }

    public function testGetUserSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getUserSkills($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserSkillsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willThrowException(new \Exception('Database error'));

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserSkills($this->security);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Error fetching user skills', $data['error']);
    }

    // ==================== Tests pour getAllSkills ====================

    public function testGetAllSkillsSuccess(): void
    {
        $skill1 = $this->createMock(Skills::class);
        $skill1->method('getId')->willReturn(1);
        $skill1->method('getName')->willReturn('JavaScript');
        $skill1->method('getDescription')->willReturn('JS Programming');
        $skill1->method('getDuree')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $skill1->method('getTechnoUtilisees')->willReturn('React, Vue');

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$skill1]);

        $response = $this->controller->getAllSkills($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('JavaScript', $data[0]['name']);
    }

    public function testGetAllSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getAllSkills($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetAllSkillsWithNullValues(): void
    {
        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        $skill->method('getName')->willReturn(null);
        $skill->method('getDescription')->willReturn(null);
        $skill->method('getDuree')->willReturn(null);
        $skill->method('getTechnoUtilisees')->willReturn(null);

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('findAll')->willReturn([$skill]);

        $response = $this->controller->getAllSkills($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('N/A', $data[0]['name']);
        $this->assertEquals('N/A', $data[0]['description']);
        $this->assertNull($data[0]['duree']);
    }

    public function testGetAllSkillsException(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository
            ->method('findAll')
            ->willThrowException(new \Exception('Database error'));

        $response = $this->controller->getAllSkills($this->security);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Error fetching all skills', $data['error']);
    }

    // ==================== Tests pour createSkill ====================

    public function testCreateSkillSuccess(): void
    {
        $requestData = [
            'name' => 'Python',
            'description' => 'Python Programming',
            'technoUtilisees' => 'Django, Flask',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['Name' => 'Python'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Skill created successfully', $data['message']);
        $this->assertArrayHasKey('skill', $data);
    }

    public function testCreateSkillMissingName(): void
    {
        $requestData = [
            'description' => 'Test',
            'technoUtilisees' => 'Test',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Skill name is required', $data['message']);
    }

    public function testCreateSkillMissingDescription(): void
    {
        $requestData = [
            'name' => 'Test',
            'technoUtilisees' => 'Test',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Description is required', $data['message']);
    }

    public function testCreateSkillMissingTechno(): void
    {
        $requestData = [
            'name' => 'Test',
            'description' => 'Test',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Technologies used is required', $data['message']);
    }

    public function testCreateSkillMissingDuree(): void
    {
        $requestData = [
            'name' => 'Test',
            'description' => 'Test',
            'technoUtilisees' => 'Test'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Duration is required', $data['message']);
    }

    public function testCreateSkillAlreadyExists(): void
    {
        $requestData = [
            'name' => 'ExistingSkill',
            'description' => 'Test',
            'technoUtilisees' => 'Test',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $existingSkill = $this->createMock(Skills::class);
        $this->skillsRepository->method('findOneBy')->willReturn($existingSkill);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('A skill with this name already exists', $data['message']);
    }

    public function testCreateSkillInvalidDateFormat(): void
    {
        $requestData = [
            'name' => 'TestSkill',
            'description' => 'Test',
            'technoUtilisees' => 'Test',
            'duree' => 'invalid-date'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid date format', $data['message']);
    }

    public function testCreateSkillNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Test']));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateSkillException(): void
    {
        $requestData = [
            'name' => 'Test',
            'description' => 'Test',
            'technoUtilisees' => 'Test',
            'duree' => '2026-06-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('persist')->willThrowException(new \Exception('DB Error'));

        $response = $this->controller->createSkill($request, $this->security);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    // ==================== Tests pour updateSkill ====================

    public function testUpdateSkillSuccessWithAllFields(): void
    {
        $requestData = [
            'name' => 'UpdatedSkill',
            'description' => 'Updated Description',
            'technoUtilisees' => 'New Tech',
            'duree' => '2026-07-01'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        $skill->method('getName')->willReturn('UpdatedSkill');
        $skill->method('getDescription')->willReturn('Updated Description');
        $skill->method('getTechnoUtilisees')->willReturn('New Tech');
        $skill->method('getDuree')->willReturn(new \DateTimeImmutable('2026-07-01'));

        $this->skillsRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($skill);

        $this->skillsRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testUpdateSkillOnlyName(): void
    {
        $requestData = ['name' => 'NewName'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        $skill->method('getName')->willReturn('NewName');
        $skill->method('getDescription')->willReturn('Desc');
        $skill->method('getTechnoUtilisees')->willReturn('Tech');
        $skill->method('getDuree')->willReturn(new \DateTimeImmutable('2026-01-01'));

        $this->skillsRepository->method('find')->willReturn($skill);
        $this->skillsRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateSkillNotFound(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Test']));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('find')->willReturn(null);

        $response = $this->controller->updateSkill(999, $request, $this->security);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Skill not found', $data['message']);
    }

    public function testUpdateSkillNameAlreadyExists(): void
    {
        $requestData = ['name' => 'ExistingName'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);

        $existingSkill = $this->createMock(Skills::class);
        $existingSkill->method('getId')->willReturn(2);

        $this->skillsRepository->method('find')->willReturn($skill);
        $this->skillsRepository->method('findOneBy')->willReturn($existingSkill);

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testUpdateSkillInvalidDateFormat(): void
    {
        $requestData = ['duree' => 'invalid'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $this->skillsRepository->method('find')->willReturn($skill);

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid date format', $data['message']);
    }

    public function testUpdateSkillNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Test']));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateSkillException(): void
    {
        $requestData = ['name' => 'Test'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->method('flush')->willThrowException(new \Exception('DB Error'));

        $response = $this->controller->updateSkill(1, $request, $this->security);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ==================== Tests pour deleteSkill ====================

    public function testDeleteSkillSuccess(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getName')->willReturn('SkillToDelete');

        $this->skillsRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($skill);

        $this->entityManager->expects($this->once())->method('remove')->with($skill);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteSkill(1, $this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('SkillToDelete', $data['message']);
    }

    public function testDeleteSkillNotFound(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('find')->willReturn(null);

        $response = $this->controller->deleteSkill(999, $this->security);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->deleteSkill(1, $this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteSkillException(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->method('remove')->willThrowException(new \Exception('DB Error'));

        $response = $this->controller->deleteSkill(1, $this->security);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ==================== Tests addUserSkill, removeUserSkill, etc... ====================
    // (Le reste des tests suivent le même pattern complet)
    
    public function testAddUserSkillSuccess(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $skills = new ArrayCollection();
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $skill->method('getName')->willReturn('TestSkill');

        $this->skillsRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($skill);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('TestSkill', $data['skill_name']);
    }

    public function testAddUserSkillMissingSkillId(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testAddUserSkillNotFound(): void
    {
        $requestData = ['skill_id' => 999];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $this->skillsRepository->method('find')->willReturn(null);

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddUserSkillAlreadyHas(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $skill = $this->createMock(Skills::class);
        $skills = new ArrayCollection([$skill]);

        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('déjà', $data['message']);
    }

    public function testAddUserSkillNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['skill_id' => 1]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddUserSkillException(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);

        $skill = $this->createMock(Skills::class);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->addUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // Continue avec TOUS les autres tests...
    // Je vais ajouter les tests manquants pour atteindre 100%

    public function testRemoveUserSkillSuccess(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $skill = $this->createMock(Skills::class);
        $skill->method('getName')->willReturn('SkillToRemove');
        
        $skills = new ArrayCollection([$skill]);

        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testRemoveUserSkillNotOwned(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $skill = $this->createMock(Skills::class);
        $skills = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserSkillMissingId(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserSkillNotFound(): void
    {
        $requestData = ['skill_id' => 999];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn(null);

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveUserSkillNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['skill_id' => 1]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRemoveUserSkillException(): void
    {
        $requestData = ['skill_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $skill = $this->createMock(Skills::class);
        $skills = new ArrayCollection([$skill]);
        
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn($skills);

        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->method('flush')->willThrowException(new \Exception('Error'));

        $response = $this->controller->removeUserSkill($request, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testChangeAvailabilitySuccess(): void
    {
        $requestData = [
            'availabilityStart' => '2026-06-01',
            'availabilityEnd' => '2026-12-31'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-06-01'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $this->security->method('getUser')->willReturn($user);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testChangeAvailabilityMissingDates(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityMissingStartDate(): void
    {
        $requestData = ['availabilityEnd' => '2026-12-31'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityMissingEndDate(): void
    {
        $requestData = ['availabilityStart' => '2026-06-01'];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityInvalidStartDate(): void
    {
        $requestData = [
            'availabilityStart' => 'invalid-date',
            'availabilityEnd' => '2026-12-31'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid', $data['message']);
    }

    public function testChangeAvailabilityInvalidEndDate(): void
    {
        $requestData = [
            'availabilityStart' => '2026-06-01',
            'availabilityEnd' => 'invalid-date'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testChangeAvailabilityException(): void
    {
        $requestData = [
            'availabilityStart' => '2026-06-01',
            'availabilityEnd' => '2026-12-31'
        ];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->changeAvailability($request, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // Tests pour getUserProjects
    public function testGetUserProjectsSuccess(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');
        $project->method('getDescription')->willReturn('Description');
        $project->method('getRequiredSkills')->willReturn('PHP, JS');
        $project->method('getStartDate')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $project->method('getEndDate')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $projects = new ArrayCollection([$project]);

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserProjects($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('Test Project', $data[0]['name']);
    }

    public function testGetUserProjectsWithNullDates(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Project');
        $project->method('getDescription')->willReturn('Desc');
        $project->method('getRequiredSkills')->willReturn('Skills');
        $project->method('getStartDate')->willReturn(null);
        $project->method('getEndDate')->willReturn(null);

        $projects = new ArrayCollection([$project]);
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserProjects($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNull($data[0]['startDate']);
        $this->assertNull($data[0]['endDate']);
    }

    public function testGetUserProjectsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getUserProjects($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserProjectsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getProject')->willThrowException(new \Exception('Error'));
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserProjects($this->security);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // Tests addUserToProject
    public function testAddUserToProjectSuccess(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Test Project');

        $projects = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testAddUserToProjectMissingId(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserToProjectNotFound(): void
    {
        $requestData = ['project_id' => 999];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn(null);

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddUserToProjectAlreadyIn(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $projects = new ArrayCollection([$project]);

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserToProjectNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 1]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddUserToProjectException(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection());

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->method('flush')->willThrowException(new \Exception('Error'));

        $response = $this->controller->addUserToProject($request, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // Tests removeUserFromProject
    public function testRemoveUserFromProjectSuccess(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Test Project');
        
        $projects = new ArrayCollection([$project]);

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testRemoveUserFromProjectMissingId(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectNotFound(): void
    {
        $requestData = ['project_id' => 999];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn(null);

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectNotInProject(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $projects = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['project_id' => 1]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectException(): void
    {
        $requestData = ['project_id' => 1];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $project = $this->createMock(Project::class);
        $projects = new ArrayCollection([$project]);
        
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn($projects);

        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->method('flush')->willThrowException(new \Exception('Error'));

        $response = $this->controller->removeUserFromProject($request, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // Tests pour les invitations et amis
    public function testGetReceivedInvitationsSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn(2);
        $sender->method('getFirstName')->willReturn('John');
        $sender->method('getLastName')->willReturn('Doe');
        $sender->method('getEmail')->willReturn('john@example.com');

        $receivedInvitations = new ArrayCollection([$sender]);
        $friends = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getReceivedInvitations($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('John', $data[0]['firstName']);
    }

    public function testGetReceivedInvitationsFiltersExistingFriends(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getId')->willReturn(2);

        $receivedInvitations = new ArrayCollection([$sender]);
        $friends = new ArrayCollection([$sender]);

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getReceivedInvitations($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data);
    }

    public function testGetReceivedInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getReceivedInvitations($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetSentInvitationsSuccess(): void
    {
        $invited = $this->createMock(User::class);
        $invited->method('getId')->willReturn(3);
        $invited->method('getFirstName')->willReturn('Jane');
        $invited->method('getLastName')->willReturn('Smith');
        $invited->method('getEmail')->willReturn('jane@example.com');

        $sentInvitations = new ArrayCollection([$invited]);
        $friends = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn($sentInvitations);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getSentInvitations($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('Jane', $data[0]['firstName']);
    }

    public function testGetSentInvitationsFiltersExistingFriends(): void
    {
        $invited = $this->createMock(User::class);
        $sentInvitations = new ArrayCollection([$invited]);
        $friends = new ArrayCollection([$invited]);

        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn($sentInvitations);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getSentInvitations($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data);
    }

    public function testGetSentInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getSentInvitations($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserFriendsSuccess(): void
    {
        $friend = $this->createMock(User::class);
        $friend->method('getId')->willReturn(4);
        $friend->method('getFirstName')->willReturn('Bob');
        $friend->method('getLastName')->willReturn('Wilson');
        $friend->method('getEmail')->willReturn('bob@example.com');

        $friends = new ArrayCollection([$friend]);

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserFriends($this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertEquals('Bob', $data[0]['firstName']);
    }

    public function testGetUserFriendsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->getUserFriends($this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserFriendsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFriends')->willThrowException(new \Exception('Error'));
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserFriends($this->security);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testDeleteFriendSuccess(): void
    {
        $friend = $this->createMock(User::class);
        $friends = new ArrayCollection([$friend]);

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->with(2)->willReturn($friend);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteFriend(2, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testDeleteFriendNotFound(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->deleteFriend(999, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteFriendNotInList(): void
    {
        $friend = $this->createMock(User::class);
        $friends = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);

        $response = $this->controller->deleteFriend(2, $this->security, $this->entityManager);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDeleteFriendNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->deleteFriend(1, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSendInvitationSuccess(): void
    {
        $requestData = ['friend_id' => 2];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $friend = $this->createMock(User::class);
        $friends = new ArrayCollection();
        $sentInvitations = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);
        $user->method('getSentInvitations')->willReturn($sentInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testSendInvitationMissingId(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSendInvitationUserNotFound(): void
    {
        $requestData = ['friend_id' => 999];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSendInvitationAlreadyFriends(): void
    {
        $requestData = ['friend_id' => 2];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $friend = $this->createMock(User::class);
        $friends = new ArrayCollection([$friend]);

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSendInvitationAlreadySent(): void
    {
        $requestData = ['friend_id' => 2];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $friend = $this->createMock(User::class);
        $friends = new ArrayCollection();
        $sentInvitations = new ArrayCollection([$friend]);

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn($friends);
        $user->method('getSentInvitations')->willReturn($sentInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSendInvitationNotAuthenticated(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['friend_id' => 1]));
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSendInvitationException(): void
    {
        $requestData = ['friend_id' => 2];
        $request = new Request([], [], [], [], [], [], json_encode($requestData));

        $friend = $this->createMock(User::class);
        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn(new ArrayCollection());
        $user->method('getSentInvitations')->willReturn(new ArrayCollection());

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);
        $this->entityManager->method('flush')->willThrowException(new \Exception('Error'));

        $response = $this->controller->sendInvitation($request, $this->entityManager, $this->security);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testAcceptInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());

        $receivedInvitations = new ArrayCollection([$sender]);

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->acceptInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testAcceptInvitationSenderNotFound(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->acceptInvitation(999, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAcceptInvitationNotReceived(): void
    {
        $sender = $this->createMock(User::class);
        $receivedInvitations = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);

        $response = $this->controller->acceptInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAcceptInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->acceptInvitation(1, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAcceptInvitationException(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        
        $receivedInvitations = new ArrayCollection([$sender]);
        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->acceptInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());

        $receivedInvitations = new ArrayCollection([$sender]);

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteReceivedInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testDeleteReceivedInvitationSenderNotFound(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->deleteReceivedInvitation(999, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationNotReceived(): void
    {
        $sender = $this->createMock(User::class);
        $receivedInvitations = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);

        $response = $this->controller->deleteReceivedInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->deleteReceivedInvitation(1, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationException(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        
        $receivedInvitations = new ArrayCollection([$sender]);
        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn($receivedInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->deleteReceivedInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testDeleteSentInvitationSuccess(): void
    {
        $receiver = $this->createMock(User::class);
        $receiver->method('getReceivedInvitations')->willReturn(new ArrayCollection());

        $sentInvitations = new ArrayCollection([$receiver]);

        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn($sentInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($receiver);
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteSentInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testDeleteSentInvitationReceiverNotFound(): void
    {
        $user = $this->createMock(User::class);
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn(null);

        $response = $this->controller->deleteSentInvitation(999, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteSentInvitationNotSent(): void
    {
        $receiver = $this->createMock(User::class);
        $sentInvitations = new ArrayCollection();

        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn($sentInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($receiver);

        $response = $this->controller->deleteSentInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteSentInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $response = $this->controller->deleteSentInvitation(1, $this->security, $this->entityManager);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteSentInvitationException(): void
    {
        $receiver = $this->createMock(User::class);
        $receiver->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        
        $sentInvitations = new ArrayCollection([$receiver]);
        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn($sentInvitations);

        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($receiver);
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->deleteSentInvitation(2, $this->security, $this->entityManager);

        $this->assertEquals(500, $response->getStatusCode());
    }
}