<?php

namespace App\Tests\Controller;

use App\Controller\MessageController;
use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Service\PapertrailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Doctrine\Common\Collections\ArrayCollection;

class MessageControllerCoverageTest extends TestCase
{
    private MessageRepository&MockObject $messageRepo;
    private EntityManagerInterface&MockObject $em;
    private PapertrailService&MockObject $papertrail;
    private MessageController $controller;

    protected function setUp(): void
    {
        $this->messageRepo = $this->createMock(MessageRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->papertrail = $this->createMock(PapertrailService::class);

        $this->controller = new MessageController(
            $this->messageRepo,
            $this->em,
            $this->papertrail
        );
    }

    // =======================================================================
    // 1. TESTS POUR validateString (branche manquante ligne 95)
    // =======================================================================

    public function testValidateStringAllBranches(): void
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateString');
        $method->setAccessible(true);

        // Test valeur vide (ligne 98)
        $result = $method->invoke($this->controller, '', 100);
        $this->assertFalse($result);

        // Test trop long (ligne 98)
        $result = $method->invoke($this->controller, str_repeat('a', 10001), 100);
        $this->assertFalse($result);

        // Test avec caractères non autorisés (ligne 105)
        $result = $method->invoke($this->controller, 'Test@Value', 100);
        $this->assertFalse($result);

        // Test avec premier caractère dangereux (ligne 111)
        $dangerousFirstChars = ['=', '+', '-', '@', "\t", "\0"];
        foreach ($dangerousFirstChars as $char) {
            $result = $method->invoke($this->controller, $char . 'test', 100);
            $this->assertFalse($result, "Failed for first char: $char");
        }

        // Test pattern XSS (ligne 117)
        $result = $method->invoke($this->controller, '<script>alert(1)</script>', 100);
        $this->assertFalse($result);

        // Test pattern SQL injection (lignes 121-123)
        $sqlPatterns = [
            'select * from users',
            'union select password',
            'drop table users'
        ];
        foreach ($sqlPatterns as $pattern) {
            $result = $method->invoke($this->controller, $pattern, 100);
            $this->assertFalse($result);
        }

        // Test valeur valide (ligne 132)
        $result = $method->invoke($this->controller, 'Hello world, this is valid!', 100);
        $this->assertTrue($result);
    }

    // =======================================================================
    // 2. TESTS POUR validateName (lignes 136-171)
    // =======================================================================

    public function testValidateNameAllBranches(): void
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateName');
        $method->setAccessible(true);

        // Test vide (ligne 138)
        $result = $method->invoke($this->controller, '');
        $this->assertFalse($result);

        // Test trop court (ligne 138)
        $result = $method->invoke($this->controller, 'A');
        $this->assertFalse($result);

        // Test trop long (ligne 138)
        $result = $method->invoke($this->controller, str_repeat('a', 21));
        $this->assertFalse($result);

        // Test espace au début (ligne 143)
        $result = $method->invoke($this->controller, ' Jean');
        $this->assertFalse($result);

        // Test espace à la fin (ligne 143)
        $result = $method->invoke($this->controller, 'Jean ');
        $this->assertFalse($result);

        // Test tiret au début (ligne 143)
        $result = $method->invoke($this->controller, '-Jean');
        $this->assertFalse($result);

        // Test apostrophe à la fin (ligne 143)
        $result = $method->invoke($this->controller, "Jean'");
        $this->assertFalse($result);

        // Test caractères non alphabétiques (ligne 148)
        $result = $method->invoke($this->controller, 'Jean123');
        $this->assertFalse($result);

        // Test chiffres (ligne 153)
        $result = $method->invoke($this->controller, 'Jean2');
        $this->assertFalse($result);

        // Test caractères dangereux (lignes 158-163)
        $dangerousChars = ['<', '>', '&', '"', '\\', '/', '@', '#', '$', '%', '^', '*', '(', ')', '=', '+', '[', ']', '{', '}'];
        foreach ($dangerousChars as $char) {
            $result = $method->invoke($this->controller, "Jean{$char}Luc");
            $this->assertFalse($result, "Failed for char: $char");
        }

        // Test 3 tirets consécutifs (ligne 166)
        $result = $method->invoke($this->controller, 'Jean---Luc');
        $this->assertFalse($result);

