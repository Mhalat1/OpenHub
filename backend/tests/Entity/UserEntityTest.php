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
    }

    public function testEntityCanBeInstantiated(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
    }

    public function testConstructorInitializesCollections(): void
    {
        $this->assertInstanceOf(Collection::class, $this->user->getSkills());
        $this->assertInstanceOf(Collection::class, $this->user->getProject());
        $this->assertInstanceOf(Collection::class, $this->user->getSentInvitations());
        $this->assertInstanceOf(Collection::class, $this->user->getReceivedInvitations());
        $this->assertInstanceOf(Collection::class, $this->user->getFriends());
        $this->assertInstanceOf(Collection::class, $this->user->getConversations());
        $this->assertInstanceOf(Collection::class, $this->user->getMessages());
        
        $this->assertCount(0, $this->user->getSkills());
        $this->assertCount(0, $this->user->getProject());
        $this->assertCount(0, $this->user->getSentInvitations());
        $this->assertCount(0, $this->user->getReceivedInvitations());
        $this->assertCount(0, $this->user->getFriends());
        $this->assertCount(0, $this->user->getConversations());
        $this->assertCount(0, $this->user->getMessages());
    }

    public function testIdIsInitiallyNull(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testGetSetEmail(): void
    {
        $email = 'test@example.com';
        
        $this->assertNull($this->user->getEmail());
        
        $result = $this->user->setEmail($email);
        
        $this->assertSame($this->user, $result);
        $this->assertEquals($email, $this->user->getEmail());
    }

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $email = 'test@example.com';
        $this->user->setEmail($email);
        
        $this->assertEquals($email, $this->user->getUserIdentifier());
    }

    public function testGetSetRoles(): void
    {
        $roles = ['ROLE_ADMIN'];
        
        $result = $this->user->setRoles($roles);
        
        $this->assertSame($this->user, $result);
        
        $expectedRoles = ['ROLE_ADMIN', 'ROLE_USER'];
        $this->assertEquals($expectedRoles, $this->user->getRoles());
    }

    public function testGetRolesAlwaysContainsUserRole(): void
    {
        $this->user->setRoles([]);
        
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(1, $roles);
    }

    public function testGetSetPassword(): void
    {
        $password = 'hashed_password_123';
        
        $this->assertNull($this->user->getPassword());
        
        $result = $this->user->setPassword($password);
        
        $this->assertSame($this->user, $result);
        $this->assertEquals($password, $this->user->getPassword());
    }

    public function testEraseCredentials(): void
    {
        // Cette méthode ne fait rien pour l'instant
        $this->user->eraseCredentials();
        $this->assertTrue(true); // Juste pour s'assurer qu'il n'y a pas d'erreur
    }

    public function testGetSetFirstName(): void
    {
        $firstName = 'Jean';
        
        $this->assertNull($this->user->getFirstName());
        
        $result = $this->user->setFirstName($firstName);
        
        $this->assertSame($this->user, $result);
        $this->assertEquals($firstName, $this->user->getFirstName());
    }

    public function testGetSetLastName(): void
    {
        $lastName = 'Dupont';
        
        $this->assertNull($this->user->getLastName());
        
        $result = $this->user->setLastName($lastName);
        
        $this->assertSame($this->user, $result);
        $this->assertEquals($lastName, $this->user->getLastName());
    }

    public function testGetSetAvailabilityStart(): void
    {
        $date = new DateTimeImmutable('2024-01-01');
        
        $this->assertNull($this->user->getAvailabilityStart());
        
        $result = $this->user->setAvailabilityStart($date);
        
        $this->assertSame($this->user, $result);
        $this->assertSame($date, $this->user->getAvailabilityStart());
    }

    public function testGetSetAvailabilityEnd(): void
    {
        $date = new DateTimeImmutable('2024-12-31');
        
        $this->assertNull($this->user->getAvailabilityEnd());
        
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
        
        // Ajout du même skill (ne doit pas dupliquer)
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
        
        // Ajout du même projet (ne doit pas dupliquer)
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
        
        // Ajout du même utilisateur (ne doit pas dupliquer)
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
        // Note: receivedInvitations est l'inverse de sentInvitations
        // et est géré automatiquement par Doctrine
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
        
        // Ajout du même ami (ne doit pas dupliquer)
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
        
        // Configurer les mocks pour la relation bidirectionnelle
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
        
        // Ajout de la même conversation (ne doit pas dupliquer)
        $this->user->addConversation($conversation1);
        $this->assertCount(2, $this->user->getConversations());
    }

    public function testRemoveConversation(): void
    {
        $conversation = $this->createMock(Conversation::class);
        
        // Configurer le mock pour la relation bidirectionnelle
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
        
        // Configurer les mocks pour la relation bidirectionnelle
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
        
        // Ajout du même message (ne doit pas dupliquer)
        $this->user->addMessage($message1);
        $this->assertCount(2, $this->user->getMessages());
    }

    public function testRemoveMessage(): void
    {
        // Créer un vrai message au lieu d'un mock
        $message = new Message();
        
        // Ajouter le message à l'utilisateur
        $this->user->addMessage($message);
        $this->assertCount(1, $this->user->getMessages());
        $this->assertSame($this->user, $message->getAuthor());
        
        // Retirer le message
        $result = $this->user->removeMessage($message);
        
        // Vérifications
        $this->assertSame($this->user, $result);
        $this->assertCount(0, $this->user->getMessages());
        $this->assertNull($message->getAuthor());
    }

    public function testFullUserCreation(): void
    {
        $email = 'jean.dupont@example.com';
        $password = 'hashed_password';
        $firstName = 'Jean';
        $lastName = 'Dupont';
        $startDate = new DateTimeImmutable('2024-01-01');
        $endDate = new DateTimeImmutable('2024-12-31');
        
        $skill = $this->createMock(Skills::class);
        $project = $this->createMock(Project::class);
        $friend = $this->createMock(User::class);
        $conversation = $this->createMock(Conversation::class);
        $message = $this->createMock(Message::class);
        
        // Configurer les mocks
        $conversation->method('addUser')->with($this->user);
        $message->method('setAuthor')->with($this->user);
        
        $this->user
            ->setEmail($email)
            ->setPassword($password)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setAvailabilityStart($startDate)
            ->setAvailabilityEnd($endDate)
            ->addSkill($skill)
            ->addProject($project)
            ->addFriend($friend)
            ->addConversation($conversation)
            ->addMessage($message);
        
        // Vérifications
        $this->assertEquals($email, $this->user->getEmail());
        $this->assertEquals($password, $this->user->getPassword());
        $this->assertEquals($firstName, $this->user->getFirstName());
        $this->assertEquals($lastName, $this->user->getLastName());
        $this->assertSame($startDate, $this->user->getAvailabilityStart());
        $this->assertSame($endDate, $this->user->getAvailabilityEnd());
        
        $this->assertCount(1, $this->user->getSkills());
        $this->assertCount(1, $this->user->getProject());
        $this->assertCount(1, $this->user->getFriends());
        $this->assertCount(1, $this->user->getConversations());
        $this->assertCount(1, $this->user->getMessages());
    }

    /**
     * Teste les attributs Doctrine
     */
    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass($this->user);
        
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
        
        // Vérifier l'UniqueConstraint sur email
        $hasUniqueConstraint = false;
        foreach ($classAttributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\UniqueConstraint') {
                $args = $attribute->getArguments();
                if (isset($args['name']) && $args['name'] === 'UNIQ_USER_EMAIL' && 
                    isset($args['fields']) && in_array('email', $args['fields'])) {
                    $hasUniqueConstraint = true;
                }
                break;
            }
        }
        $this->assertTrue($hasUniqueConstraint, 'Class should have UniqueConstraint on email');
    }

    /**
     * Teste les interfaces implémentées
     */
    public function testImplementedInterfaces(): void
    {
        $this->assertInstanceOf(UserInterface::class, $this->user);
        $this->assertInstanceOf(PasswordAuthenticatedUserInterface::class, $this->user);
    }

    


    
