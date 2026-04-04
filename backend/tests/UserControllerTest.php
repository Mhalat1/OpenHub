<?php

namespace App\Tests\Controller;

use App\Controller\UserController;
use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use App\Service\UserService;
use App\Service\AxiomService;
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
    private EntityManagerInterface&MockObject $em;
    private UserService&MockObject $userService;
    private Security&MockObject $security;
    private EntityRepository&MockObject $userRepo;
    private EntityRepository&MockObject $skillsRepo;
    private EntityRepository&MockObject $projectRepo;
    private UserController $ctrl;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->userService  = $this->createMock(UserService::class);
        $this->security     = $this->createMock(Security::class);
        $this->userRepo     = $this->createMock(EntityRepository::class);
        $this->skillsRepo   = $this->createMock(EntityRepository::class);
        $this->projectRepo  = $this->createMock(EntityRepository::class);

        $this->em->method('getRepository')->willReturnCallback(fn($c) => match ($c) {
            User::class    => $this->userRepo,
            Skills::class  => $this->skillsRepo,
            Project::class => $this->projectRepo,
            default        => null,
        });

        $this->ctrl = new UserController(
            $this->em,
            $this->userService,
            $this->createMock(AxiomService::class)
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function skill(int $id = 1, string $name = 'PHP'): Skills&MockObject
    {
        $s = $this->createMock(Skills::class);
        $s->method('getId')->willReturn($id);
        $s->method('getName')->willReturn($name);
        $s->method('getDescription')->willReturn('Desc');
        $s->method('getTechnoUtilisees')->willReturn('Symfony');
        $s->method('getDuree')->willReturn(new \DateTimeImmutable('2026-01-01'));
        return $s;
    }

    private function user(int $id = 1): User&MockObject
    {
        $u = $this->createMock(User::class);
        $u->method('getId')->willReturn($id);
        $u->method('getEmail')->willReturn("user{$id}@example.com");
        $u->method('getFirstName')->willReturn('Jean');
        $u->method('getLastName')->willReturn('Dupont');
        $u->method('getAvailabilityStart')->willReturn(null);
        $u->method('getAvailabilityEnd')->willReturn(null);
        return $u;
    }

    private function req(array $data): Request
    {
        return new Request([], [], [], [], [], [], json_encode($data));
    }

    private function authed(?User $u = null): void
    {
        $this->security->method('getUser')->willReturn($u ?? $this->user());
    }

    private function assertStatus(int $code, $response): void
    {
        $this->assertEquals($code, $response->getStatusCode());
    }

    public function testUserCreateMissingEmail(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->assertStatus(400, $this->ctrl->userCreate($this->req(['password' => 'secret']), $hasher));
    }

    public function testUserCreateMissingPassword(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->assertStatus(400, $this->ctrl->userCreate($this->req(['email' => 'a@b.com']), $hasher));
    }

    public function testUserCreateEmailAlreadyExists(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userRepo->method('findOneBy')->willReturn($this->user());
        $this->assertStatus(409, $this->ctrl->userCreate($this->req([
            'email' => 'existing@example.com', 'password' => 'secret',
        ]), $hasher));
    }

    public function testUserCreateAvailabilityStartInPast(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->assertStatus(400, $this->ctrl->userCreate($this->req([
            'email' => 'a@b.com', 'password' => 'x',
            'availabilityStart' => '2020-01-01',
        ]), $hasher));
    }

    public function testUserCreateAvailabilityEndInPast(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->assertStatus(400, $this->ctrl->userCreate($this->req([
            'email' => 'a@b.com', 'password' => 'x',
            'availabilityEnd' => '2020-01-01',
        ]), $hasher));
    }

    public function testUserCreateEndBeforeStart(): void
    {
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->assertStatus(400, $this->ctrl->userCreate($this->req([
            'email' => 'a@b.com', 'password' => 'x',
            'availabilityStart' => '2030-12-01',
            'availabilityEnd'   => '2027-01-01',
        ]), $hasher));
    }

    public function testGetAllUsers(): void
    {
        $this->userRepo->method('findAll')->willReturn([$this->user(1), $this->user(2)]);
        $r = $this->ctrl->getAllUsers();
        $this->assertStatus(200, $r);
        $this->assertCount(2, json_decode($r->getContent(), true));
    }

    // ── getConnectedUser ──────────────────────────────────────────

    public function testGetConnectedUserSuccess(): void
    {
        $u = $this->user();
        $u->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-03-01'));
        $u->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));
        $this->security->method('getUser')->willReturn($u);
        $this->userService->method('findAll')->willReturn(['some' => 'data']);

        $r = $this->ctrl->getConnectedUser($this->security);
        $this->assertStatus(200, $r);
        $this->assertArrayHasKey('userData', json_decode($r->getContent(), true));
    }

    public function testGetConnectedUserNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getConnectedUser($this->security));
    }

    public function testGetConnectedUserNonUserEntity(): void
    {
        $proxy = $this->getMockBuilder(\Symfony\Component\Security\Core\User\UserInterface::class)->getMock();
        $proxy->method('getUserIdentifier')->willReturn('proxy@example.com');
        $this->security->method('getUser')->willReturn($proxy);
        $this->userRepo->method('findOneBy')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->getConnectedUser($this->security));
    }

    // ── getUserSkills ─────────────────────────────────────────────

    public function testGetUserSkillsSuccess(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection([$this->skill(1), $this->skill(2, 'JS')]));
        $this->security->method('getUser')->willReturn($u);
        $r = $this->ctrl->getUserSkills($this->security);
        $this->assertStatus(200, $r);
        $this->assertCount(2, json_decode($r->getContent(), true));
    }

    public function testGetUserSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getUserSkills($this->security));
    }

    public function testGetUserSkillsException(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willThrowException(new \Exception('DB error'));
        $this->security->method('getUser')->willReturn($u);
        $this->assertStatus(500, $this->ctrl->getUserSkills($this->security));
    }

    // ── getAllSkills ──────────────────────────────────────────────

    public function testGetAllSkillsSuccess(): void
    {
        $this->authed();
        $this->skillsRepo->method('findAll')->willReturn([$this->skill()]);
        $r = $this->ctrl->getAllSkills($this->security);
        $this->assertStatus(200, $r);
        $this->assertCount(1, json_decode($r->getContent(), true));
    }

    public function testGetAllSkillsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getAllSkills($this->security));
    }

    public function testGetAllSkillsNullValues(): void
    {
        $this->authed();
        $s = $this->createMock(Skills::class);
        $s->method('getId')->willReturn(1);
        $s->method('getName')->willReturn(null);
        $s->method('getDescription')->willReturn(null);
        $s->method('getDuree')->willReturn(null);
        $s->method('getTechnoUtilisees')->willReturn(null);
        $this->skillsRepo->method('findAll')->willReturn([$s]);
        $data = json_decode($this->ctrl->getAllSkills($this->security)->getContent(), true);
        $this->assertEquals('N/A', $data[0]['name']);
        $this->assertNull($data[0]['duree']);
    }

    public function testGetAllSkillsException(): void
    {
        $this->authed();
        $this->skillsRepo->method('findAll')->willThrowException(new \Exception('DB error'));
        $this->assertStatus(500, $this->ctrl->getAllSkills($this->security));
    }

    public function testCreateSkillAlreadyExists(): void
    {
        $this->authed();
        $this->skillsRepo->method('findOneBy')->willReturn($this->skill());
        $this->assertStatus(409, $this->ctrl->createSkill($this->req([
            'name' => 'PHP', 'description' => 'D', 'technoUtilisees' => 'X', 'duree' => '2026-06-01',
        ]), $this->security));
    }

    public function testCreateSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->createSkill($this->req(['name' => 'T']), $this->security));
    }

    public function testCreateSkillException(): void
    {
        $this->authed();
        $this->skillsRepo->method('findOneBy')->willReturn(null);
        $this->em->method('persist')->willThrowException(new \Exception('DB Error'));
        $this->assertStatus(500, $this->ctrl->createSkill($this->req([
            'name' => 'T', 'description' => 'D', 'technoUtilisees' => 'X', 'duree' => '2026-06-01',
        ]), $this->security));
    }

    // ── updateSkill ───────────────────────────────────────────────

    public function testUpdateSkillSuccess(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->skillsRepo->method('findOneBy')->willReturn(null);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->updateSkill(1, $this->req([
            'name' => 'Updated', 'description' => 'D', 'technoUtilisees' => 'X', 'duree' => '2026-07-01',
        ]), $this->security));
    }

    public function testUpdateSkillNotFound(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->updateSkill(999, $this->req(['name' => 'T']), $this->security));
    }

    public function testUpdateSkillNameConflict(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill(1));
        $this->skillsRepo->method('findOneBy')->willReturn($this->skill(2));
        $this->assertStatus(409, $this->ctrl->updateSkill(1, $this->req(['name' => 'Existing']), $this->security));
    }

    public function testUpdateSkillInvalidDate(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->assertStatus(400, $this->ctrl->updateSkill(1, $this->req(['duree' => 'invalid']), $this->security));
    }

    public function testUpdateSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->updateSkill(1, $this->req(['name' => 'T']), $this->security));
    }

    public function testUpdateSkillException(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->skillsRepo->method('findOneBy')->willReturn(null);
        $this->em->method('flush')->willThrowException(new \Exception('DB Error'));
        $this->assertStatus(500, $this->ctrl->updateSkill(1, $this->req(['name' => 'T']), $this->security));
    }

    // ── deleteSkill ───────────────────────────────────────────────

    public function testDeleteSkillSuccess(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->em->expects($this->once())->method('remove');
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->deleteSkill(1, $this->security));
    }

    public function testDeleteSkillNotFound(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->deleteSkill(999, $this->security));
    }

    public function testDeleteSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->deleteSkill(1, $this->security));
    }

    public function testDeleteSkillException(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->em->method('remove')->willThrowException(new \Exception('DB Error'));
        $this->assertStatus(500, $this->ctrl->deleteSkill(1, $this->security));
    }

    // ── addUserSkill ──────────────────────────────────────────────

    public function testAddUserSkillSuccess(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(201, $this->ctrl->addUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserSkillMissingId(): void
    {
        $this->authed();
        $this->assertStatus(400, $this->ctrl->addUserSkill($this->req([]), $this->security, $this->em));
    }

    public function testAddUserSkillNotFound(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->addUserSkill($this->req(['skill_id' => 999]), $this->security, $this->em));
    }

    public function testAddUserSkillAlreadyOwned(): void
    {
        $s = $this->skill();
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection([$s]));
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($s);
        $this->assertStatus(400, $this->ctrl->addUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->addUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserSkillException(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->em->method('persist')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->addUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    // ── removeUserSkill ───────────────────────────────────────────

    public function testRemoveUserSkillSuccess(): void
    {
        $s = $this->skill();
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection([$s]));
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($s);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->removeUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserSkillNotOwned(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($this->skill());
        $this->assertStatus(400, $this->ctrl->removeUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserSkillMissingId(): void
    {
        $this->authed();
        $this->assertStatus(400, $this->ctrl->removeUserSkill($this->req([]), $this->security, $this->em));
    }

    public function testRemoveUserSkillNotFound(): void
    {
        $this->authed();
        $this->skillsRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->removeUserSkill($this->req(['skill_id' => 999]), $this->security, $this->em));
    }

    public function testRemoveUserSkillNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->removeUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserSkillException(): void
    {
        $s = $this->skill();
        $u = $this->createMock(User::class);
        $u->method('getSkills')->willReturn(new ArrayCollection([$s]));
        $this->security->method('getUser')->willReturn($u);
        $this->skillsRepo->method('find')->willReturn($s);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->removeUserSkill($this->req(['skill_id' => 1]), $this->security, $this->em));
    }

    // ── changeAvailability ────────────────────────────────────────

    public function testChangeAvailabilitySuccess(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-06-01'));
        $u->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-12-31'));
        $this->security->method('getUser')->willReturn($u);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->changeAvailability($this->req([
            'availabilityStart' => '2026-06-01', 'availabilityEnd' => '2026-12-31',
        ]), $this->security, $this->em));
    }

    public function testChangeAvailabilityNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->changeAvailability($this->req([]), $this->security, $this->em));
    }

    public function testChangeAvailabilityException(): void
    {
        $this->authed();
        $this->em->method('persist')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->changeAvailability($this->req([
            'availabilityStart' => '2026-06-01', 'availabilityEnd' => '2026-12-31',
        ]), $this->security, $this->em));
    }

    // ── getUserProjects ───────────────────────────────────────────

    public function testGetUserProjectsSuccess(): void
    {
        $p = $this->createMock(Project::class);
        $p->method('getId')->willReturn(1);
        $p->method('getName')->willReturn('Test Project');
        $p->method('getDescription')->willReturn('Desc');
        $p->method('getRequiredSkills')->willReturn('PHP');
        $p->method('getStartDate')->willReturn(new \DateTimeImmutable('2026-01-01'));
        $p->method('getEndDate')->willReturn(new \DateTimeImmutable('2026-12-31'));

        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection([$p]));
        $this->security->method('getUser')->willReturn($u);
        $r = $this->ctrl->getUserProjects($this->security);
        $this->assertStatus(200, $r);
        $this->assertCount(1, json_decode($r->getContent(), true));
    }

    public function testGetUserProjectsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getUserProjects($this->security));
    }

    public function testGetUserProjectsException(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getProject')->willThrowException(new \Exception('Error'));
        $this->security->method('getUser')->willReturn($u);
        $this->assertStatus(500, $this->ctrl->getUserProjects($this->security));
    }

    // ── addUserToProject ──────────────────────────────────────────

    public function testAddUserToProjectSuccess(): void
    {
        $p = $this->createMock(Project::class);
        $p->method('getName')->willReturn('Test');
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->addUserToProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserToProjectMissingId(): void
    {
        $this->authed();
        $this->assertStatus(400, $this->ctrl->addUserToProject($this->req([]), $this->security, $this->em));
    }

    public function testAddUserToProjectNotFound(): void
    {
        $this->authed();
        $this->projectRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->addUserToProject($this->req(['project_id' => 999]), $this->security, $this->em));
    }

    public function testAddUserToProjectAlreadyIn(): void
    {
        $p = $this->createMock(Project::class);
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection([$p]));
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->assertStatus(400, $this->ctrl->addUserToProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserToProjectNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->addUserToProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testAddUserToProjectException(): void
    {
        $p = $this->createMock(Project::class);
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->addUserToProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    // ── removeUserFromProject ─────────────────────────────────────

    public function testRemoveUserFromProjectSuccess(): void
    {
        $p = $this->createMock(Project::class);
        $p->method('getName')->willReturn('Test');
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection([$p]));
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->removeUserFromProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserFromProjectNotInProject(): void
    {
        $p = $this->createMock(Project::class);
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->assertStatus(400, $this->ctrl->removeUserFromProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserFromProjectMissingId(): void
    {
        $this->authed();
        $this->assertStatus(400, $this->ctrl->removeUserFromProject($this->req([]), $this->security, $this->em));
    }

    public function testRemoveUserFromProjectNotFound(): void
    {
        $this->authed();
        $this->projectRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->removeUserFromProject($this->req(['project_id' => 999]), $this->security, $this->em));
    }

    public function testRemoveUserFromProjectNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->removeUserFromProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    public function testRemoveUserFromProjectException(): void
    {
        $p = $this->createMock(Project::class);
        $p->method('getName')->willReturn('Test');
        $u = $this->createMock(User::class);
        $u->method('getProject')->willReturn(new ArrayCollection([$p]));
        $this->security->method('getUser')->willReturn($u);
        $this->projectRepo->method('find')->willReturn($p);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->removeUserFromProject($this->req(['project_id' => 1]), $this->security, $this->em));
    }

    // ── Invitations ───────────────────────────────────────────────

    public function testGetReceivedInvitationsSuccess(): void
    {
        $sender = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $r = $this->ctrl->getReceivedInvitations($this->security);
        $this->assertStatus(200, $r);
        $this->assertCount(1, json_decode($r->getContent(), true));
    }

    public function testGetReceivedInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getReceivedInvitations($this->security));
    }

    public function testGetSentInvitationsSuccess(): void
    {
        $invited = $this->user(3);
        $u = $this->createMock(User::class);
        $u->method('getSentInvitations')->willReturn(new ArrayCollection([$invited]));
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->assertStatus(200, $this->ctrl->getSentInvitations($this->security));
    }

    public function testGetSentInvitationsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getSentInvitations($this->security));
    }

    // ── getUserFriends ────────────────────────────────────────────

    public function testGetUserFriendsSuccess(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection([$this->user(4)]));
        $this->security->method('getUser')->willReturn($u);
        $this->assertStatus(200, $this->ctrl->getUserFriends($this->security));
    }

    public function testGetUserFriendsNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->getUserFriends($this->security));
    }

    public function testGetUserFriendsException(): void
    {
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willThrowException(new \Exception('Error'));
        $this->security->method('getUser')->willReturn($u);
        $this->assertStatus(500, $this->ctrl->getUserFriends($this->security));
    }

    // ── deleteFriend ──────────────────────────────────────────────

    public function testDeleteFriendSuccess(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection([$friend]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->deleteFriend(2, $this->security, $this->em));
    }

    public function testDeleteFriendNotFound(): void
    {
        $this->authed();
        $this->userRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->deleteFriend(999, $this->security, $this->em));
    }

    public function testDeleteFriendNotInList(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->assertStatus(400, $this->ctrl->deleteFriend(2, $this->security, $this->em));
    }

    public function testDeleteFriendNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->deleteFriend(1, $this->security, $this->em));
    }

    public function testSendInvitationSuccess(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $u->method('getSentInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->sendInvitation($this->req(['friend_id' => 2]), $this->em, $this->security));
    }

    public function testSendInvitationMissingId(): void
    {
        $this->authed();
        $this->assertStatus(400, $this->ctrl->sendInvitation($this->req([]), $this->em, $this->security));
    }

    public function testSendInvitationNotFound(): void
    {
        $this->authed();
        $this->userRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->sendInvitation($this->req(['friend_id' => 999]), $this->em, $this->security));
    }

    public function testSendInvitationAlreadyFriends(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection([$friend]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->assertStatus(400, $this->ctrl->sendInvitation($this->req(['friend_id' => 2]), $this->em, $this->security));
    }

    public function testSendInvitationAlreadySent(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $u->method('getSentInvitations')->willReturn(new ArrayCollection([$friend]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->assertStatus(400, $this->ctrl->sendInvitation($this->req(['friend_id' => 2]), $this->em, $this->security));
    }

    public function testSendInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->sendInvitation($this->req(['friend_id' => 1]), $this->em, $this->security));
    }

    public function testSendInvitationException(): void
    {
        $friend = $this->user(2);
        $u = $this->createMock(User::class);
        $u->method('getFriends')->willReturn(new ArrayCollection());
        $u->method('getSentInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($friend);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->sendInvitation($this->req(['friend_id' => 2]), $this->em, $this->security));
    }

    // ── acceptInvitation ──────────────────────────────────────────

    public function testAcceptInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->em->expects($this->exactly(2))->method('persist');
        $this->assertStatus(200, $this->ctrl->acceptInvitation(2, $this->security, $this->em));
    }

    public function testAcceptInvitationSenderNotFound(): void
    {
        $this->authed();
        $this->userRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->acceptInvitation(999, $this->security, $this->em));
    }

    public function testAcceptInvitationNoInvitation(): void
    {
        $sender = $this->createMock(User::class);
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->assertStatus(404, $this->ctrl->acceptInvitation(2, $this->security, $this->em));
    }

    public function testAcceptInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->acceptInvitation(1, $this->security, $this->em));
    }

    public function testAcceptInvitationException(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->em->method('persist')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->acceptInvitation(2, $this->security, $this->em));
    }

    // ── deleteReceivedInvitation ──────────────────────────────────

    public function testDeleteReceivedInvitationSuccess(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->deleteReceivedInvitation(2, $this->security, $this->em));
    }

    public function testDeleteReceivedInvitationSenderNotFound(): void
    {
        $this->authed();
        $this->userRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->deleteReceivedInvitation(999, $this->security, $this->em));
    }

    public function testDeleteReceivedInvitationNoInvitation(): void
    {
        $sender = $this->createMock(User::class);
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->assertStatus(404, $this->ctrl->deleteReceivedInvitation(2, $this->security, $this->em));
    }

    public function testDeleteReceivedInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->deleteReceivedInvitation(1, $this->security, $this->em));
    }

    public function testDeleteReceivedInvitationException(): void
    {
        $sender = $this->createMock(User::class);
        $sender->method('getSentInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getReceivedInvitations')->willReturn(new ArrayCollection([$sender]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($sender);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->deleteReceivedInvitation(2, $this->security, $this->em));
    }

    // ── deleteSentInvitation ──────────────────────────────────────

    public function testDeleteSentInvitationSuccess(): void
    {
        $receiver = $this->createMock(User::class);
        $receiver->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getSentInvitations')->willReturn(new ArrayCollection([$receiver]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($receiver);
        $this->em->expects($this->once())->method('flush');
        $this->assertStatus(200, $this->ctrl->deleteSentInvitation(2, $this->security, $this->em));
    }

    public function testDeleteSentInvitationReceiverNotFound(): void
    {
        $this->authed();
        $this->userRepo->method('find')->willReturn(null);
        $this->assertStatus(404, $this->ctrl->deleteSentInvitation(999, $this->security, $this->em));
    }

    public function testDeleteSentInvitationNoInvitation(): void
    {
        $receiver = $this->createMock(User::class);
        $u = $this->createMock(User::class);
        $u->method('getSentInvitations')->willReturn(new ArrayCollection());
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($receiver);
        $this->assertStatus(404, $this->ctrl->deleteSentInvitation(2, $this->security, $this->em));
    }

    public function testDeleteSentInvitationNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $this->assertStatus(401, $this->ctrl->deleteSentInvitation(1, $this->security, $this->em));
    }

    public function testDeleteSentInvitationException(): void
    {
        $receiver = $this->createMock(User::class);
        $receiver->method('getReceivedInvitations')->willReturn(new ArrayCollection());
        $u = $this->createMock(User::class);
        $u->method('getSentInvitations')->willReturn(new ArrayCollection([$receiver]));
        $this->security->method('getUser')->willReturn($u);
        $this->userRepo->method('find')->willReturn($receiver);
        $this->em->method('flush')->willThrowException(new \Exception('Error'));
        $this->assertStatus(500, $this->ctrl->deleteSentInvitation(2, $this->security, $this->em));
    }
}