        // Test 3 apostrophes consécutifs (ligne 166)
        $result = $method->invoke($this->controller, "Jean'''Luc");
        $this->assertFalse($result);

        // Test 3 espaces consécutifs (ligne 166)
        $result = $method->invoke($this->controller, 'Jean   Luc');
        $this->assertFalse($result);

        // Test valide avec tiret (ligne 170)
        $result = $method->invoke($this->controller, 'Jean-Luc');
        $this->assertTrue($result);

        // Test valide avec apostrophe
        $result = $method->invoke($this->controller, "O'Brien");
        $this->assertTrue($result);

        // Test valide avec accents
        $result = $method->invoke($this->controller, 'Éléonore');
        $this->assertTrue($result);
    }

    // =======================================================================
    // 3. TESTS POUR validateConversationDeleteRateLimit (lignes 322-342)
    // =======================================================================
public function testValidateConversationDeleteRateLimit(): void
{
    $method = new \ReflectionMethod(MessageController::class, 'validateConversationDeleteRateLimit');
    $method->setAccessible(true);

    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);

    // Test cas nominal (lignes 324-333)
    $result = $method->invoke($this->controller, $user, $this->em);
    $this->assertTrue($result['valid']);
    $this->assertNull($result['error']);

    // Test cas d'exception (lignes 335-341)
    $emWithException = $this->createMock(EntityManagerInterface::class);
    
    // IMPORTANT: Il faut que getRepository soit appelé pour déclencher l'exception
    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('createQueryBuilder')
        ->willThrowException(new \Exception('DB error'));
    
    // Configuration pour que getRepository retourne le repository qui lance l'exception
    $emWithException->method('getRepository')
        ->with(Conversation::class)
        ->willReturn($convRepo);

    // Vérifier que le logger est appelé
    $this->papertrail->expects($this->once())
        ->method('warning')
        ->with('Rate limit check failed', $this->callback(function($context) {
            return isset($context['user_id']) && $context['user_id'] === 1;
        }));

    // Appeler la méthode avec l'entity manager qui va générer l'exception
    $result = $method->invoke($this->controller, $user, $emWithException);
    $this->assertTrue($result['valid']); // Fail-open
    $this->assertNull($result['error']);
}
    // =======================================================================
    // 4. TESTS POUR validateUserState (lignes 480-568)
    // =======================================================================

    public function testValidateUserStateAllBranches(): void
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $method->setAccessible(true);

        // Test avec firstName invalide (lignes 500-541)
        $userInvalidFirstName = $this->createMock(User::class);
        $userInvalidFirstName->method('getId')->willReturn(1);
        $userInvalidFirstName->method('getEmail')->willReturn('test@example.com');
        $userInvalidFirstName->method('getFirstName')->willReturn('Jean123');
        $userInvalidFirstName->method('getLastName')->willReturn('Dupont');
        $userInvalidFirstName->method('getAvailabilityStart')->willReturn(null);
        $userInvalidFirstName->method('getAvailabilityEnd')->willReturn(null);

        $result = $method->invoke($this->controller, $userInvalidFirstName);
        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid user name', $result['error']);

        // Test avec lastName invalide
        $userInvalidLastName = $this->createMock(User::class);
        $userInvalidLastName->method('getId')->willReturn(1);
        $userInvalidLastName->method('getEmail')->willReturn('test@example.com');
        $userInvalidLastName->method('getFirstName')->willReturn('Jean');
        $userInvalidLastName->method('getLastName')->willReturn('Dupont<>');
        $userInvalidLastName->method('getAvailabilityStart')->willReturn(null);
        $userInvalidLastName->method('getAvailabilityEnd')->willReturn(null);

        $result = $method->invoke($this->controller, $userInvalidLastName);
        $this->assertFalse($result['valid']);

        // Test avec email invalide (lignes 543-550)
        $userInvalidEmail = $this->createMock(User::class);
        $userInvalidEmail->method('getId')->willReturn(1);
        $userInvalidEmail->method('getEmail')->willReturn('bad-email');
        $userInvalidEmail->method('getFirstName')->willReturn('Jean');
        $userInvalidEmail->method('getLastName')->willReturn('Dupont');
        $userInvalidEmail->method('getAvailabilityStart')->willReturn(null);
        $userInvalidEmail->method('getAvailabilityEnd')->willReturn(null);

        // Mock du repository User pour la validation d'email
        $userRepo = $this->createMock(EntityRepository::class);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($this->controller, $userInvalidEmail);
        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid user email', $result['error']);

        // Test avec dates invalides (lignes 552-564)
        $userInvalidDates = $this->createMock(User::class);
        $userInvalidDates->method('getId')->willReturn(1);
        $userInvalidDates->method('getEmail')->willReturn('test@example.com');
        $userInvalidDates->method('getFirstName')->willReturn('Jean');
        $userInvalidDates->method('getLastName')->willReturn('Dupont');
        $userInvalidDates->method('getAvailabilityStart')
            ->willReturn(new \DateTimeImmutable('+10 days'));
        $userInvalidDates->method('getAvailabilityEnd')
            ->willReturn(new \DateTimeImmutable('+1 day'));

        $result = $method->invoke($this->controller, $userInvalidDates);
        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid availability dates', $result['error']);

        // Test utilisateur valide (lignes 566-568)
        $userValid = $this->createMock(User::class);
        $userValid->method('getId')->willReturn(1);
        $userValid->method('getEmail')->willReturn('test@example.com');
        $userValid->method('getFirstName')->willReturn('Jean');
        $userValid->method('getLastName')->willReturn('Dupont');
        $userValid->method('getAvailabilityStart')->willReturn(null);
        $userValid->method('getAvailabilityEnd')->willReturn(null);

        // Mock pour findOneBy retournant null (email unique)
        $userRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($this->controller, $userValid);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    // =======================================================================
    // 5. TESTS POUR validateConversationParticipants (lignes 344-405)
    // =======================================================================
