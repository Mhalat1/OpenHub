<?php

namespace App\Tests\Repository;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\Conversation;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private MessageRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Message::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    private function createTestUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('password123');
        $user->setRoles(['ROLE_USER']);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $this->entityManager->persist($user);
        return $user;
    }

    private function createTestConversation(): Conversation
    {
        $conversation = new Conversation();
        // Si Conversation a des champs requis, définissez-les ici
        $this->entityManager->persist($conversation);
        return $conversation;
    }

    private function createTestMessage(
        ?User $author = null, 
        ?Conversation $conversation = null, 
        string $content = 'Test message content'
    ): Message {
        if (!$author) {
            $author = $this->createTestUser('author_' . uniqid() . '@example.com');
        }
        
        if (!$conversation) {
            $conversation = $this->createTestConversation();
        }

        $message = new Message();
        $message->setContent($content);
        $message->setAuthor($author);
        $message->setConversation($conversation);
        $message->setCreatedAt(new \DateTimeImmutable());
        
        $this->entityManager->persist($message);
        return $message;
    }

    public function testRepositoryIsInstanceOfMessageRepository(): void
    {
        $this->assertInstanceOf(MessageRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $messages = $this->repository->findAll();
        $this->assertIsArray($messages);
    }

    public function testFindOneBy(): void
    {
        // Create test message with all required dependencies
        $message = $this->createTestMessage();
        $this->entityManager->flush();

        $foundMessage = $this->repository->findOneBy(['id' => $message->getId()]);
        
        $this->assertNotNull($foundMessage);
        $this->assertInstanceOf(Message::class, $foundMessage);
        $this->assertEquals($message->getId(), $foundMessage->getId());
        $this->assertEquals('Test message content', $foundMessage->getContent());
        $this->assertNotNull($foundMessage->getAuthor());
        $this->assertNotNull($foundMessage->getConversation());

        // Cleanup
        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }

    public function testFindBy(): void
    {
        // Create test messages with all required dependencies
        $author = $this->createTestUser('author_findby@example.com');
        $conversation = $this->createTestConversation();
        
        $message1 = $this->createTestMessage($author, $conversation, 'First test message');
        $message2 = $this->createTestMessage($author, $conversation, 'Second test message');
        $this->entityManager->flush();

        $messages = $this->repository->findBy([], ['id' => 'ASC']);
        
        $this->assertIsArray($messages);
        $this->assertGreaterThanOrEqual(2, count($messages));

        // Vérifier que nos messages sont bien dans la liste
        $foundMessages = array_filter($messages, fn($m) => 
            $m->getId() === $message1->getId() || $m->getId() === $message2->getId()
        );
        $this->assertCount(2, $foundMessages);

        // Cleanup
        $this->entityManager->remove($message1);
        $this->entityManager->remove($message2);
        $this->entityManager->flush();
    }

    public function testCount(): void
    {
        $initialCount = $this->repository->count([]);
        
        // Add a test message
        $message = $this->createTestMessage();
        $this->entityManager->flush();
        
        $newCount = $this->repository->count([]);
        $this->assertEquals($initialCount + 1, $newCount);

        // Cleanup
        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $message = $this->repository->findOneBy(['id' => 999999]);
        $this->assertNull($message);
    }

    public function testFindByOrderBy(): void
    {
        // Create messages with different properties to test ordering
        $author = $this->createTestUser('author_order@example.com');
        $conversation = $this->createTestConversation();
        
        $message1 = $this->createTestMessage($author, $conversation, 'Message 1');
        $message1->setCreatedAt(new \DateTimeImmutable('-2 days'));
        
        $message2 = $this->createTestMessage($author, $conversation, 'Message 2');
        $message2->setCreatedAt(new \DateTimeImmutable('-1 day'));
        
        $this->entityManager->flush();

        // Test order by ID DESC
        $messagesDesc = $this->repository->findBy([], ['id' => 'DESC']);
        $this->assertGreaterThanOrEqual(2, count($messagesDesc));
        
        // Vérifier que le message avec l'ID le plus grand est en premier
        if (count($messagesDesc) >= 2) {
            $this->assertGreaterThan($messagesDesc[1]->getId(), $messagesDesc[0]->getId());
        }

        // Test order by created_at
        $messagesByDate = $this->repository->findBy([], ['createdAt' => 'DESC']);
        $this->assertGreaterThanOrEqual(2, count($messagesByDate));

        // Cleanup
        $this->entityManager->remove($message1);
        $this->entityManager->remove($message2);
        $this->entityManager->flush();
    }

    /**
     * Test spécifique pour rechercher par contenu
     */
    public function testFindByContent(): void
    {
        $uniqueContent = 'Unique content ' . uniqid();
        $message = $this->createTestMessage(null, null, $uniqueContent);
        $this->entityManager->flush();

        $foundMessages = $this->repository->findBy(['content' => $uniqueContent]);
        
        $this->assertCount(1, $foundMessages);
        $this->assertEquals($uniqueContent, $foundMessages[0]->getContent());

        // Cleanup
        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }

    /**
     * Test pour vérifier la relation avec l'utilisateur
     */
    public function testMessageUserRelation(): void
    {
        $user = $this->createTestUser('relation_test@example.com');
        $message = $this->createTestMessage($user, null, 'Test relation');
        $this->entityManager->flush();

        $foundMessage = $this->repository->find($message->getId());
        
        $this->assertNotNull($foundMessage);
        $this->assertInstanceOf(User::class, $foundMessage->getAuthor());
        $this->assertEquals('relation_test@example.com', $foundMessage->getAuthor()->getEmail());

        // Cleanup
        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }
}