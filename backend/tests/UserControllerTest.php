<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;

class UserControllerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private UserService&MockObject $userService;
    private User&MockObject $user;
    private EntityRepository&MockObject $userRepository;
    private UserController $controller;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userService = $this->createMock(UserService::class);
        $this->user = $this->createMock(User::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        
        $this->em->method('getRepository')->willReturn($this->userRepository);
        
        $this->controller = new UserController($this->em, $this->userService);
    }

    // ========================================
    // TESTS: userCreate() - POST /api/userCreate
    // ========================================

    public function testUserCreateReturns400WhenEmailMissing(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['password' => 'test123']));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Email and password are required', $data['message']);
    }

    public function testUserCreateReturns400WhenPasswordMissing(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode(['email' => 'test@example.com']));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUserCreateReturns409WhenEmailExists(): void
    {
        $this->userRepository->method('findOneBy')->willReturn($this->user);
        
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'existing@example.com',
            'password' => 'password123'
        ]));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('This email is already in use', $data['message']);
    }

    public function testUserCreateReturns400WhenAvailabilityStartInPast(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'new@example.com',
            'password' => 'password123',
            'availabilityStart' => '2020-01-01'
        ]));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUserCreateReturns400WhenAvailabilityEndBeforeStart(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'new@example.com',
            'password' => 'password123',
            'availabilityStart' => '2026-12-01',
            'availabilityEnd' => '2026-06-01'
        ]));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUserCreateSuccessfully(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed_password');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'new@example.com',
            'password' => 'password123',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]));
        
        $response = $this->controller->userCreate($request, $this->passwordHasher);
        
        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['status']);
    }

    // ========================================
    // TESTS: getAllUsers() - GET /api/getAllUsers
    // ========================================

    public function testGetAllUsersReturnsAllUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);
        $user1->method('getEmail')->willReturn('user1@example.com');
        $user1->method('getFirstName')->willReturn('John');
        $user1->method('getLastName')->willReturn('Doe');
        
        $this->userRepository->method('findAll')->willReturn([$user1]);
        
        $response = $this->controller->getAllUsers();
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
    }

    // ========================================
    // TESTS: getConnectedUser() - GET /api/getConnectedUser
    // ========================================

    public function testGetConnectedUserReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getConnectedUser($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetConnectedUserReturnsUserData(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);
        
        $this->security->method('getUser')->willReturn($this->user);
        $this->userService->method('findAll')->willReturn([]);
        
        $response = $this->controller->getConnectedUser($this->security);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================
    // TESTS: getUserSkills() - GET /api/user/skills
    // ========================================

    public function testGetUserSkillsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getUserSkills($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserSkillsReturnsUserSkills(): void
    {
        $skill = $this->createMock(Skills::class);
        $skill->method('getId')->willReturn(1);
        $skill->method('getName')->willReturn('PHP');
        $skill->method('getDescription')->willReturn('PHP Development');
        $skill->method('getTechnoUtilisees')->willReturn('Symfony');
        
        $this->user->method('getSkills')->willReturn(new ArrayCollection([$skill]));
        $this->security->method('getUser')->willReturn($this->user);
        
        $response = $this->controller->getUserSkills($this->security);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
    }

    // ========================================
    // TESTS: getAllSkills() - GET /api/skills
    // ========================================

    public function testGetAllSkillsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getAllSkills($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ========================================
    // TESTS: createSkill() - POST /api/skills/create
    // ========================================

    public function testCreateSkillReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->createSkill($request, $this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateSkillReturns400WhenNameMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->createSkill($request, $this->security);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Skill name is required', $data['message']);
    }

    public function testCreateSkillReturns409WhenSkillAlreadyExists(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $existingSkill = $this->createMock(Skills::class);
        $skillRepo->method('findOneBy')->willReturn($existingSkill);
        
        // Créer un nouveau mock d'EntityManager pour ce test
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function($class) use ($skillRepo) {
            if ($class === Skills::class) {
                return $skillRepo;
            }
            return $this->userRepository;
        });
        
        $controller = new UserController($em, $this->userService);
        
        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'PHP',
            'description' => 'Test',
            'technoUtilisees' => 'Symfony',
            'duree' => '2026-01-01'
        ]));
        
        $response = $controller->createSkill($request, $this->security);
        
        $this->assertEquals(409, $response->getStatusCode());
    }

    // ========================================
    // TESTS: updateSkill() - PUT /api/skills/update/{id}
    // ========================================

    public function testUpdateSkillReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->updateSkill(1, $request, $this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUpdateSkillReturns404WhenSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $skillRepo->method('find')->willReturn(null);
        
        $this->em->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Skills::class, $skillRepo]
        ]);
        
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Updated']));
        
        $response = $this->controller->updateSkill(999, $request, $this->security);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    // ========================================
    // TESTS: deleteSkill() - DELETE /api/skills/delete/{id}
    // ========================================

    public function testDeleteSkillReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->deleteSkill(1, $this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteSkillReturns404WhenSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $skillRepo->method('find')->willReturn(null);
        
        $this->em->method('getRepository')->willReturnMap([
            [User::class, $this->userRepository],
            [Skills::class, $skillRepo]
        ]);
        
        $response = $this->controller->deleteSkill(999, $this->security);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    // ========================================
    // TESTS: addUserSkill() - POST /api/user/add/skills
    // ========================================

    public function testAddUserSkillReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->addUserSkill($request, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddUserSkillReturns400WhenSkillIdMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->addUserSkill($request, $this->security, $this->em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddUserSkillReturns404WhenSkillNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $skillRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($skillRepo);
        
        $request = new Request([], [], [], [], [], [], json_encode(['skill_id' => 999]));
        
        $response = $this->controller->addUserSkill($request, $this->security, $this->em);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddUserSkillReturns400WhenSkillAlreadyAdded(): void
    {
        $skill = $this->createMock(Skills::class);
        $skills = $this->createMock(ArrayCollection::class);
        $skills->method('contains')->willReturn(true);
        
        $this->user->method('getSkills')->willReturn($skills);
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $skillRepo->method('find')->willReturn($skill);
        
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($skillRepo);
        
        $controller = new UserController($em, $this->userService);
        
        $request = new Request([], [], [], [], [], [], json_encode(['skill_id' => 1]));
        
        $response = $controller->addUserSkill($request, $this->security, $em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: removeUserSkill() - DELETE /api/user/delete/skill
    // ========================================

    public function testRemoveUserSkillReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->removeUserSkill($request, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testRemoveUserSkillReturns400WhenUserDoesntHaveSkill(): void
    {
        $skill = $this->createMock(Skills::class);
        $skills = $this->createMock(ArrayCollection::class);
        $skills->method('contains')->willReturn(false);
        
        $this->user->method('getSkills')->willReturn($skills);
        $this->security->method('getUser')->willReturn($this->user);
        
        $skillRepo = $this->createMock(EntityRepository::class);
        $skillRepo->method('find')->willReturn($skill);
        
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($skillRepo);
        
        $controller = new UserController($em, $this->userService);
        
        $request = new Request([], [], [], [], [], [], json_encode(['skill_id' => 1]));
        
        $response = $controller->removeUserSkill($request, $this->security, $em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: changeAvailability() - POST /api/user/availability
    // ========================================

    public function testChangeAvailabilityReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->changeAvailability($request, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testChangeAvailabilityReturns400WhenDataMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->changeAvailability($request, $this->security, $this->em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testChangeAvailabilityReturns400WhenInvalidDateFormat(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([
            'availabilityStart' => 'invalid-date',
            'availabilityEnd' => '2026-12-31'
        ]));
        
        $response = $this->controller->changeAvailability($request, $this->security, $this->em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: getUserProjects() - GET /api/user/projects
    // ========================================

    public function testGetUserProjectsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getUserProjects($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserProjectsReturnsProjects(): void
    {
        $project = $this->createMock(Project::class);
        $project->method('getId')->willReturn(1);
        $project->method('getName')->willReturn('Test Project');
        $project->method('getDescription')->willReturn('Description');
        $project->method('getRequiredSkills')->willReturn('PHP');
        
        $this->user->method('getProject')->willReturn(new ArrayCollection([$project]));
        $this->security->method('getUser')->willReturn($this->user);
        
        $response = $this->controller->getUserProjects($this->security);
        
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ========================================
    // TESTS: addUserToProject() - POST /api/user/add/project
    // ========================================

    public function testAddUserToProjectReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->addUserToProject($request, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testAddUserToProjectReturns400WhenProjectIdMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->addUserToProject($request, $this->security, $this->em);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: removeUserFromProject() - DELETE /api/user/delete/project
    // ========================================

    public function testRemoveUserFromProjectReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->removeUserFromProject($request, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ========================================
    // TESTS: Friend Management
    // ========================================

    public function testGetReceivedInvitationsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getReceivedInvitations($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetSentInvitationsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getSentInvitations($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserFriendsReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->getUserFriends($this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteFriendReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->deleteFriend(1, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteFriendReturns404WhenFriendNotFound(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $this->em->method('getRepository')->willReturn($this->userRepository);
        $this->userRepository->method('find')->willReturn(null);
        
        $response = $this->controller->deleteFriend(999, $this->security, $this->em);
        
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSendInvitationReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->sendInvitation($request, $this->em, $this->security);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSendInvitationReturns400WhenFriendIdMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->user);
        $request = new Request([], [], [], [], [], [], json_encode([]));
        
        $response = $this->controller->sendInvitation($request, $this->em, $this->security);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAcceptInvitationReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->acceptInvitation(1, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteReceivedInvitationReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->deleteReceivedInvitation(1, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteSentInvitationReturns401WhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        
        $response = $this->controller->deleteSentInvitation(1, $this->security, $this->em);
        
        $this->assertEquals(401, $response->getStatusCode());
    }
}