public function testValidateConversationParticipants(): void
{
    $method = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
    $method->setAccessible(true);

    $creator = $this->createMock(User::class);
    $creator->method('getId')->willReturn(1);

    // Test trop de participants (ligne 347)
    $result = $method->invoke($this->controller, $creator, range(2, 53), $this->em);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('50 participants', $result['error']);

    // Test IDs non numériques (lignes 358-360) - DEVRAIT RETOURNER false car vide
    $result = $method->invoke($this->controller, $creator, ['abc', 'xyz'], $this->em);
    $this->assertFalse($result['valid']); // Correction: false car empty($validUserIds)

    // Test IDs négatifs (lignes 364-366) - DEVRAIT RETOURNER false
    $result = $method->invoke($this->controller, $creator, [-1, -2], $this->em);
    $this->assertFalse($result['valid']); // Correction: false

    // Test créateur inclus (lignes 368-371) - DEVRAIT RETOURNER false car plus que créateur?
    // Si seulement [1] est passé, ça donne validUserIds vide → false
    $result = $method->invoke($this->controller, $creator, [1], $this->em);
    $this->assertFalse($result['valid']);

    // Test aucun participant valide (ligne 377)
    $result = $method->invoke($this->controller, $creator, [], $this->em);
    $this->assertFalse($result['valid']);

    // Test participants inexistants (lignes 386-398)
    $participant1 = $this->createMock(User::class);
    $participant1->method('getId')->willReturn(2);
    
    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findBy')
        ->with(['id' => [2, 3]])
        ->willReturn([$participant1]); // Seulement l'ID 2 trouvé
    
    $em = $this->createMock(EntityManagerInterface::class);
    $em->method('getRepository')->with(User::class)->willReturn($userRepo);

    $result = $method->invoke($this->controller, $creator, [2, 3], $em);
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('3', $result['error']);

    // Test participants valides (lignes 400-404)
    $participant2 = $this->createMock(User::class);
    $participant2->method('getId')->willReturn(3);
    
    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findBy')
        ->with(['id' => [2, 3]])
        ->willReturn([$participant1, $participant2]);
    
    $em->method('getRepository')->with(User::class)->willReturn($userRepo);

    $result = $method->invoke($this->controller, $creator, [2, 3], $em);
    $this->assertTrue($result['valid']);
    $this->assertCount(2, $result['validUsers']);
}

    // =======================================================================
    // 6. TESTS POUR getConnectedUser (lignes 657-775)
    // =======================================================================

    public function testGetConnectedUserAllBranches(): void
    {
        // Test non authentifié (lignes 661-667)
        $controller = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return null;
            }
        };
        $response = $controller->getConnectedUser();
        $this->assertSame(401, $response->getStatusCode());

        // Test mauvais type d'utilisateur (lignes 669-674)
        $controller = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return new class implements \Symfony\Component\Security\Core\User\UserInterface {
                    public function getRoles(): array { return []; }
                    public function eraseCredentials(): void {}
                    public function getUserIdentifier(): string { return 'anon'; }
                };
            }
        };
        $response = $controller->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());

        // Test firstName invalide (lignes 681-690)
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean123');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getEmail')->willReturn('test@example.com');

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());

        // Test lastName invalide (lignes 692-702)
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont123');
        $user->method('getEmail')->willReturn('test@example.com');

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());

        // Test email invalide (lignes 706-717)
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getEmail')->willReturn('bad-email');

        $userRepo = $this->createMock(EntityRepository::class);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());

        // Test utilisateur valide (lignes 759-767)
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getEmail')->willReturn('test@example.com');
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);
        $user->method('getConversations')->willReturn(new ArrayCollection([]));

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $controller = $this->createControllerWithUser($user);
        $response = $controller->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Jean', $data['firstName']);
    }

    // =======================================================================
    // 7. TESTS POUR getUserConversations (lignes 777-901)
    // =======================================================================

  public function testGetUserConversationsAllBranches(): void
{
    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);
    $user->method('getEmail')->willReturn('test@example.com');
    $user->method('getFirstName')->willReturn('Jean');
    $user->method('getLastName')->willReturn('Dupont');

    // Test non authentifié (lignes 780-784)
    $controller = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
        public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
        {
            return null;
        }
    };
    $response = $controller->getUserConversations($this->em);
    $this->assertSame(401, $response->getStatusCode());

    // IMPORTANT: Configurer le repository User pour la validation d'email
    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findOneBy')->willReturn(null);
    $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

    // Test titre invalide (lignes 800-807)
    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getId')->willReturn(1);
    $conversation->method('getTitle')->willReturn('<script>alert</script>');
    $conversation->method('getDescription')->willReturn('desc');
    $conversation->method('getUsers')->willReturn(new ArrayCollection([]));
    $conversation->method('getCreatedBy')->willReturn($user);

    $controller = $this->createControllerWithUser($user);
    $this->mockConversationRepository([$conversation]);
    
    $response = $controller->getUserConversations($this->em);
    $data = json_decode($response->getContent(), true);
    $this->assertIsArray($data);
    $this->assertEmpty($data); // Conversation sautée

    // Test participants avec noms invalides (lignes 822-832)
    $badParticipant = $this->createMock(User::class);
    $badParticipant->method('getId')->willReturn(2);
    $badParticipant->method('getEmail')->willReturn('bad@example.com');
    $badParticipant->method('getFirstName')->willReturn('Marie123');
    $badParticipant->method('getLastName')->willReturn('Curie');

    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getId')->willReturn(1);
    $conversation->method('getTitle')->willReturn('Valid Title');
    $conversation->method('getDescription')->willReturn('desc');
    $conversation->method('getUsers')->willReturn(new ArrayCollection([$user, $badParticipant]));
    $conversation->method('getCreatedBy')->willReturn($user);

    $this->mockConversationRepository([$conversation]);
    
    $response = $controller->getUserConversations($this->em);
    $data = json_decode($response->getContent(), true);
    $this->assertEmpty($data); // Conversation sautée car aucun participant valide

    // Test créateur avec nom invalide (lignes 866-873)
    $badCreator = $this->createMock(User::class);
    $badCreator->method('getId')->willReturn(3);
    $badCreator->method('getEmail')->willReturn('creator@example.com');
    $badCreator->method('getFirstName')->willReturn('Creator123');
    $badCreator->method('getLastName')->willReturn('Bad');

    $goodParticipant = $this->createMock(User::class);
    $goodParticipant->method('getId')->willReturn(2);
    $goodParticipant->method('getEmail')->willReturn('good@example.com');
    $goodParticipant->method('getFirstName')->willReturn('Marie');
    $goodParticipant->method('getLastName')->willReturn('Curie');

    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getId')->willReturn(1);
    $conversation->method('getTitle')->willReturn('Valid Title');
    $conversation->method('getDescription')->willReturn('desc');
    $conversation->method('getUsers')->willReturn(new ArrayCollection([$user, $goodParticipant]));
    $conversation->method('getCreatedBy')->willReturn($badCreator);

    $this->mockConversationRepository([$conversation]);
    
    $response = $controller->getUserConversations($this->em);
    $data = json_decode($response->getContent(), true);
    $this->assertNotEmpty($data); // La conversation devrait être retournée
    $this->assertEquals('Unknown', $data[0]['createdBy']); // Fallback à Unknown
}

    // =======================================================================
    // 8. TESTS POUR getMessages (lignes 911-1050)
    // =======================================================================