/**
 * Test - Vérifie l'ajout de projet avec addProject()
 */
public function testGetProjectsAfterAddProject(): void
{
    $user = new User();
    
    // Créer un mock de Project ou une vraie entité Project
    $project = $this->createMock(Project::class);
    
    // Ajouter le projet
    $user->addProject($project);
    
    // Récupérer les projets
    $projects = $user->getProjects();
    
    $this->assertCount(1, $projects, 'Doit contenir 1 projet');
    $this->assertTrue($projects->contains($project), 'La collection doit contenir le projet ajouté');
}

/**
 * Test - Vérifie l'ajout de plusieurs projets
 */
public function testGetProjectsAfterAddingMultipleProjects(): void
{
    $user = new User();
    
    $project1 = $this->createMock(Project::class);
    $project2 = $this->createMock(Project::class);
    $project3 = $this->createMock(Project::class);
    
    $user->addProject($project1);
    $user->addProject($project2);
    $user->addProject($project3);
    
    $projects = $user->getProjects();
    
    $this->assertCount(3, $projects, 'Doit contenir 3 projets');
    $this->assertTrue($projects->contains($project1), 'Doit contenir le projet 1');
    $this->assertTrue($projects->contains($project2), 'Doit contenir le projet 2');
    $this->assertTrue($projects->contains($project3), 'Doit contenir le projet 3');
}

