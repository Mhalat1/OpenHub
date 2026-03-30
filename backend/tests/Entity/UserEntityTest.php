<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use App\Entity\Conversation;
use App\Entity\Message;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserEntityTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        // Initialiser les propriétés requises pour éviter les erreurs d'accès
        $this->user->setEmail('test@example.com');
        $this->user->setPassword('password');
        $this->user->setFirstName('Test');
        $this->user->setLastName('User');
    }

    public function testEntityCanBeInstantiated(): void
    {
        $user = new User();
        $this->assertInstanceOf(User::class, $user);
    }

    public function testConstructorInitializesCollections(): void
    {
        $user = new User();
        $this->assertInstanceOf(Collection::class, $user->getSkills());
        $this->assertInstanceOf(Collection::class, $user->getProject());
        $this->assertInstanceOf(Collection::class, $user->getSentInvitations());
        $this->assertInstanceOf(Collection::class, $user->getReceivedInvitations());
        $this->assertInstanceOf(Collection::class, $user->getFriends());
        $this->assertInstanceOf(Collection::class, $user->getConversations());
        $this->assertInstanceOf(Collection::class, $user->getMessages());
        
        $this->assertCount(0, $user->getSkills());
        $this->assertCount(0, $user->getProject());
        $this->assertCount(0, $user->getSentInvitations());
        $this->assertCount(0, $user->getReceivedInvitations());
        $this->assertCount(0, $user->getFriends());
        $this->assertCount(0, $user->getConversations());
        $this->assertCount(0, $user->getMessages());
    }

    public function testGetSetEmail(): void
    {
        $email = 'newemail@example.com';
        
        $result = $this->user->setEmail($email);
        
        $this->assertSame($this->user, $result);
        $this->assertEquals($email, $this->user->getEmail());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $this->assertEquals('test@example.com', $this->user->getUserIdentifier());
    }

    public function testGetSetRoles(): void
    {
        $user = new User();
        $user->setEmail('roles@example.com');
        $user->setPassword('password');
        $user->setFirstName('Roles');
        $user->setLastName('Test');
        
        $roles = ['ROLE_ADMIN'];
        
        $result = $user->setRoles($roles);
        
        $this->assertSame($user, $result);
        
        $expectedRoles = ['ROLE_ADMIN', 'ROLE_USER'];
        $this->assertEquals($expectedRoles, $user->getRoles());
    }

    public function testGetRolesAlwaysContainsUserRole(): void
    {
        $user = new User();
        $user->setEmail('roles2@example.com');
        $user->setPassword('password');
        $user->setFirstName('Roles');
        $user->setLastName('Test');
        $user->setRoles([]);
        
        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(1, $roles);
    }

    public function testGetSetPassword(): void
    {
        $user = new User();
        $user->setEmail('password@example.com');
        $user->setFirstName('Password');
        $user->setLastName('Test');
        
        $password = 'hashed_password_123';
        
        $result = $user->setPassword($password);
        
        $this->assertSame($user, $result);
        $this->assertEquals($password, $user->getPassword());
    }

    public function testEraseCredentials(): void
    {
        // Cette méthode ne fait rien pour l'instant
        $this->user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testGetSetFirstName(): void
    {
        $user = new User();
        $user->setEmail('first@example.com');
        $user->setPassword('password');
        
        $firstName = 'Jean';
        
        $result = $user->setFirstName($firstName);
        
        $this->assertSame($user, $result);
        $this->assertEquals($firstName, $user->getFirstName());
    }

    public function testGetSetLastName(): void
    {
        $user = new User();
        $user->setEmail('last@example.com');
        $user->setPassword('password');
        $user->setFirstName('Jean');
        
        $lastName = 'Dupont';
        
        $result = $user->setLastName($lastName);
        
        $this->assertSame($user, $result);
        $this->assertEquals($lastName, $user->getLastName());
    }

    public function testGetSetAvailabilityStart(): void
    {
        $date = new DateTimeImmutable('2024-01-01');
        
        $result = $this->user->setAvailabilityStart($date);
        
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getAvailabilityStart());
    }

    public function testGetSetAvailabilityEnd(): void
    {
        $date = new DateTimeImmutable('2024-12-31');
        
        $result = $this->user->setAvailabilityEnd($date);
        
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getAvailabilityEnd());
    }

    public function testAddGetSkills(): void
    {
        $skill1 = $this->createMock(Skills::class);
        $skill2 = $this->createMock(Skills::class);
        
        $result = $this->user->addSkill($skill1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getSkills());
        $this->assertTrue($this->user->getSkills()->contains($skill1));
        
        $this->user->addSkill($skill2);
        $this->assertCount(2, $this->user->getSkills());
        
        $this->user->addSkill($skill1);
        $this->assertCount(2, $this->user->getSkills());
    }

    public function testRemoveSkill(): void
    {
        $skill = $this->createMock(Skills::class);
        
        $this->user->addSkill($skill);
        $this->assertCount(1, $this->user->getSkills());
        
        $result = $this->user->removeSkill($skill);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getSkills());
    }

    public function testAddGetProjects(): void
    {
        $project1 = $this->createMock(Project::class);
        $project2 = $this->createMock(Project::class);
        
        $result = $this->user->addProject($project1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getProject());
        $this->assertTrue($this->user->getProject()->contains($project1));
        
        $this->user->addProject($project2);
        $this->assertCount(2, $this->user->getProject());
        
        $this->user->addProject($project1);
        $this->assertCount(2, $this->user->getProject());
    }

    public function testRemoveUserProject(): void
    {
        $project = $this->createMock(Project::class);
        
        $this->user->addProject($project);
        $this->assertCount(1, $this->user->getProject());
        
        $result = $this->user->removeUserProject($project);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getProject());
    }

    public function testAddGetSentInvitations(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);
        
        $result = $this->user->addSentInvitation($user1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getSentInvitations());
        $this->assertTrue($this->user->getSentInvitations()->contains($user1));
        
        $this->user->addSentInvitation($user2);
        $this->assertCount(2, $this->user->getSentInvitations());
        
        $this->user->addSentInvitation($user1);
        $this->assertCount(2, $this->user->getSentInvitations());
    }

    public function testRemoveSentInvitation(): void
    {
        $invitedUser = $this->createMock(User::class);
        
        $this->user->addSentInvitation($invitedUser);
        $this->assertCount(1, $this->user->getSentInvitations());
        
        $result = $this->user->removeSentInvitation($invitedUser);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getSentInvitations());
    }

    public function testGetReceivedInvitations(): void
    {
        $this->assertInstanceOf(Collection::class, $this->user->getReceivedInvitations());
        $this->assertCount(0, $this->user->getReceivedInvitations());
    }

    public function testAddGetFriends(): void
    {
        $friend1 = $this->createMock(User::class);
        $friend2 = $this->createMock(User::class);
        
        $result = $this->user->addFriend($friend1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getFriends());
        $this->assertTrue($this->user->getFriends()->contains($friend1));
        
        $this->user->addFriend($friend2);
        $this->assertCount(2, $this->user->getFriends());
        
        $this->user->addFriend($friend1);
        $this->assertCount(2, $this->user->getFriends());
    }

    public function testRemoveFriend(): void
    {
        $friend = $this->createMock(User::class);
        
        $this->user->addFriend($friend);
        $this->assertCount(1, $this->user->getFriends());
        
        $result = $this->user->removeFriend($friend);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getFriends());
    }

    public function testAddGetConversations(): void
    {
        $conversation1 = $this->createMock(Conversation::class);
        $conversation2 = $this->createMock(Conversation::class);
        
        $conversation1->expects($this->once())
            ->method('addUser')
            ->with($this->user);
        $conversation2->expects($this->once())
            ->method('addUser')
            ->with($this->user);
        
        $result = $this->user->addConversation($conversation1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getConversations());
        $this->assertTrue($this->user->getConversations()->contains($conversation1));
        
        $this->user->addConversation($conversation2);
        $this->assertCount(2, $this->user->getConversations());
        
        $this->user->addConversation($conversation1);
        $this->assertCount(2, $this->user->getConversations());
    }

    public function testRemoveConversation(): void
    {
        $conversation = $this->createMock(Conversation::class);
        
        $conversation->expects($this->once())
            ->method('addUser')
            ->with($this->user);
        $conversation->expects($this->once())
            ->method('removeUser')
            ->with($this->user);
        
        $this->user->addConversation($conversation);
        $this->assertCount(1, $this->user->getConversations());
        
        $result = $this->user->removeConversation($conversation);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getConversations());
    }

    public function testAddGetMessages(): void
    {
        $message1 = $this->createMock(Message::class);
        $message2 = $this->createMock(Message::class);
        
        $message1->expects($this->once())
            ->method('setAuthor')
            ->with($this->user);
        $message2->expects($this->once())
            ->method('setAuthor')
            ->with($this->user);
        
        $result = $this->user->addMessage($message1);
        
        $this->assertSame($this->user, $result);
        $this->assertCount(1, $this->user->getMessages());
        $this->assertTrue($this->user->getMessages()->contains($message1));
        
        $this->user->addMessage($message2);
        $this->assertCount(2, $this->user->getMessages());
        
        $this->user->addMessage($message1);
        $this->assertCount(2, $this->user->getMessages());
    }

    public function testFullUserCreation(): void
    {
        $user = new User();
        $user->setEmail('jean.dupont@example.com')
            ->setPassword('hashed_password')
            ->setFirstName('Jean')
            ->setLastName('Dupont');
        
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $skill = $this->createMock(Skills::class);
        $project = $this->createMock(Project::class);
        $friend = $this->createMock(User::class);
        $conversation = $this->createMock(Conversation::class);
        $message = $this->createMock(Message::class);
        
        $conversation->method('addUser')->with($user);
        $message->method('setAuthor')->with($user);
        
        $user->setAvailabilityStart($startDate)
            ->setAvailabilityEnd($endDate)
            ->addSkill($skill)
            ->addProject($project)
            ->addFriend($friend)
            ->addConversation($conversation)
            ->addMessage($message);
        
        $this->assertEquals('jean.dupont@example.com', $user->getEmail());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertEquals('Jean', $user->getFirstName());
        $this->assertEquals('Dupont', $user->getLastName());
        $this->assertSame($startDate, $user->getAvailabilityStart());
        $this->assertSame($endDate, $user->getAvailabilityEnd());
        
        $this->assertCount(1, $user->getSkills());
        $this->assertCount(1, $user->getProject());
        $this->assertCount(1, $user->getFriends());
        $this->assertCount(1, $user->getConversations());
        $this->assertCount(1, $user->getMessages());
    }

    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass(User::class);
        
        $classAttributes = $reflection->getAttributes();
        $hasEntityAttribute = false;
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Entity') {
                $hasEntityAttribute = true;
                break;
            }
        }
        $this->assertTrue($hasEntityAttribute, 'Class should have Entity attribute');
    }

    public function testImplementedInterfaces(): void
    {
        $this->assertInstanceOf(UserInterface::class, $this->user);
        $this->assertInstanceOf(PasswordAuthenticatedUserInterface::class, $this->user);
    }

    public function testGetProjectsAfterAddProject(): void
    {
        $user = new User();
        $user->setEmail('project@example.com');
        $user->setPassword('password');
        $user->setFirstName('Project');
        $user->setLastName('Test');
        
        $project = $this->createMock(Project::class);
        
        $user->addProject($project);
        
        $projects = $user->getProjects();
        
        $this->assertCount(1, $projects);
        $this->assertTrue($projects->contains($project));
    }

    public function testRemoveProjectRemovesExistingProject(): void
    {
        $user = new User();
        $user->setEmail('remove@example.com');
        $user->setPassword('password');
        $user->setFirstName('Remove');
        $user->setLastName('Test');
        
        $project = $this->createMock(Project::class);
        
        $user->addProject($project);
        $this->assertCount(1, $user->getProjects());
        
        $result = $user->removeProject($project);
        
        $this->assertCount(0, $user->getProjects());
        $this->assertSame($user, $result);
    }

    public function testRemoveProjectWithNonExistentProject(): void
    {
        $user = new User();
        $user->setEmail('nonexistent@example.com');
        $user->setPassword('password');
        $user->setFirstName('Nonexistent');
        $user->setLastName('Test');
        
        $project1 = $this->createMock(Project::class);
        $project2 = $this->createMock(Project::class);
        
        $user->addProject($project1);
        $this->assertCount(1, $user->getProjects());
        
        $result = $user->removeProject($project2);
        
        $this->assertCount(1, $user->getProjects());
        $this->assertTrue($user->getProjects()->contains($project1));
        $this->assertSame($user, $result);
    }
}