public function testGetMessagesAllBranches(): void
{
    $security = $this->createMock(Security::class);
    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);
    $user->method('getEmail')->willReturn('test@example.com');
    $security->method('getUser')->willReturn($user);

    // IMPORTANT: Ajouter BusinessLimits pour le test
    if (!defined('App\BusinessLimits::PAGINATION_MAX_OFFSET')) {
        // Simuler la constante si nécessaire
    }

    // Test pagination avec offset trop grand (lignes 941-951)
    // Correction: Utiliser une valeur qui dépasse effectivement la limite
    $request = new Request(['page' => 1000000, 'limit' => 100]);
    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertSame(400, $response->getStatusCode()); // Maintenant OK

    // Test message avec contenu invalide (lignes 973-979)
    $message = $this->createMock(Message::class);
    $message->method('getId')->willReturn(1);
    $message->method('getContent')->willReturn('<script>alert</script>');
    $message->method('getAuthor')->willReturn($user);
    $message->method('getAuthorName')->willReturn('Jean Dupont');
    $conversation = $this->createMock(Conversation::class);
    $message->method('getConversation')->willReturn($conversation);
    $message->method('getConversationTitle')->willReturn('Test');
    $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

    $this->messageRepo->method('findBy')->willReturn([$message]);
    $this->messageRepo->method('count')->willReturn(1);

    $response = $this->controller->getMessages($security, $this->messageRepo, new Request(['page' => 1, 'limit' => 10]));
    $data = json_decode($response->getContent(), true);
    $this->assertEmpty($data['data']); // Message sauté

    // Test message sans conversation (lignes 1020-1025)
    $message = $this->createMock(Message::class);
    $message->method('getId')->willReturn(1);
    $message->method('getContent')->willReturn('Hello');
    $message->method('getAuthor')->willReturn($user);
    $message->method('getAuthorName')->willReturn('Jean Dupont');
    $message->method('getConversation')->willReturn(null);
    $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

    $this->messageRepo->method('findBy')->willReturn([$message]);

    $response = $this->controller->getMessages($security, $this->messageRepo, new Request(['page' => 1, 'limit' => 10]));
    $data = json_decode($response->getContent(), true);
    $this->assertEmpty($data['data']); // Message sauté
}
    // =======================================================================
    // 9. TESTS POUR deleteConversation (lignes 1456-1555)
    // =======================================================================