/**
 * Test - Vérifie que addProject n'ajoute pas de doublons
 */
public function testAddProjectDoesNotAddDuplicates(): void
{
    $user = new User();
    $project = $this->createMock(Project::class);
    
    // Ajouter le même projet deux fois
    $user->addProject($project);
    $user->addProject($project); // Deuxième ajout du même projet
    
    $projects = $user->getProjects();
    
    $this->assertCount(1, $projects, 'La collection ne doit pas contenir de doublons');
}

/**
 * Test - Vérifie la suppression avec removeProject()
 */
public function testGetProjectsAfterRemoveProject(): void
{
    $user = new User();
    
    $project1 = $this->createMock(Project::class);
    $project2 = $this->createMock(Project::class);
    
    $user->addProject($project1);
    $user->addProject($project2);
    
    // Vérifier avant suppression
    $this->assertCount(2, $user->getProjects());
    
    // Supprimer un projet
    $user->removeProject($project1);
    
    $projects = $user->getProjects();
    
    $this->assertCount(1, $projects, 'Doit contenir 1 projet après suppression');
    $this->assertFalse($projects->contains($project1), 'Ne doit plus contenir le projet supprimé');
    $this->assertTrue($projects->contains($project2), 'Doit encore contenir le projet non supprimé');
}

/**
 * Test - Vérifie la suppression d'un projet non existant
 */
public function testRemoveProjectThatDoesNotExist(): void
{
    $user = new User();
    $project1 = $this->createMock(Project::class);
    $project2 = $this->createMock(Project::class);
    
    $user->addProject($project1);
    
    // Compter avant suppression
    $countBefore = $user->getProjects()->count();
    
    // Supprimer un projet qui n'existe pas
    $user->removeProject($project2);
    
    $this->assertCount($countBefore, $user->getProjects(), 'Le nombre de projets ne doit pas changer');
    $this->assertTrue($user->getProjects()->contains($project1), 'Le projet existant doit toujours être présent');
}

/**
 * Test - Vérifie que getProjects retourne la même instance à chaque appel
 */
public function testGetProjectsReturnsSameInstance(): void
{
    $user = new User();
    
    $projects1 = $user->getProjects();
    $projects2 = $user->getProjects();
    
    $this->assertSame($projects1, $projects2, 'getProjects doit retourner la même instance à chaque appel');
}

/**
 * Test - Vérifie le type des éléments dans la collection
 */
public function testGetProjectsContainsProjectEntities(): void
{
    $user = new User();
    
    // Créer un vrai projet ou un mock
    $project = $this->createMock(Project::class);
    
    $user->addProject($project);
    
    $projects = $user->getProjects();
    
    foreach ($projects as $projectItem) {
        $this->assertInstanceOf(Project::class, $projectItem, 'Les éléments doivent être des instances de Project');
    }
}



