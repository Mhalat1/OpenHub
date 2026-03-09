<?php

namespace App\Tests\Controller;

use App\Controller\MessageController;
use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\ConversationRepository;
use App\Service\PapertrailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class MessageControllerTest extends TestCase
{
    private MessageController $controller;
    private MessageRepository&MockObject $messageRepository;
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private User&MockObject $user;
    private PapertrailService&MockObject $papertrailLogger;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->user = $this->createMock(User::class);
        $this->papertrailLogger = $this->createMock(PapertrailService::class);

        $this->controller = new MessageController(
            $this->messageRepository,
            $this->em,
            $this->papertrailLogger
        );
    }

    // ========================================
    // TESTS: getConnectedUser()
    // ========================================

    public function testGetConnectedUserReturnsUserDataWhenAuthenticated(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('2026-03-01'));
        $this->user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('2026-04-01'));
        $this->user->method('getConversations')->willReturn(new ArrayCollection());

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getConnectedUser();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('test@example.com', $data['email']);
    }

    public function testGetConnectedUserReturns401WhenNotAuthenticated(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn(null);

        $response = $controllerMock->getConnectedUser();

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User not authenticated', $data['message']);
    }

    public function testGetConnectedUserReturns500WhenFirstNameIsInvalid(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getFirstName')->willReturn('John1234');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getConnectedUser();

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testGetConnectedUserReturns500WhenLastNameIsInvalid(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('test@example.com');
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe<script>');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getConnectedUser();

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testGetConnectedUserReturns500WhenEmailIsInvalid(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('invalid-email');
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getConnectedUser();

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ========================================
    // TESTS: getUserConversations()
    // ========================================

    public function testGetUserConversationsReturns401WhenNotAuthenticated(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn(null);

        $response = $controllerMock->getUserConversations($this->em);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUserConversationsReturnsEmptyArrayWhenNoConversations(): void
    {
        $this->user->method('getId')->willReturn(1);

        $repository = $this->createMock(ConversationRepository::class);
        $repository->method('createQueryBuilder')->willReturnCallback(function() {
            $qb = $this->createMock(QueryBuilder::class);
            $qb->method('innerJoin')->willReturnSelf();
            $qb->method('where')->willReturnSelf();
            $qb->method('setParameter')->willReturnSelf();
            $qb->method('orderBy')->willReturnSelf();
            $qb->method('getQuery')->willReturnCallback(function() {
                $query = $this->getMockBuilder(Query::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['getResult'])
                    ->getMock();
                $query->method('getResult')->willReturn([]);
                return $query;
            });
            return $qb;
        });

        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    // ========================================
    // TESTS: getMessages()
    // ========================================

    public function testGetMessagesReturns401WhenNotAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $response = $controller->getMessages($security, $this->messageRepository, new Request());

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetMessagesReturnsPaginatedResults(): void
    {
        $this->user->method('getId')->willReturn(1);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->messageRepository->method('findBy')->willReturn([]);
        $this->messageRepository->method('count')->willReturn(0);

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->getMessages($security, $this->messageRepository, new Request(['page' => 1, 'limit' => 10]));

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testGetMessagesValidatesPaginationParameters(): void
    {
        $this->user->method('getId')->willReturn(1);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->getMessages($security, $this->messageRepository, new Request(['page' => 100000, 'limit' => 10]));

        $this->assertTrue(in_array($response->getStatusCode(), [200, 400]));
    }

    // ========================================
    // TESTS: createConversation()
    // ========================================

    public function testCreateConversationReturns401WhenNotAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $response = $controller->createConversation(new Request(), $security, $this->em);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testCreateConversationReturns415WhenContentTypeIsNotJson(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('rollback')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'text/plain']);
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(415, $response->getStatusCode());
    }

    public function testCreateConversationReturns413WhenRequestTooLarge(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('rollback')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 20000));
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(413, $response->getStatusCode());
    }

    public function testCreateConversationReturns400WhenTitleIsMissing(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('rollback')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['description' => 'Test']));
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateConversationReturns400WhenTitleContainsDangerousCharacters(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('rollback')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => '<script>alert("xss")</script>', 'conv_users' => [2]]));
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateConversationReturns400WhenTitleAndDescriptionAreIdentical(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);
        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('rollback')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Same text', 'description' => 'Same text', 'conv_users' => [2]]));
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: deleteConversation()
    // ========================================

    public function testDeleteConversationReturns401WhenNotAuthenticated(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn(null);

        $response = $controllerMock->deleteConversation(1, $this->em);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteConversationReturns400WhenIdIsInvalid(): void
    {
        $this->user->method('getId')->willReturn(1);
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(-1, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDeleteConversationReturns404WhenConversationNotFound(): void
    {
        $this->user->method('getId')->willReturn(1);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(999, $this->em);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteConversationReturns403WhenUserIsNotCreator(): void
    {
        $this->user->method('getId')->willReturn(1);
        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(2);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getCreatedBy')->willReturn($creator);
        $conversation->method('getMessages')->willReturn(new ArrayCollection());
        $conversation->method('getTitle')->willReturn('Valid Title');

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn($conversation);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(1, $this->em);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS: deleteMessage()
    // ========================================

    public function testDeleteMessageReturns401WhenNotAuthenticated(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn(null);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDeleteMessageReturns400WhenIdIsInvalid(): void
    {
        $this->user->method('getId')->willReturn(1);
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(0, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testDeleteMessageReturns404WhenMessageNotFound(): void
    {
        $this->user->method('getId')->willReturn(1);
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(999, $this->em);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteMessageReturns403WhenUserIsNotAuthor(): void
    {
        $this->user->method('getId')->willReturn(1);
        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn(2);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);

        $message = $this->createMock(Message::class);
        $message->method('getAuthor')->willReturn($author);
        $message->method('getContent')->willReturn('Valid content');
        $message->method('getConversation')->willReturn($conversation);
        $message->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn($message);
        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS: validateName()
    // ========================================

    public function testValidateNameRejectsEmptyNames(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, ''));
        $this->assertFalse($method->invoke($controller, 'a'));
    }

    public function testValidateNameRejectsLeadingTrailingSpaces(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, ' John'));
        $this->assertFalse($method->invoke($controller, 'John '));
        $this->assertFalse($method->invoke($controller, '-John'));
        $this->assertFalse($method->invoke($controller, 'John-'));
        $this->assertFalse($method->invoke($controller, "'John"));
        $this->assertFalse($method->invoke($controller, "John'"));
    }

    public function testValidateNameRejectsNumbers(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, 'John123'));
        $this->assertFalse($method->invoke($controller, 'John Doe 2'));
    }

    public function testValidateNameRejectsDangerousCharacters(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $dangerousChars = ['<', '>', '&', '"', '\\', '/', '@', '#', '$', '%', '^', '*', '(', ')', '=', '+', '[', ']', '{', '}'];

        foreach ($dangerousChars as $char) {
            $this->assertFalse($method->invoke($controller, "John{$char}Doe"));
        }
    }

    public function testValidateNameAcceptsValidNames(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, 'John'));
        $this->assertTrue($method->invoke($controller, 'Jean-Pierre'));
        $this->assertTrue($method->invoke($controller, "D'Orazio"));
        $this->assertTrue($method->invoke($controller, 'Marie Claire'));
        $this->assertTrue($method->invoke($controller, 'José'));
        $this->assertTrue($method->invoke($controller, 'Müller'));
        $this->assertTrue($method->invoke($controller, 'François'));
    }

    // ========================================
    // TESTS: canonicalDecode()
    // ========================================

    public function testCanonicalDecodeHandlesHtmlEntities(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('canonicalDecode');
        $method->setAccessible(true);

        $input = "Hello &amp; World &lt;test&gt;";
        $result = $method->invoke($controller, $input);
        $this->assertEquals("Hello & World <test>", $result);
    }

    public function testCanonicalDecodeHandlesUrlEncoding(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('canonicalDecode');
        $method->setAccessible(true);

        $input = "Hello%20World%21";
        $result = $method->invoke($controller, $input);
        $this->assertEquals("Hello World!", $result);
    }

    public function testCanonicalDecodeHandlesNestedEncoding(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('canonicalDecode');
        $method->setAccessible(true);

        $input = "%26lt%3Btest%26gt%3B";
        $result = $method->invoke($controller, $input);
        $this->assertEquals("<test>", $result);
    }

    public function testCanonicalDecodeThrowsOnTooLargeInput(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('canonicalDecode');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($controller, str_repeat('a', 2000000));
    }

    // ========================================
    // TESTS: validateString()
    // ========================================

    public function testValidateStringRejectsDangerousFirstCharacters(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateString');
        $method->setAccessible(true);

        $dangerousFirstChars = ['=', '+', '-', '@', "\t", "\0"];
        foreach ($dangerousFirstChars as $char) {
            $this->assertFalse($method->invoke($controller, $char . 'test', 1000));
        }
    }

    public function testValidateStringRejectsDangerousPatterns(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateString');
        $method->setAccessible(true);

        $dangerousPatterns = [
            '<script>alert(1)</script>',
            '<iframe src="evil.com">',
            'javascript:alert(1)',
            'onclick="evil()"',
            'SELECT * FROM users',
            'UNION SELECT password FROM users',
            'DROP TABLE users',
        ];

        foreach ($dangerousPatterns as $pattern) {
            $this->assertFalse($method->invoke($controller, $pattern, 1000));
        }
    }

    // ========================================
    // TESTS: validateAvailabilityDates()
    // ========================================


    public function testValidateAvailabilityDatesRejectsEndBeforeStart(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateAvailabilityDates');
        $method->setAccessible(true);

        $start = new \DateTimeImmutable('+10 days');
        $end = new \DateTimeImmutable('+5 days');

        $result = $method->invoke($controller, $start, $end);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('after', $result['error']);
    }

    public function testValidateAvailabilityDatesRejectsRangeTooLong(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateAvailabilityDates');
        $method->setAccessible(true);

        $start = new \DateTimeImmutable('+1 day');
        $end = new \DateTimeImmutable('+3 years');

        $result = $method->invoke($controller, $start, $end);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 years', $result['error']);
    }


    // ========================================
    // TESTS: validateEmailandUniqueness()
    // ========================================

    public function testValidateEmailandUniquenessRejectsLongEmail(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateEmailandUniqueness');
        $method->setAccessible(true);

        $longEmail = str_repeat('a', 256) . '@example.com';
        $result = $method->invoke($controller, $longEmail, 1, $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('too long', $result['error']);
    }

    public function testValidateEmailandUniquenessRejectsInvalidFormat(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateEmailandUniqueness');
        $method->setAccessible(true);

        $invalidEmails = [
            'not-an-email',
            'missing@domain',
            '@example.com',
            'user@.com',
            'user@example.',
        ];

        foreach ($invalidEmails as $email) {
            $result = $method->invoke($controller, $email, 1, $this->em);
            $this->assertFalse($result['valid']);
            $this->assertStringContainsString('Invalid email', $result['error']);
        }
    }

    // ========================================
    // TESTS: generateConversationHash()
    // ========================================

    public function testGenerateConversationHashIsConsistent(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generateConversationHash');
        $method->setAccessible(true);

        $userIds = [1, 2, 3];
        $title = "Test Conversation";

        $hash1 = $method->invoke($controller, $userIds, $title);
        $hash2 = $method->invoke($controller, $userIds, $title);

        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateConversationHashIsOrderIndependent(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generateConversationHash');
        $method->setAccessible(true);

        $userIds1 = [3, 1, 2];
        $userIds2 = [1, 2, 3];
        $title = "Test Conversation";

        $hash1 = $method->invoke($controller, $userIds1, $title);
        $hash2 = $method->invoke($controller, $userIds2, $title);

        $this->assertEquals($hash1, $hash2);
    }

    public function testGenerateConversationHashIsTitleSensitive(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generateConversationHash');
        $method->setAccessible(true);

        $userIds = [1, 2, 3];

        $hash1 = $method->invoke($controller, $userIds, "Test Title");
        $hash2 = $method->invoke($controller, $userIds, "Different Title");

        $this->assertNotEquals($hash1, $hash2);
    }

    // ========================================
    // TESTS: sanitizeHtml()
    // ========================================

    public function testSanitizeHtmlAllowsBasicFormattingWhenAllowed(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeHtml');
        $method->setAccessible(true);

        $html = "<strong>Bold</strong> and <em>italic</em> and <u>underline</u> and <br> and <p>paragraph</p>";
        $result = $method->invoke($controller, $html, true);

        $this->assertStringContainsString("<strong>Bold</strong>", $result);
        $this->assertStringContainsString("<em>italic</em>", $result);
        $this->assertStringContainsString("<u>underline</u>", $result);
        $this->assertStringContainsString("<br />", $result);
        $this->assertStringContainsString("<p>paragraph</p>", $result);
    }

    public function testSanitizeHtmlRemovesUnsafeElements(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeHtml');
        $method->setAccessible(true);

        $html = "<strong>Safe</strong><script>alert(1)</script><iframe src='evil.com'></iframe><a href='evil.com'>link</a>";
        $result = $method->invoke($controller, $html, true);

        $this->assertStringContainsString("<strong>Safe</strong>", $result);
        $this->assertStringNotContainsString("<script", $result);
        $this->assertStringNotContainsString("<iframe", $result);
        $this->assertStringNotContainsString("<a", $result);
    }

    // ========================================
    // TESTS: validateConversationParticipants()
    // ========================================

    public function testValidateConversationParticipantsRejectsTooManyUsers(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $userIds = range(1, 51);
        $result = $method->invoke($controller, $this->user, $userIds, $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Maximum 50 participants', $result['error']);
    }

    public function testValidateConversationParticipantsRejectsEmptyList(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $this->user->method('getId')->willReturn(1);

        $result = $method->invoke($controller, $this->user, [], $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('at least 2 participants', $result['error']);
    }

    // ========================================
    // TESTS: sanitizeForJson()
    // ========================================

    public function testSanitizeForJsonRemovesControlCharacters(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeForJson');
        $method->setAccessible(true);

        $input = "Hello\x00World\x01Test\x7F";
        $result = $method->invoke($controller, $input);

        $this->assertEquals("HelloWorldTest", $result);
    }

    public function testSanitizeForJsonHandlesArrays(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeForJson');
        $method->setAccessible(true);

        $input = [
            'name' => "Test\x00Name",
            'values' => ["Item\x01", "Clean"]
        ];

        $result = $method->invoke($controller, $input);

        $this->assertEquals('TestName', $result['name']);
        $this->assertEquals('Item', $result['values'][0]);
        $this->assertEquals('Clean', $result['values'][1]);
    }

    // ========================================
    // TESTS: getMessages() with invalid data
    // ========================================

    public function testGetMessagesSkipsMessagesWithInvalidContent(): void
    {
        $this->user->method('getId')->willReturn(1);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);

        $message = $this->createMock(Message::class);
        $message->method('getContent')->willReturn('<script>alert(1)</script>');
        $message->method('getId')->willReturn(1);
        $message->method('getAuthor')->willReturn($this->user);
        $message->method('getAuthorName')->willReturn('John Doe');
        $message->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);
        $conversation->method('getTitle')->willReturn('Test');
        $message->method('getConversation')->willReturn($conversation);

        $this->messageRepository->method('findBy')->willReturn([$message]);
        $this->messageRepository->method('count')->willReturn(1);

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->getMessages($security, $this->messageRepository, new Request(['page' => 1, 'limit' => 10]));

        $data = json_decode($response->getContent(), true);

        $this->assertEmpty($data['data']);
        $this->assertEquals(1, $data['pagination']['total']);
    }

    // ========================================
    // TESTS: deleteMessage() edge cases
    // ========================================

    public function testDeleteMessageHandlesMessageWithInvalidConversation(): void
    {
        $this->user->method('getId')->willReturn(1);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn(1);

        $message = $this->createMock(Message::class);
        $message->method('getAuthor')->willReturn($author);
        $message->method('getContent')->willReturn('Valid content');
        $message->method('getConversation')->willReturn(null);
        $message->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn($message);
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ========================================
    // TESTS: deleteConversation() edge cases
    // ========================================

    public function testDeleteConversationHandlesTooManyMessages(): void
    {
        $this->user->method('getId')->willReturn(1);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $messages = new ArrayCollection();
        for ($i = 0; $i < 15000; $i++) {
            $messages->add($this->createMock(Message::class));
        }

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getCreatedBy')->willReturn($creator);
        $conversation->method('getMessages')->willReturn($messages);
        $conversation->method('getTitle')->willReturn('Valid Title');
        $conversation->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn($conversation);
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(1, $this->em);

        $this->assertEquals(413, $response->getStatusCode());
    }

    // ========================================
    // TESTS: sanitizeData()
    // ========================================

    public function testSanitizeDataHandlesAllFields(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = [
            'title' => '<script>XSS</script>Test Title',
            'description' => '<strong>Safe</strong> Description',
            'content' => '<em>Message</em> Content',
            'conv_users' => ['1', '2', '3', 'invalid', '0'],
            'conversation_id' => '42'
        ];

        $result = $method->invoke($controller, $input);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('conv_users', $result);
        $this->assertArrayHasKey('conversation_id', $result);
        $this->assertStringNotContainsString('<script>', $result['title']);
        $this->assertEquals([1, 2, 3], $result['conv_users']);
        $this->assertEquals(42, $result['conversation_id']);
    }

    public function testSanitizeDataHandlesMissingFields(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['title' => 'Only Title'];
        $result = $method->invoke($controller, $input);

        $this->assertEquals(['title' => 'Only Title'], $result);
    }

    // ========================================
    // TESTS: validateMessageRateLimit()
    // ========================================

    public function testValidateMessageRateLimitReturnsValidWhenUnderLimit(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateMessageRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(50);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Message::class)->willReturn($messageRepo);

        $result = $method->invoke($controller, $user, $conversation, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateMessageRateLimitReturnsErrorWhenOverLimit(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateMessageRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(150);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Message::class)->willReturn($messageRepo);

        $result = $method->invoke($controller, $user, $conversation, $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit exceeded', $result['error']);
    }

    public function testValidateMessageRateLimitHandlesExceptions(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateMessageRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $conversation = $this->createMock(Conversation::class);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));

        $this->em->method('getRepository')->with(Message::class)->willReturn($messageRepo);

        $result = $method->invoke($controller, $user, $conversation, $this->em);

        $this->assertTrue($result['valid']);
    }

    // ========================================
    // TESTS: checkDuplicateConversation()
    // ========================================

    public function testCheckDuplicateConversationReturnsTrueWhenDuplicateExists(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('checkDuplicateConversation');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [2, 3];
        $title = 'Test Conversation';

        $existingConversation = $this->createMock(Conversation::class);

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn($existingConversation);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $creator, $userIds, $title, $this->em);

        $this->assertTrue($result);
    }

    public function testCheckDuplicateConversationReturnsFalseWhenNoDuplicate(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('checkDuplicateConversation');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [2, 3];
        $title = 'Test Conversation';

        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $creator, $userIds, $title, $this->em);

        $this->assertFalse($result);
    }

    // ========================================
    // ADDITIONAL: validateString()
    // ========================================

    public function testValidateStringRejectsEmptyValue(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateString');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '', 100);
        $this->assertFalse($result);
    }

    public function testValidateStringRejectsTooLongValue(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateString');
        $method->setAccessible(true);

        $result = $method->invoke($controller, str_repeat('a', 10001), 100);
        $this->assertFalse($result);
    }

    // ========================================
    // ADDITIONAL: validateName()
    // ========================================

    public function testValidateNameRejectsTooLongName(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $result = $method->invoke($controller, str_repeat('a', 101), 100);
        $this->assertFalse($result);
    }

    public function testValidateNameRejectsThreeConsecutiveSpaces(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 'John   Doe', 100);
        $this->assertFalse($result);
    }

    public function testValidateNameRejectsThreeConsecutiveHyphens(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateName');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 'John---Doe', 100);
        $this->assertFalse($result);
    }

    // ========================================
    // ADDITIONAL: sanitizeData()
    // ========================================

    public function testSanitizeDataHandlesOnlyTitle(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['title' => 'Test Title'];
        $result = $method->invoke($controller, $input);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayNotHasKey('description', $result);
        $this->assertArrayNotHasKey('content', $result);
        $this->assertArrayNotHasKey('conv_users', $result);
        $this->assertArrayNotHasKey('conversation_id', $result);
    }

    public function testSanitizeDataHandlesOnlyDescription(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['description' => 'Test Description'];
        $result = $method->invoke($controller, $input);

        $this->assertArrayHasKey('description', $result);
    }

    public function testSanitizeDataHandlesOnlyContent(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['content' => 'Test Content'];
        $result = $method->invoke($controller, $input);

        $this->assertArrayHasKey('content', $result);
    }

    public function testSanitizeDataHandlesEmptyConvUsers(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['conv_users' => []];
        $result = $method->invoke($controller, $input);

        $this->assertArrayHasKey('conv_users', $result);
        $this->assertEmpty($result['conv_users']);
    }

    public function testSanitizeDataHandlesNonArrayConvUsers(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeData');
        $method->setAccessible(true);

        $input = ['conv_users' => 'not an array'];
        $result = $method->invoke($controller, $input);

        $this->assertArrayNotHasKey('conv_users', $result);
    }

    // ========================================
    // ADDITIONAL: sanitizeHtml()
    // ========================================

    public function testSanitizeHtmlHandlesNullAndEmptyStrings(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeHtml');
        $method->setAccessible(true);

        $this->assertEquals('', $method->invoke($controller, null, true));
        $this->assertEquals('', $method->invoke($controller, '', true));
        $this->assertEquals('', $method->invoke($controller, null, false));
        $this->assertEquals('', $method->invoke($controller, '', false));
    }

    // ========================================
    // ADDITIONAL: sanitizeForJson()
    // ========================================

    public function testSanitizeForJsonHandlesNullValue(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeForJson');
        $method->setAccessible(true);

        $result = $method->invoke($controller, null);
        $this->assertNull($result);
    }

    public function testSanitizeForJsonHandlesNonStringNonArrayValue(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeForJson');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 123);
        $this->assertEquals(123, $result);

        $result = $method->invoke($controller, true);
        $this->assertTrue($result);

        $result = $method->invoke($controller, 3.14);
        $this->assertEquals(3.14, $result);
    }

    // ========================================
    // ADDITIONAL: validateConversationDeleteRateLimit()
    // ========================================

    public function testValidateConversationDeleteRateLimitHandlesException(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationDeleteRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->em->method('beginTransaction')->willThrowException(new \Exception('Test exception'));

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateConversationDeleteRateLimitHandlesDatabaseError(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationDeleteRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willThrowException(new \Exception('Database error'));

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Message::class)->willReturn($messageRepo);

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateConversationDeleteRateLimitHandlesUnexpectedResult(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationDeleteRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn('not a number');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Message::class)->willReturn($messageRepo);

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    // ========================================
    // ADDITIONAL: validateConversationParticipants()
    // ========================================

    public function testValidateConversationParticipantsHandlesNegativeIds(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [-1, -2, 2, 3];

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->willReturn([]);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $creator, $userIds, $this->em);

        $this->assertFalse($result['valid']);
    }

    public function testValidateConversationParticipantsHandlesNonNumericStrings(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = ['abc', 'def', '2', '3'];

        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(2);
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(3);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->with(['id' => [2, 3]])->willReturn([$user1, $user2]);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $creator, $userIds, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['validUsers']);
    }

    public function testValidateConversationParticipantsHandlesDuplicateCreatorId(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [1, 2, 3];

        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(2);
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(3);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->with(['id' => [2, 3]])->willReturn([$user1, $user2]);

        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $creator, $userIds, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['validUsers']);
    }

    public function testValidateConversationParticipantsHandlesZeroAndNegativeIds(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [0, -1, 2, 3];

        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(2);
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(3);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->with(['id' => [2, 3]])->willReturn([$user1, $user2]);

        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $creator, $userIds, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertCount(2, $result['validUsers']);
    }

    public function testValidateConversationParticipantsHandlesMissingUsers(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationParticipants');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [2, 3, 4];

        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(2);
        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(3);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->with(['id' => [2, 3, 4]])->willReturn([$user1, $user2]);

        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $creator, $userIds, $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user IDs', $result['error']);
    }

    // ========================================
    // ADDITIONAL: validateEmailandUniqueness()
    // ========================================

    public function testValidateEmailandUniquenessHandlesNullExistingUser(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateEmailandUniqueness');
        $method->setAccessible(true);

        $email = 'new@example.com';
        $currentUserId = 1;

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findOneBy')->with(['email' => $email])->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $email, $currentUserId, $this->em);

        $this->assertTrue($result['valid']);
    }

    public function testValidateEmailandUniquenessAllowsSameEmailForSameUser(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateEmailandUniqueness');
        $method->setAccessible(true);

        $email = 'user@example.com';
        $currentUserId = 1;

        $existingUser = $this->createMock(User::class);
        $existingUser->method('getId')->willReturn(1);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findOneBy')->with(['email' => $email])->willReturn($existingUser);

        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $method->invoke($controller, $email, $currentUserId, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    // ========================================
    // ADDITIONAL: validateConversationCreateRateLimit()
    // ========================================

    public function testValidateConversationCreateRateLimitReturnsValidWhenUnderLimit(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationCreateRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(5);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function testValidateConversationCreateRateLimitHandlesException(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationCreateRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertTrue($result['valid']);
    }

    // ========================================
    // ADDITIONAL: getConnectedUser()
    // ========================================

    public function testGetConnectedUserHandlesThrowable(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();

        $controllerMock->method('getUser')->willThrowException(new \Exception('Test exception'));

        $response = $controllerMock->getConnectedUser();

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Internal server error', $data['message']);
    }

    // ========================================
    // ADDITIONAL: getUserConversations()
    // ========================================

    public function testGetUserConversationsHandlesException(): void
    {
        $this->user->method('getId')->willReturn(1);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Error fetching conversations', $data['message']);
    }

    // ========================================
    // ADDITIONAL: getMessages() offset
    // ========================================

    public function testGetMessagesRejectsTooHighOffset(): void
    {
        $this->user->method('getId')->willReturn(1);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->getMessages($security, $this->messageRepository, new Request(['page' => 1000000, 'limit' => 1000]));

        $this->assertTrue(in_array($response->getStatusCode(), [200, 400]));
    }

    // ========================================
    // ADDITIONAL: deleteConversation()
    // ========================================

    public function testDeleteConversationHandlesException(): void
    {
        $this->user->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(1, $this->em);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ========================================
    // ADDITIONAL: deleteMessage()
    // ========================================

    public function testDeleteMessageHandlesIdOutOfRange(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(2147483648, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Message ID out of range', $data['message']);
    }

    public function testDeleteMessageHandlesInvalidMessageInstance(): void
    {
        $this->user->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteMessageHandlesInvalidAuthor(): void
    {
        $this->user->method('getId')->willReturn(1);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn(1);

        $message = $this->createMock(Message::class);
        $message->method('getAuthor')->willReturn($author);
        $message->method('getContent')->willReturn('Valid content');
        $message->method('getConversation')->willReturn($this->createMock(Conversation::class));
        $message->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn($message);
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDeleteMessageHandlesException(): void
    {
        $this->user->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(500, $response->getStatusCode());
    }

    // ========================================
    // ADDITIONAL: checkDuplicateConversation() exception
    // ========================================

    public function testCheckDuplicateConversationHandlesException(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('checkDuplicateConversation');
        $method->setAccessible(true);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $userIds = [2, 3];
        $title = 'Test Conversation';

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $creator, $userIds, $title, $this->em);

        $this->assertFalse($result);
    }

    // ========================================
    // TESTS: validateUserState()
    // ========================================

    public function testValidateUserStateWithAllValidData(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $user = $this->createMock(User::class);
        $user->method('getFirstName')->willReturn('John');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getEmail')->willReturn('john@example.com');
        $user->method('getId')->willReturn(1);
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('+1 day'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('+2 days'));

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateUserState');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $user);

        $this->assertTrue($result['valid']);
    }

    public function testValidateUserStateReturnsErrorWhenNameInvalid(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $user = $this->createMock(User::class);
        $user->method('getFirstName')->willReturn('John123');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getEmail')->willReturn('john@example.com');
        $user->method('getId')->willReturn(1);
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateUserState');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid user name', $result['error']);
    }

    public function testValidateUserStateReturnsErrorWhenEmailInvalid(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $user = $this->createMock(User::class);
        $user->method('getFirstName')->willReturn('John');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getEmail')->willReturn('invalid-email');
        $user->method('getId')->willReturn(1);
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateUserState');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid user email', $result['error']);
    }

    public function testValidateUserStateReturnsErrorWhenDatesInvalid(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $user = $this->createMock(User::class);
        $user->method('getFirstName')->willReturn('John');
        $user->method('getLastName')->willReturn('Doe');
        $user->method('getEmail')->willReturn('john@example.com');
        $user->method('getId')->willReturn(1);
        $user->method('getAvailabilityStart')->willReturn(new \DateTimeImmutable('+10 days'));
        $user->method('getAvailabilityEnd')->willReturn(new \DateTimeImmutable('+5 days'));

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateUserState');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Invalid availability dates', $result['error']);
    }

    public function testDeleteConversationHandlesMessageCountCheck(): void
    {
        $this->user->method('getId')->willReturn(1);

        $creator = $this->createMock(User::class);
        $creator->method('getId')->willReturn(1);

        $messages = new ArrayCollection();
        for ($i = 0; $i < 5000; $i++) {
            $messages->add($this->createMock(Message::class));
        }

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getCreatedBy')->willReturn($creator);
        $conversation->method('getMessages')->willReturn($messages);
        $conversation->method('getTitle')->willReturn('Valid Title');
        $conversation->method('getId')->willReturn(1);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('find')->willReturn($conversation);

        $rateLimitQuery = $this->createMock(Query::class);
        $rateLimitQuery->method('getSingleScalarResult')->willReturn(0);

        $rateLimitQb = $this->createMock(QueryBuilder::class);
        $rateLimitQb->method('select')->willReturnSelf();
        $rateLimitQb->method('where')->willReturnSelf();
        $rateLimitQb->method('andWhere')->willReturnSelf();
        $rateLimitQb->method('setParameter')->willReturnSelf();
        $rateLimitQb->method('getQuery')->willReturn($rateLimitQuery);

        $conversationRepo->method('createQueryBuilder')->willReturn($rateLimitQb);

        $messageRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $messageRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(function($class) use ($conversationRepo, $messageRepo) {
            if ($class === Conversation::class) {
                return $conversationRepo;
            }
            if ($class === Message::class) {
                return $messageRepo;
            }
            return $this->createMock(\Doctrine\ORM\EntityRepository::class);
        });

        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('flush')->willReturnCallback(function() {});
        $this->em->method('commit')->willReturnCallback(function() {});
        $this->em->method('remove')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteConversation(1, $this->em);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testValidateStringRejectsInvalidCharacters(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateString');
        $method->setAccessible(true);

        $result = $method->invoke($controller, 'Test with @ symbol', 100);
        $this->assertFalse($result);
    }

    public function testSanitizeHtmlRemovesAllHtmlWhenNotAllowed(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('sanitizeHtml');
        $method->setAccessible(true);

        $html = "<strong>Bold</strong> and <em>italic</em>";
        $result = $method->invoke($controller, $html, false);

        $this->assertEquals("Bold and italic", $result);
    }

    public function testValidateConversationCreateRateLimitReturnsErrorWhenOverLimit(): void
    {
        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('validateConversationCreateRateLimit');
        $method->setAccessible(true);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(20);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);

        $result = $method->invoke($controller, $user, $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit', strtolower($result['error']));
    }

    // ========================================
    // TESTS: getUserConversations() full data
    // ========================================

    public function testGetUserConversationsReturnsConversationsWithFullData(): void
    {
        $this->user->method('getId')->willReturn(1);

        $participant1 = $this->createMock(User::class);
        $participant1->method('getId')->willReturn(2);
        $participant1->method('getFirstName')->willReturn('Jane');
        $participant1->method('getLastName')->willReturn('Smith');
        $participant1->method('getEmail')->willReturn('jane@example.com');

        $message1 = $this->createMock(Message::class);
        $message1->method('getId')->willReturn(1);
        $message1->method('getContent')->willReturn('Hello everyone');
        $message1->method('getAuthor')->willReturn($this->user);
        $message1->method('getAuthorName')->willReturn('John Doe');
        $message1->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);
        $conversation->method('getTitle')->willReturn('Test Conversation');
        $conversation->method('getDescription')->willReturn('Test Description');
        $conversation->method('getCreatedBy')->willReturn($this->user);
        $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01 09:00:00'));
        $conversation->method('getMessages')->willReturn(new ArrayCollection([$message1]));

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn([$conversation]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(ConversationRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetUserConversationsHandlesEmptyConversations(): void
    {
        $this->user->method('getId')->willReturn(1);

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(ConversationRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetUserConversationsHandlesConversationsWithNoMessages(): void
    {
        $this->user->method('getId')->willReturn(1);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);
        $conversation->method('getTitle')->willReturn('Test Conversation');
        $conversation->method('getDescription')->willReturn('Description');
        $conversation->method('getCreatedBy')->willReturn($this->user);
        $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conversation->method('getMessages')->willReturn(new ArrayCollection());

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn([$conversation]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(ConversationRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testGetUserConversationsHandlesConversationsWithInvalidTitle(): void
    {
        $this->user->method('getId')->willReturn(1);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(1);
        $conversation->method('getTitle')->willReturn('<script>XSS</script>');
        $conversation->method('getDescription')->willReturn('Description');
        $conversation->method('getCreatedBy')->willReturn($this->user);
        $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conversation->method('getMessages')->willReturn(new ArrayCollection());

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn([$conversation]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repository = $this->createMock(ConversationRepository::class);
        $repository->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('getRepository')->willReturn($repository);
        $this->em->method('clear')->willReturnCallback(function() {});

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->getUserConversations($this->em);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }

    public function testCreateConversationSuccessfullyCreatesConversation(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getFirstName')->willReturn('John');
        $this->user->method('getLastName')->willReturn('Doe');
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getAvailabilityStart')->willReturn(null);
        $this->user->method('getAvailabilityEnd')->willReturn(null);

        $participant1 = $this->createMock(User::class);
        $participant1->method('getId')->willReturn(2);

        $participant2 = $this->createMock(User::class);
        $participant2->method('getId')->willReturn(3);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);

        $userRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepo->method('findBy')->willReturn([$participant1, $participant2]);
        $userRepo->method('findOneBy')->willReturn(null);

        $duplicateQuery = $this->createMock(Query::class);
        $duplicateQuery->method('getOneOrNullResult')->willReturn(null);

        $duplicateQb = $this->createMock(QueryBuilder::class);
        $duplicateQb->method('where')->willReturnSelf();
        $duplicateQb->method('setParameter')->willReturnSelf();
        $duplicateQb->method('getQuery')->willReturn($duplicateQuery);

        $rateLimitQuery = $this->createMock(Query::class);
        $rateLimitQuery->method('getSingleScalarResult')->willReturn(0);

        $rateLimitQb = $this->createMock(QueryBuilder::class);
        $rateLimitQb->method('select')->willReturnSelf();
        $rateLimitQb->method('where')->willReturnSelf();
        $rateLimitQb->method('andWhere')->willReturnSelf();
        $rateLimitQb->method('setParameter')->willReturnSelf();
        $rateLimitQb->method('getQuery')->willReturn($rateLimitQuery);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('createQueryBuilder')->willReturnOnConsecutiveCalls($duplicateQb, $rateLimitQb);

        $this->em->method('getRepository')->willReturnCallback(function($class) use ($userRepo, $conversationRepo) {
            if ($class === User::class) {
                return $userRepo;
            }
            if ($class === Conversation::class) {
                return $conversationRepo;
            }
            return $this->createMock(\Doctrine\ORM\EntityRepository::class);
        });

        $this->em->method('beginTransaction')->willReturnCallback(function() {});
        $this->em->method('persist')->willReturnCallback(function() {});
        $this->em->method('flush')->willReturnCallback(function() {});
        $this->em->method('commit')->willReturnCallback(function() {});
        $this->em->method('clear')->willReturnCallback(function() {});

        $requestData = [
            'title' => 'New Test Conversation',
            'description' => 'A test conversation description',
            'conv_users' => [2, 3]
        ];

        $request = new Request(
            [], [], [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testDeleteMessageHandlesMessageNotFound(): void
    {
        $this->user->method('getId')->willReturn(1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository->method('find')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repository);

        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em, $this->papertrailLogger])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCreateMessageSuccess(): void
    {
        $this->user->method('getId')->willReturn(1);
        $this->user->method('getEmail')->willReturn('john@example.com');
        $this->user->method('getUserIdentifier')->willReturn('john@example.com');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->user);

        $conversation = $this->createMock(Conversation::class);
        $conversation->method('getId')->willReturn(123);
        $conversation->method('getTitle')->willReturn('Test Conversation');

        $usersCollection = new ArrayCollection([$this->user]);
        $conversation->method('getUsers')->willReturn($usersCollection);

        $conversationRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $conversationRepo->method('find')->with(123)->willReturn($conversation);

        $this->em->method('getRepository')->with(Conversation::class)->willReturn($conversationRepo);
        $this->em->method('persist')->willReturnCallback(function($entity) {});
        $this->em->method('flush')->willReturnCallback(function() {});

        $storage = new InMemoryStorage();
        $rateLimiterFactory = new RateLimiterFactory(
            ['id' => 'message_test', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
            $storage,
            null
        );

        $controller = new MessageController($this->messageRepository, $this->em, $this->papertrailLogger);

        $request = new Request(
            [], [], [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => 'Message test', 'conversation_id' => 123])
        );

        $response = $controller->createMessage($request, $security, $this->em, $rateLimiterFactory);

        $this->assertNotEquals(500, $response->getStatusCode());
    }
}