public function testDeleteConversationAllBranches(): void
{
    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);

    // Test non authentifié (lignes 1459-1463)
    $controller = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
        public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
        {
            return null;
        }
    };
    $response = $controller->deleteConversation(1, $this->em);
    $this->assertSame(401, $response->getStatusCode());

    // Test ID invalide (lignes 1466-1468)
    $controller = $this->createControllerWithUser($user);
    $response = $controller->deleteConversation(0, $this->em);
    $this->assertSame(400, $response->getStatusCode());

    // Test conversation non trouvée (lignes 1472-1476)
    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn(null);
    $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

    $response = $controller->deleteConversation(999, $this->em);
    $this->assertSame(404, $response->getStatusCode());

    // Test trop de messages (lignes 1479-1488)
    $conversation = $this->createMock(Conversation::class);
    $messageCollection = new ArrayCollection();
    for ($i = 0; $i < 10001; $i++) {
        $messageCollection->add($this->createMock(Message::class));
    }
    $conversation->method('getMessages')->willReturn($messageCollection);
    $conversation->method('getCreatedBy')->willReturn($user);

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conversation);
    $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

    $response = $controller->deleteConversation(1, $this->em);
    $this->assertSame(413, $response->getStatusCode()); // Maintenant OK

    // Test non créateur (lignes 1499-1501)
    $otherUser = $this->createMock(User::class);
    $otherUser->method('getId')->willReturn(2);
    
    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getMessages')->willReturn(new ArrayCollection([]));
    $conversation->method('getCreatedBy')->willReturn($otherUser);
    $conversation->method('getTitle')->willReturn('Test');

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conversation);
    $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

    $response = $controller->deleteConversation(1, $this->em);
    $this->assertSame(403, $response->getStatusCode());

    // Test titre invalide (lignes 1504-1510)
    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getMessages')->willReturn(new ArrayCollection([]));
    $conversation->method('getCreatedBy')->willReturn($user);
    $conversation->method('getTitle')->willReturn('<script>bad</script>');

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conversation);
    $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

    $this->papertrail->expects($this->atLeastOnce())->method('warning');
    
    $response = $controller->deleteConversation(1, $this->em);
    $this->assertSame(200, $response->getStatusCode());
}
    // =======================================================================
    // 10. TESTS POUR deleteMessage (lignes 1557-1652)
    // =======================================================================