/**
 * Test - Vérifie la méthode removeProject (anciennement removeUserProject)
 */
public function testNewRemoveProjectMethodWorks(): void
{
    $user = new User();
    $project = $this->createMock(Project::class);
    
    $user->addProject($project);
    $this->assertCount(1, $user->getProjects());
    
    // Utiliser la nouvelle méthode removeProject
    $user->removeProject($project);
    
    $this->assertCount(0, $user->getProjects(), 'removeProject doit supprimer le projet');
    $this->assertFalse($user->getProjects()->contains($project), 'Le projet ne doit plus être dans la collection');
}

/**
 * Test - Vérifie la cohérence entre getProjects et l'ajout/suppression
 */
public function testProjectsCollectionConsistency(): void
{
    $user = new User();
    
    $project1 = $this->createMock(Project::class);
    $project2 = $this->createMock(Project::class);
    $project3 = $this->createMock(Project::class);
    
    // Scénario d'utilisation
    $user->addProject($project1);
    $user->addProject($project2);
    
    $this->assertCount(2, $user->getProjects());
    
    $user->removeProject($project1);
    
    $this->assertCount(1, $user->getProjects());
    $this->assertFalse($user->getProjects()->contains($project1));
    $this->assertTrue($user->getProjects()->contains($project2));
    
    $user->addProject($project3);
    
    $this->assertCount(2, $user->getProjects());
    $this->assertTrue($user->getProjects()->contains($project2));
    $this->assertTrue($user->getProjects()->contains($project3));
}

/**
 * Test d'intégration avec une vraie entité Project (si possible)
 */
public function testIntegrationWithRealProject(): void
{
    // Ce test nécessite d'avoir l'entité Project disponible
    // Si vous avez une fixture ou un moyen de créer un vrai projet
    
    if (!class_exists('App\Entity\Project')) {
        $this->markTestSkipped('L\'entité Project n\'est pas disponible');
    }
    
    $user = new User();
    
    // Créer un vrai projet si possible, sinon utiliser un mock
    if (method_exists($this, 'getEntityManager')) {
        // Si vous êtes dans un test avec accès à la base de données
        $project = new Project();
        // Configurer le projet...
        
        $user->addProject($project);
        
        $this->assertCount(1, $user->getProjects());
        $this->assertTrue($user->getProjects()->contains($project));
    } else {
        $this->markTestSkipped('Test d\'intégration nécessite un contexte Doctrine');
    }
}





public function testRemoveProjectRemovesExistingProject(): void
{
    $user = new User();
    $project = $this->createMock(Project::class);
    
    // Ajouter d'abord le projet
    $user->addProject($project);
    $this->assertCount(1, $user->getProjects());
    $this->assertTrue($user->getProjects()->contains($project));
    
    // Tester la suppression
    $result = $user->removeProject($project);
    
    // Vérifier que le projet a été supprimé
    $this->assertCount(0, $user->getProjects());
    $this->assertFalse($user->getProjects()->contains($project));
    
    // Vérifier le retour fluent (return $this)
    $this->assertSame($user, $result, 'removeProject doit retourner $this pour l\'interface fluent');
}

/**
 * Test pour removeProject() - Vérifie la suppression d'un projet qui n'existe pas
 */
public function testRemoveProjectWithNonExistentProject(): void
{
    $user = new User();
    $project1 = $this->createMock(Project::class);
    $project2 = $this->createMock(Project::class);
    
    // Ajouter un projet
    $user->addProject($project1);
    $this->assertCount(1, $user->getProjects());
    
    // Tenter de supprimer un projet qui n'existe pas
    $result = $user->removeProject($project2);
    
    // Vérifier que la collection n'a pas changé
    $this->assertCount(1, $user->getProjects());
    $this->assertTrue($user->getProjects()->contains($project1));
    $this->assertFalse($user->getProjects()->contains($project2));
    
    // Vérifier le retour fluent
    $this->assertSame($user, $result);
}


}