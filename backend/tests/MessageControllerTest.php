<?php

namespace App\Tests\Controller;

use App\Controller\MessageController;
use App\Entity\User;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PHPUnit\Framework\MockObject\MockObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

class MessageControllerTest extends TestCase
{
    private MessageController $controller;
    private MessageRepository&MockObject $messageRepository;
    private EntityManagerInterface&MockObject $em;
    private Security&MockObject $security;
    private User&MockObject $user;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->user = $this->createMock(User::class);

        $this->controller = new MessageController(
            $this->messageRepository,
            $this->em
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
        $controller = new MessageController($this->messageRepository, $this->em);

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

        $controller = new MessageController($this->messageRepository, $this->em);
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

        $controller = new MessageController($this->messageRepository, $this->em);
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
        $controller = new MessageController($this->messageRepository, $this->em);

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
        $controller = new MessageController($this->messageRepository, $this->em);
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
        $controller = new MessageController($this->messageRepository, $this->em);
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
        $controller = new MessageController($this->messageRepository, $this->em);
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
        $controller = new MessageController($this->messageRepository, $this->em);
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
        $controller = new MessageController($this->messageRepository, $this->em);
        $response = $controller->createConversation($request, $security, $this->em);

        $this->assertEquals(400, $response->getStatusCode());
    }

    // ========================================
    // TESTS: deleteConversation()
    // ========================================

    public function testDeleteConversationReturns401WhenNotAuthenticated(): void
    {
        $controllerMock = $this->getMockBuilder(MessageController::class)
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
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
            ->setConstructorArgs([$this->messageRepository, $this->em])
            ->onlyMethods(['getUser'])
            ->getMock();
        $controllerMock->method('getUser')->willReturn($this->user);

        $response = $controllerMock->deleteMessage(1, $this->em);

        $this->assertEquals(403, $response->getStatusCode());
    }
}