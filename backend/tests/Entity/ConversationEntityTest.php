<?php

namespace App\Tests\Entity;

use App\Entity\Conversation;
use App\Entity\User;
use App\Entity\Message;
use PHPUnit\Framework\TestCase;
use Doctrine\Common\Collections\Collection;

class ConversationEntityTest extends TestCase
{
    private Conversation $conversation;

    protected function setUp(): void
    {
        $this->conversation = new Conversation();
    }

    public function testConstructorInitializesCollectionsAndDates(): void
    {
        $this->assertInstanceOf(Collection::class, $this->conversation->getUsers());
        $this->assertInstanceOf(Collection::class, $this->conversation->getMessages());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->conversation->getCreatedAt());
        $this->assertCount(0, $this->conversation->getUsers());
        $this->assertCount(0, $this->conversation->getMessages());
    }

    public function testGetSetTitle(): void
    {
        $title = 'Test Conversation';

        $this->conversation->setTitle($title);
        $this->assertEquals($title, $this->conversation->getTitle());
    }

    public function testGetSetDescription(): void
    {
        $description = 'This is a test conversation';

        $this->conversation->setDescription($description);
        $this->assertEquals($description, $this->conversation->getDescription());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->conversation->setCreatedAt($date);
        $this->assertEquals($date, $this->conversation->getCreatedAt());
    }

    public function testPrePersistSetsCreatedAt(): void
    {
        $conversation = new Conversation();

        $reflection = new \ReflectionClass($conversation);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($conversation, null);

        $this->assertNull($conversation->getCreatedAt());

        $conversation->onPrePersist();

        $this->assertInstanceOf(\DateTimeImmutable::class, $conversation->getCreatedAt());
    }

    public function testGetSetLastMessageAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->assertNull($this->conversation->getLastMessageAt());

        $this->conversation->setLastMessageAt($date);
        $this->assertEquals($date, $this->conversation->getLastMessageAt());
    }

    public function testGetSetCreatedBy(): void
    {
        $user = new User();
        $this->assertNull($this->conversation->getCreatedBy());

        $this->conversation->setCreatedBy($user);
        $this->assertEquals($user, $this->conversation->getCreatedBy());
    }

    public function testAddUser(): void
    {
        $user1 = new User();
        $user2 = new User();

        $this->conversation->addUser($user1);
        $this->assertCount(1, $this->conversation->getUsers());
        $this->assertTrue($this->conversation->hasUser($user1));

        $this->conversation->addUser($user2);
        $this->assertCount(2, $this->conversation->getUsers());

        // Test ajout du même utilisateur (ne doit pas dupliquer)
        $this->conversation->addUser($user1);
        $this->assertCount(2, $this->conversation->getUsers());
    }

    public function testRemoveUser(): void
    {
        $user1 = new User();
        $user2 = new User();

        $this->conversation->addUser($user1);
        $this->conversation->addUser($user2);
        $this->assertCount(2, $this->conversation->getUsers());

        $this->conversation->removeUser($user1);
        $this->assertCount(1, $this->conversation->getUsers());
        $this->assertFalse($this->conversation->hasUser($user1));
        $this->assertTrue($this->conversation->hasUser($user2));
    }

    public function testHasUser(): void
    {
        $user = new User();

        $this->assertFalse($this->conversation->hasUser($user));

        $this->conversation->addUser($user);
        $this->assertTrue($this->conversation->hasUser($user));
    }

    public function testAddMessage(): void
    {
        $message = new Message();

        $this->conversation->addMessage($message);

        $this->assertCount(1, $this->conversation->getMessages());
        $this->assertSame($this->conversation, $message->getConversation());
        $this->assertNotNull($this->conversation->getLastMessageAt());
    }

    public function testAddMessageUpdatesLastMessageAt(): void
    {
        $oldDate = new \DateTimeImmutable('2024-01-01 12:00:00');
        $this->conversation->setLastMessageAt($oldDate);

        $message = new Message();

        usleep(1000);
        $this->conversation->addMessage($message);

        $this->assertNotEquals($oldDate, $this->conversation->getLastMessageAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->conversation->getLastMessageAt());
    }

    public function testGetLastMessageWithMessages(): void
    {
        $message1 = new Message();
        $message1->setCreatedAt(new \DateTimeImmutable('2024-01-01 12:00:00'));

        $message2 = new Message();
        $message2->setCreatedAt(new \DateTimeImmutable('2024-01-02 12:00:00'));

        $message3 = new Message();
        $message3->setCreatedAt(new \DateTimeImmutable('2024-01-03 12:00:00'));

        $this->conversation->addMessage($message1);
        $this->conversation->addMessage($message2);
        $this->conversation->addMessage($message3);

        $lastMessage = $this->conversation->getLastMessage();
        $this->assertSame($message3, $lastMessage);
    }

    public function testGetLastMessageWithEmptyMessages(): void
    {
        $this->assertNull($this->conversation->getLastMessage());
    }

    public function testGetDisplayTitleWithCustomTitle(): void
    {
        $currentUser = new User();
        $title = 'Mon Titre Personnalisé';

        $this->conversation->setTitle($title);

        $this->assertEquals($title, $this->conversation->getDisplayTitle($currentUser));
    }


    public function testGetSetConversationHash(): void
    {
        $hash = 'abc123def456';

        $this->assertNull($this->conversation->getConversationHash());

        $this->conversation->setConversationHash($hash);
        $this->assertEquals($hash, $this->conversation->getConversationHash());
    }

    public function testFluentInterfaces(): void
    {
        $user = new User();
        $message = new Message();
        $date = new \DateTimeImmutable();

        $result = $this->conversation->setTitle('Test');
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->setDescription('Description');
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->setCreatedAt($date);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->setLastMessageAt($date);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->setCreatedBy($user);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->addUser($user);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->removeUser($user);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->addMessage($message);
        $this->assertSame($this->conversation, $result);

        $result = $this->conversation->setConversationHash('hash');
        $this->assertSame($this->conversation, $result);
    }




public function testRemoveMessage(): void
{
    $message = new Message();

    $this->conversation->addMessage($message);

    $this->assertCount(1, $this->conversation->getMessages());
    $this->assertSame($this->conversation, $message->getConversation());

    $result = $this->conversation->removeMessage($message);

    // ✔ retour fluent
    $this->assertSame($this->conversation, $result);

    // ✔ message retiré
    $this->assertCount(0, $this->conversation->getMessages());
    $this->assertFalse($this->conversation->getMessages()->contains($message));

    // ⚠️ selon ton modèle : NE PAS tester null si non nullable
}

public function testRemoveMessageWhenNotInCollection(): void
{
    $message = new Message();

    $result = $this->conversation->removeMessage($message);

    $this->assertSame($this->conversation, $result);
    $this->assertCount(0, $this->conversation->getMessages());
}


public function testGetDisplayTitleWithTitle(): void
{
    $user = new User();

    $this->conversation->setTitle('Mon super chat');

    $this->assertSame(
        'Mon super chat',
        $this->conversation->getDisplayTitle($user)
    );
}

public function testGetDisplayTitle(): void
{
    $user = new User();

    // Cas 1 : titre personnalisé
    $this->conversation->setTitle('Mon salon privé');

    $this->assertSame(
        'Mon salon privé',
        $this->conversation->getDisplayTitle($user)
    );

    // Cas 2 : titre vide string (fallback)
    $this->conversation->setTitle('');

    $this->assertSame(
        'Conversation',
        $this->conversation->getDisplayTitle($user)
    );
}
}