public function testDeleteMessageAllBranches(): void
{
    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);

    // Test non authentifié (lignes 1560-1564)
    $controller = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
        public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
        {
            return null;
        }
    };
    $response = $controller->deleteMessage(1, $this->em);
    $this->assertSame(401, $response->getStatusCode());

    // Test ID invalide (lignes 1568-1570)
    $controller = $this->createControllerWithUser($user);
    $response = $controller->deleteMessage(0, $this->em);
    $this->assertSame(400, $response->getStatusCode());

    // Test ID trop grand (lignes 1573-1575)
    // Note: PHP convertit automatiquement les grands entiers, mieux vaut utiliser un string
    $response = $controller->deleteMessage(2147483647 + 1, $this->em); // Dépassement
    $this->assertSame(400, $response->getStatusCode());

    // Test message non trouvé (lignes 1579-1583)
    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('find')->willReturn(null);
    $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

    $response = $controller->deleteMessage(1, $this->em);
    $this->assertSame(404, $response->getStatusCode());

    // Test non auteur (lignes 1585-1587)
    $otherUser = $this->createMock(User::class);
    $otherUser->method('getId')->willReturn(2);

    $message = $this->createMock(Message::class);
    $message->method('getAuthor')->willReturn($otherUser);
    $message->method('getConversation')->willReturn($this->createMock(Conversation::class));

    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('find')->willReturn($message);
    $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

    $response = $controller->deleteMessage(1, $this->em);
    $this->assertSame(403, $response->getStatusCode()); // Maintenant OK

    // Test message sans conversation (lignes 1623-1631)
    $message = $this->createMock(Message::class);
    $message->method('getAuthor')->willReturn($user);
    $message->method('getContent')->willReturn('Hello');
    $message->method('getConversation')->willReturn(null);

    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('find')->willReturn($message);
    $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

    $this->papertrail->expects($this->once())->method('warning');
    
    $response = $controller->deleteMessage(1, $this->em);
    $this->assertSame(500, $response->getStatusCode());

    // Test succès (lignes 1632-1644)
    $conversation = $this->createMock(Conversation::class);
    $message = $this->createMock(Message::class);
    $message->method('getAuthor')->willReturn($user);
    $message->method('getContent')->willReturn('Hello');
    $message->method('getConversation')->willReturn($conversation);

    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('find')->willReturn($message);
    $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

    $response = $controller->deleteMessage(1, $this->em);
    $this->assertSame(200, $response->getStatusCode());
}

    // =======================================================================
    // MÉTHODES HELPER
    // =======================================================================

    private function createControllerWithUser($user): MessageController
    {
        return new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return $this->user;
            }
        };
    }

    private function mockConversationRepository(array $conversations): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($conversations);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($queryBuilder);
        
        $this->em->method('getRepository')
            ->with(Conversation::class)
            ->willReturn($convRepo);
    }
}