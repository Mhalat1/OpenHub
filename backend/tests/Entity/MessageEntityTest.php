<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\User;
use App\Entity\Conversation;
use PHPUnit\Framework\TestCase;

class MessageEntityTest extends TestCase
{
    private Message $message;
    private User $author;
    private Conversation $conversation;

    protected function setUp(): void
    {
        $this->message = new Message();
        $this->author = $this->createMock(User::class);
        $this->conversation = $this->createMock(Conversation::class);
    }

    public function testEntityCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Message::class, $this->message);
    }

    public function testGetSetContent(): void
    {
        $content = 'Ceci est un message de test';

        $this->message->setContent($content);
        $this->assertEquals($content, $this->message->getContent());
    }

    public function testGetSetAuthor(): void
    {
        $this->message->setAuthor($this->author);
        $this->assertSame($this->author, $this->message->getAuthor());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');

        $this->message->setCreatedAt($date);
        $this->assertSame($date, $this->message->getCreatedAt());
    }

    public function testGetSetConversation(): void
    {
        $this->message->setConversation($this->conversation);
        $this->assertSame($this->conversation, $this->message->getConversation());
    }

    public function testGetAuthorNameWithValidAuthor(): void
    {
        $realAuthor = new User();
        $realAuthor->setFirstName('Jean');
        $realAuthor->setLastName('Dupont');

        $this->message->setAuthor($realAuthor);

        $this->assertEquals('Jean Dupont', $this->message->getAuthorName());
    }

    public function testGetAuthorNameWithEmptyNames(): void
    {
        $realAuthor = new User();
        $realAuthor->setFirstName('');
        $realAuthor->setLastName('');

        $this->message->setAuthor($realAuthor);

        $this->assertEquals(' ', $this->message->getAuthorName());
    }

    public function testGetAuthorNameWithOnlyFirstName(): void
    {
        $realAuthor = new User();
        $realAuthor->setFirstName('Jean');
        $realAuthor->setLastName('');

        $this->message->setAuthor($realAuthor);

        $this->assertEquals('Jean ', $this->message->getAuthorName());
    }

    public function testGetAuthorNameWithOnlyLastName(): void
    {
        $realAuthor = new User();
        $realAuthor->setFirstName('');
        $realAuthor->setLastName('Dupont');

        $this->message->setAuthor($realAuthor);

        $this->assertEquals(' Dupont', $this->message->getAuthorName());
    }

    public function testGetConversationTitleWithValidConversation(): void
    {
        $realConversation = new Conversation();
        $realConversation->setTitle('Conversation de test');

        $this->message->setConversation($realConversation);

        $this->assertEquals('Conversation de test', $this->message->getConversationTitle());
    }

    public function testFluentInterfaces(): void
    {
        $date = new \DateTimeImmutable();
        $author = new User();
        $conversation = new Conversation();

        $result = $this->message->setContent('Test');
        $this->assertSame($this->message, $result);

        $result = $this->message->setAuthor($author);
        $this->assertSame($this->message, $result);

        $result = $this->message->setCreatedAt($date);
        $this->assertSame($this->message, $result);

        $result = $this->message->setConversation($conversation);
        $this->assertSame($this->message, $result);
    }

    public function testFullMessageCreation(): void
    {
        $content = 'Message important';
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');

        $realAuthor = new User();
        $realAuthor->setFirstName('Jean');
        $realAuthor->setLastName('Dupont');

        $realConversation = new Conversation();
        $realConversation->setTitle('Discussion');

        $this->message->setContent($content)
                      ->setAuthor($realAuthor)
                      ->setCreatedAt($date)
                      ->setConversation($realConversation);

        $this->assertEquals($content, $this->message->getContent());
        $this->assertSame($realAuthor, $this->message->getAuthor());
        $this->assertSame($date, $this->message->getCreatedAt());
        $this->assertSame($realConversation, $this->message->getConversation());
        $this->assertEquals('Jean Dupont', $this->message->getAuthorName());
        $this->assertEquals('Discussion', $this->message->getConversationTitle());
    }

    public function testBidirectionalRelationships(): void
    {
        $realConversation = new Conversation();

        $this->message->setConversation($realConversation);

        $this->assertCount(0, $realConversation->getMessages());

        $realConversation->addMessage($this->message);

        $this->assertCount(1, $realConversation->getMessages());
        $this->assertSame($this->message, $realConversation->getMessages()->first());
        $this->assertSame($realConversation, $this->message->getConversation());
    }

    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass($this->message);

        $properties = ['id', 'content', 'author', 'createdAt', 'conversation'];

        foreach ($properties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName), "Property $propertyName should exist");
        }

        $authorProperty = $reflection->getProperty('author');
        $attributes = $authorProperty->getAttributes();

        $hasManyToOneAttribute = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\ManyToOne') {
                $hasManyToOneAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasManyToOneAttribute, "author property should have ManyToOne attribute");

        $hasJoinColumnAttribute = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\JoinColumn') {
                $hasJoinColumnAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasJoinColumnAttribute, "author property should have JoinColumn attribute");
    }
}