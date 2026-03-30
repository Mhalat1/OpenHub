<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use App\Service\UserService;
use App\Service\PapertrailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserService&MockObject $userService;
    private PapertrailService&MockObject $papertrailService;
    private Security&MockObject $security;
    private EntityRepository&MockObject $userRepository;
    private EntityRepository&MockObject $skillsRepository;
    private EntityRepository&MockObject $projectRepository;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        $this->userService     = $this->createMock(UserService::class);
        $this->papertrailService = $this->createMock(PapertrailService::class);
        $this->security        = $this->createMock(Security::class);
        $this->userRepository  = $this->createMock(EntityRepository::class);
        $this->skillsRepository = $this->createMock(EntityRepository::class);
        $this->projectRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')->willReturnCallback(
            fn($class) => match ($class) {
                User::class    => $this->userRepository,
                Skills::class  => $this->skillsRepository,
                Project::class => $this->projectRepository,
                default        => null,
            }
        );

        $this->controller = new UserController(
            $this->entityManager,
            $this->userService,
            $this->papertrailService
        );
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function mockSkill(int $id = 1, string $name = 'PHP'): Skills&MockObject
    {
        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn($id);
        $skill->method('getName')->willReturn($name);
        $skill->method('getDescription')->willReturn('Description');
        $skill->method('getTechnoUtilisees')->willReturn('Symfony');
        $skill->method('getDuree')->willReturn(new \DateTimeImmutable('2026-01-01'));
        return $skill;
    }

    private function mockUser(int $id = 1): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn("user{$id}@example.com");
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);
        return $user;
    }

    private function jsonRequest(array $data): Request
    {
        return new Request([], [], [], [], [], [], json_encode($data));
    }

    // ── getAllUsers ───────────────────────────────────────────────

    public function testGetAllUsersReturns200(): void
    {
        $this->userRepository->method('findAll')->willReturn([
            $this->mockUser(1),
            $this->mockUser(2),
        ]);

        $response = $this->controller->getAllUsers();
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
    }

    public function testGetAllUsersEmpty(): void
    {
        $this->userRepository->method('findAll')->willReturn([]);
        $response = $this->controller->getAllUsers();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(0, json_decode($response->getContent(), true));
    }

    // ── getConnectedUser ──────────────────────────────────────────

    public function testGetConnectedUserSuccess(): void
    {
        $user = $this->mockUser(1);
        $user->method('getEmail')->willReturn('connected@example.com');
        $user->method('getFirstName')->willReturn('Connected');
        $user->method('getLastName')->willReturn('User');
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-03-01'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $this->security->method('getUser')->willReturn($user);
        $this->userService->method('findAll')->with($user)->willReturn(['some' => 'data']);

        $response = $this->controller->getConnectedUser($this->security);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['id']);
        $this->assertArrayHasKey('userData', $data);
    }

    public function testGetConnectedUserNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->getConnectedUser($this->security);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── getUserSkills ─────────────────────────────────────────────

    public function testGetUserSkillsSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection([
            $this->mockSkill(1, 'PHP'),
            $this->mockSkill(2, 'JavaScript'),
        ]));
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserSkills($this->security);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
    }

    public function testGetUserSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getUserSkills($this->security)->getStatusCode());
    }

    public function testGetUserSkillsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willThrowException(new \Exception('DB error'));
        $this->security->method('getUser')->willReturn($user);
        $this->assertEquals(500, $this->controller->getUserSkills($this->security)->getStatusCode());
    }

    // ── getAllSkills ──────────────────────────────────────────────

    public function testGetAllSkillsSuccess(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findAll')->willReturn([$this->mockSkill()]);

        $response = $this->controller->getAllSkills($this->security);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, json_decode($response->getContent(), true));
    }

    public function testGetAllSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getAllSkills($this->security)->getStatusCode());
    }

    public function testGetAllSkillsWithNullValues(): void
    {
        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        $skill->method('getName')->willReturn(null);
        $skill->method('getDescription')->willReturn(null);
        $skill->method('getDuree')->willReturn(null);
        $skill->method('getTechnoUtilisees')->willReturn(null);

        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findAll')->willReturn([$skill]);

        $response = $this->controller->getAllSkills($this->security);
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('N/A', $data[0]['name']);
        $this->assertNull($data[0]['duree']);
    }

    public function testGetAllSkillsException(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findAll')->willThrowException(new \Exception('DB error'));
        $this->assertEquals(500, $this->controller->getAllSkills($this->security)->getStatusCode());
    }

    public function testCreateSkillMissingName(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->createSkill($this->jsonRequest([
            'description' => 'Test', 'technoUtilisees' => 'Test', 'duree' => '2026-06-01',
        ]), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSkillMissingDescription(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'Test', 'technoUtilisees' => 'Test', 'duree' => '2026-06-01',
        ]), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSkillMissingTechno(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'Test', 'description' => 'Test', 'duree' => '2026-06-01',
        ]), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSkillMissingDuree(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'Test', 'description' => 'Test', 'technoUtilisees' => 'Test',
        ]), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSkillAlreadyExists(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findOneBy')->willReturn($this->mockSkill());

        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'PHP', 'description' => 'Test', 'technoUtilisees' => 'Test', 'duree' => '2026-06-01',
        ]), $this->security);
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testCreateSkillInvalidDateFormat(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'Test', 'description' => 'Test', 'technoUtilisees' => 'Test', 'duree' => 'invalid',
        ]), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->createSkill($this->jsonRequest(['name' => 'Test']), $this->security);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateSkillException(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('persist')->willThrowException(new \Exception('DB Error'));

        $response = $this->controller->createSkill($this->jsonRequest([
            'name' => 'Test', 'description' => 'Test', 'technoUtilisees' => 'Test', 'duree' => '2026-06-01',
        ]), $this->security);
        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── updateSkill ───────────────────────────────────────────────

    public function testUpdateSkillSuccess(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->skillsRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->updateSkill(1, $this->jsonRequest([
            'name' => 'UpdatedSkill', 'description' => 'Desc', 'technoUtilisees' => 'Tech', 'duree' => '2026-07-01',
        ]), $this->security);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn(null);

        $response = $this->controller->updateSkill(999, $this->jsonRequest(['name' => 'Test']), $this->security);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateSkillNameAlreadyExists(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill(1));
        $this->skillsRepository->method('findOneBy')->willReturn($this->mockSkill(2));

        $response = $this->controller->updateSkill(1, $this->jsonRequest(['name' => 'Existing']), $this->security);
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testUpdateSkillInvalidDate(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());

        $response = $this->controller->updateSkill(1, $this->jsonRequest(['duree' => 'invalid']), $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUpdateSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->updateSkill(1, $this->jsonRequest(['name' => 'Test']), $this->security);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateSkillException(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->skillsRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('flush')->willThrowException(new \Exception('DB Error'));

        $response = $this->controller->updateSkill(1, $this->jsonRequest(['name' => 'Test']), $this->security);
        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── deleteSkill ───────────────────────────────────────────────

    public function testDeleteSkillSuccess(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->entityManager->expects($this->once())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteSkill(1, $this->security);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn(null);
        $this->assertEquals(404, $this->controller->deleteSkill(999, $this->security)->getStatusCode());
    }

    public function testDeleteSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->deleteSkill(1, $this->security)->getStatusCode());
    }

    public function testDeleteSkillException(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->entityManager->method('remove')->willThrowException(new \Exception('DB Error'));
        $this->assertEquals(500, $this->controller->deleteSkill(1, $this->security)->getStatusCode());
    }

    // ── addUserSkill ──────────────────────────────────────────────

    public function testAddUserSkillSuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->addUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testAddUserSkillMissingId(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->addUserSkill($this->jsonRequest([]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn(null);
        $response = $this->controller->addUserSkill($this->jsonRequest(['skill_id' => 999]), $this->security, $this->entityManager);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddUserSkillAlreadyHas(): void
    {
        $skill = $this->mockSkill();
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection([$skill]));
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);

        $response = $this->controller->addUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->addUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddUserSkillException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));

        $response = $this->controller->addUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── removeUserSkill ───────────────────────────────────────────

    public function testRemoveUserSkillSuccess(): void
    {
        $skill = $this->mockSkill();
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection([$skill]));
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->removeUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRemoveUserSkillNotOwned(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($this->mockSkill());

        $response = $this->controller->removeUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserSkillMissingId(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->removeUserSkill($this->jsonRequest([]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->skillsRepository->method('find')->willReturn(null);
        $response = $this->controller->removeUserSkill($this->jsonRequest(['skill_id' => 999]), $this->security, $this->entityManager);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveUserSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->removeUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRemoveUserSkillException(): void
    {
        $skill = $this->mockSkill();
        $user = $this->createMock(User::class);
        $user->method('getSkills')->willReturn(new ArrayCollection([$skill]));
        $this->security->method('getUser')->willReturn($user);
        $this->skillsRepository->method('find')->willReturn($skill);
        $this->entityManager->method('flush')->willThrowException(new \Exception('Error'));

        $response = $this->controller->removeUserSkill($this->jsonRequest(['skill_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── changeAvailability ────────────────────────────────────────

    public function testChangeAvailabilitySuccess(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-06-01'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));
        $this->security->method('getUser')->willReturn($user);
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->changeAvailability($this->jsonRequest([
            'availabilityStart' => '2026-06-01', 'availabilityEnd' => '2026-12-31',
        ]), $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testChangeAvailabilityMissingDates(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->changeAvailability($this->jsonRequest([]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityMissingStart(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->changeAvailability($this->jsonRequest(['availabilityEnd' => '2026-12-31']), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityMissingEnd(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->changeAvailability($this->jsonRequest(['availabilityStart' => '2026-06-01']), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityInvalidStart(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->changeAvailability($this->jsonRequest([
            'availabilityStart' => 'invalid', 'availabilityEnd' => '2026-12-31',
        ]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityInvalidEnd(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->changeAvailability($this->jsonRequest([
            'availabilityStart' => '2026-06-01', 'availabilityEnd' => 'invalid',
        ]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->changeAvailability($this->jsonRequest([]), $this->security, $this->entityManager);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testChangeAvailabilityException(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->entityManager->method('persist')->willThrowException(new \Exception('Error'));
        $response = $this->controller->changeAvailability($this->jsonRequest([
            'availabilityStart' => '2026-06-01', 'availabilityEnd' => '2026-12-31',
        ]), $this->security, $this->entityManager);
        $this->assertEquals(500, $response->getStatusCode());
    }

    // ── getUserProjects ───────────────────────────────────────────

    public function testGetUserProjectsSuccess(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');
        $project->method('getDescription')->willReturn('Desc');
        $project->method('getRequiredSkills')->willReturn('PHP');
        $project->method('getStartDate')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $project->method('getEndDate')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection([$project]));
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserProjects($this->security);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, json_decode($response->getContent(), true));
    }

    public function testGetUserProjectsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getUserProjects($this->security)->getStatusCode());
    }

    public function testGetUserProjectsException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getProject')->willThrowException(new \Exception('Error'));
        $this->security->method('getUser')->willReturn($user);
        $this->assertEquals(500, $this->controller->getUserProjects($this->security)->getStatusCode());
    }

    // ── addUserToProject ──────────────────────────────────────────

    public function testAddUserToProjectSuccess(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Test');
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->addUserToProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAddUserToProjectMissingId(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->addUserToProject($this->jsonRequest([]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserToProjectNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->projectRepository->method('find')->willReturn(null);
        $response = $this->controller->addUserToProject($this->jsonRequest(['project_id' => 999]), $this->security, $this->entityManager);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddUserToProjectAlreadyIn(): void
    {
        $project = $this->createMock(Project::class);
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection([$project]));
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);

        $response = $this->controller->addUserToProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserToProjectNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->addUserToProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── removeUserFromProject ─────────────────────────────────────

    public function testRemoveUserFromProjectSuccess(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getName')->willReturn('Test');
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection([$project]));
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->removeUserFromProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectNotInProject(): void
    {
        $project = $this->createMock(Project::class);
        $user = $this->createMock(User::class);
        $user->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->projectRepository->method('find')->willReturn($project);

        $response = $this->controller->removeUserFromProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRemoveUserFromProjectNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->removeUserFromProject($this->jsonRequest(['project_id' => 1]), $this->security, $this->entityManager);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── Invitations & Friends ─────────────────────────────────────

    public function testGetReceivedInvitationsSuccess(): void
    {
        $sender = $this->mockUser(2);
        $sender->method('getFirstName')->willReturn('John');
        $sender->method('getLastName')->willReturn('Doe');
        $sender->method('getEmail')->willReturn('john@example.com');

        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $user->method('getFriends')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getReceivedInvitations($this->security);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, json_decode($response->getContent(), true));
    }

    public function testGetReceivedInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getReceivedInvitations($this->security)->getStatusCode());
    }

    public function testGetSentInvitationsSuccess(): void
    {
        $invited = $this->mockUser(3);
        $invited->method('getFirstName')->willReturn('Jane');
        $invited->method('getLastName')->willReturn('Smith');
        $invited->method('getEmail')->willReturn('jane@example.com');

        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn(new ArrayCollection([$invited]));
        $user->method('getFriends')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getSentInvitations($this->security);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetSentInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getSentInvitations($this->security)->getStatusCode());
    }

    public function testGetUserFriendsSuccess(): void
    {
        $friend = $this->mockUser(4);
        $friend->method('getFirstName')->willReturn('Bob');
        $friend->method('getLastName')->willReturn('Wilson');
        $friend->method('getEmail')->willReturn('bob@example.com');

        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn(new ArrayCollection([$friend]));
        $this->security->method('getUser')->willReturn($user);

        $response = $this->controller->getUserFriends($this->security);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetUserFriendsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->getUserFriends($this->security)->getStatusCode());
    }

    public function testDeleteFriendSuccess(): void
    {
        $friend = $this->mockUser(2);
        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn(new ArrayCollection([$friend]));
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->deleteFriend(2, $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteFriendNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->userRepository->method('find')->willReturn(null);
        $this->assertEquals(404, $this->controller->deleteFriend(999, $this->security, $this->entityManager)->getStatusCode());
    }

    public function testDeleteFriendNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->deleteFriend(1, $this->security, $this->entityManager)->getStatusCode());
    }

    public function testSendInvitationSuccess(): void
    {
        $friend = $this->mockUser(2);
        $user = $this->createMock(User::class);
        $user->method('getFriends')->willReturn(new ArrayCollection());
        $user->method('getSentInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($friend);
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->controller->sendInvitation($this->jsonRequest(['friend_id' => 2]), $this->entityManager, $this->security);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testSendInvitationMissingId(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $response = $this->controller->sendInvitation($this->jsonRequest([]), $this->entityManager, $this->security);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSendInvitationNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->userRepository->method('find')->willReturn(null);
        $response = $this->controller->sendInvitation($this->jsonRequest(['friend_id' => 999]), $this->entityManager, $this->security);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSendInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $response = $this->controller->sendInvitation($this->jsonRequest(['friend_id' => 1]), $this->entityManager, $this->security);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAcceptInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $response = $this->controller->acceptInvitation(2, $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAcceptInvitationNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->mockUser());
        $this->userRepository->method('find')->willReturn(null);
        $this->assertEquals(404, $this->controller->acceptInvitation(999, $this->security, $this->entityManager)->getStatusCode());
    }

    public function testAcceptInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->acceptInvitation(1, $this->security, $this->entityManager)->getStatusCode());
    }

    public function testDeleteReceivedInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $user = $this->createMock(User::class);
        $user->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($sender);

        $response = $this->controller->deleteReceivedInvitation(2, $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->deleteReceivedInvitation(1, $this->security, $this->entityManager)->getStatusCode());
    }

    public function testDeleteSentInvitationSuccess(): void
    {
        $receiver = $this->createMock(User::class);
        $receiver->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        $user = $this->createMock(User::class);
        $user->method('getSentInvitations')->willReturn(new ArrayCollection([$receiver]));
        $this->security->method('getUser')->willReturn($user);
        $this->userRepository->method('find')->willReturn($receiver);

        $response = $this->controller->deleteSentInvitation(2, $this->security, $this->entityManager);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteSentInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertEquals(401, $this->controller->deleteSentInvitation(1, $this->security, $this->entityManager)->getStatusCode());
    }
}