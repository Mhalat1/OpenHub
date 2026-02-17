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
        
        // Créer des mocks avec PHPUnit
        $this->author = $this->createMock(User::class);
        $this->conversation = $this->createMock(Conversation::class);
    }

    public function testEntityCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Message::class, $this->message);
    }

    public function testIdIsInitiallyNull(): void
    {
        $this->assertNull($this->message->getId());
    }

    public function testGetSetContent(): void
    {
        $content = 'Ceci est un message de test';
        
        $this->assertNull($this->message->getContent());
        
        $this->message->setContent($content);
        $this->assertEquals($content, $this->message->getContent());
    }

    public function testGetSetAuthor(): void
    {
        $this->assertNull($this->message->getAuthor());
        
        $this->message->setAuthor($this->author);
        $this->assertSame($this->author, $this->message->getAuthor());
    }

    public function testGetSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        
        $this->assertNull($this->message->getCreatedAt());
        
        $this->message->setCreatedAt($date);
        $this->assertSame($date, $this->message->getCreatedAt());
    }

    public function testGetSetConversation(): void
    {
        $this->assertNull($this->message->getConversation());
        
        $this->message->setConversation($this->conversation);
        $this->assertSame($this->conversation, $this->message->getConversation());
    }

    public function testGetAuthorNameWithValidAuthor(): void
    {
        // Créer un vrai User au lieu d'un mock
        $realAuthor = new User();
        $realAuthor->setFirstName('Jean');
        $realAuthor->setLastName('Dupont');
        
        $this->message->setAuthor($realAuthor);
        
        $this->assertEquals('Jean Dupont', $this->message->getAuthorName());
    }

    public function testGetAuthorNameWithNullAuthor(): void
    {
        $this->assertNull($this->message->getAuthor());
        $this->assertNull($this->message->getAuthorName());
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

    public function testGetConversationTitleWithNullConversation(): void
    {
        $this->assertNull($this->message->getConversation());
        $this->assertNull($this->message->getConversationTitle());
    }

    public function testGetConversationTitleWithNullTitle(): void
    {
        $realConversation = new Conversation();
        $realConversation->setTitle(null);
        
        $this->message->setConversation($realConversation);
        
        $this->assertNull($this->message->getConversationTitle());
    }

    public function testFluentInterfaces(): void
    {
        $date = new \DateTimeImmutable();
        $author = new User();
        $conversation = new Conversation();

        // Test fluent interface pour setContent
        $result = $this->message->setContent('Test');
        $this->assertSame($this->message, $result);

        // Test fluent interface pour setAuthor
        $result = $this->message->setAuthor($author);
        $this->assertSame($this->message, $result);

        // Test fluent interface pour setCreatedAt
        $result = $this->message->setCreatedAt($date);
        $this->assertSame($this->message, $result);

        // Test fluent interface pour setConversation
        $result = $this->message->setConversation($conversation);
        $this->assertSame($this->message, $result);
    }

    public function testFullMessageCreation(): void
    {
        $content = 'Message important';
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        
        // Créer de vraies entités
        $realAuthor = new User();
        $realAuthor->setFirstName('Jean');
        $realAuthor->setLastName('Dupont');
        
        $realConversation = new Conversation();
        $realConversation->setTitle('Discussion');

        $this->message->setContent($content)
                      ->setAuthor($realAuthor)
                      ->setCreatedAt($date)
                      ->setConversation($realConversation);

        // Vérifications
        $this->assertEquals($content, $this->message->getContent());
        $this->assertSame($realAuthor, $this->message->getAuthor());
        $this->assertSame($date, $this->message->getCreatedAt());
        $this->assertSame($realConversation, $this->message->getConversation());
        $this->assertEquals('Jean Dupont', $this->message->getAuthorName());
        $this->assertEquals('Discussion', $this->message->getConversationTitle());
    }

    /**
     * Teste la cohérence des relations bidirectionnelles
     */
    public function testBidirectionalRelationships(): void
    {
        // Créer une vraie conversation pour tester la relation
        $realConversation = new Conversation();
        
        $this->message->setConversation($realConversation);
        
        // Vérifier que la conversation n'a pas encore le message
        $this->assertCount(0, $realConversation->getMessages());
        
        // Simuler l'ajout du message à la conversation (c'est la responsabilité de Conversation::addMessage)
        $realConversation->addMessage($this->message);
        
        // Vérifier que la relation est maintenant bidirectionnelle
        $this->assertCount(1, $realConversation->getMessages());
        $this->assertSame($this->message, $realConversation->getMessages()->first());
        $this->assertSame($realConversation, $this->message->getConversation());
    }

    /**
     * Teste que le message a bien les attributs Doctrine nécessaires
     */
    public function testDoctrineAttributes(): void
    {
        $reflection = new \ReflectionClass($this->message);
        
        // Vérifier les propriétés principales
        $properties = ['id', 'content', 'author', 'createdAt', 'conversation'];
        
        foreach ($properties as $propertyName) {
            $this->assertTrue($reflection->hasProperty($propertyName), "Property $propertyName should exist");
        }
        
        // Vérifier plus spécifiquement pour la propriété author (qui avait un problème)
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
        
        // Vérifier que la propriété author a JoinColumn
        $hasJoinColumnAttribute = false;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\JoinColumn') {
                $hasJoinColumnAttribute = true;
                break;
            }
        }
        
        $this->assertTrue($hasJoinColumnAttribute, "author property should have JoinColumn attribute");
    }

    /**
     * Teste que le constructeur n'est pas nécessaire (pas de logique dans le constructeur)
     */
    public function testNoConstructorLogic(): void
    {
        $message = new Message();
        
        // Vérifier que toutes les propriétés sont null après instanciation
        $this->assertNull($message->getId());
        $this->assertNull($message->getContent());
        $this->assertNull($message->getAuthor());
        $this->assertNull($message->getCreatedAt());
        $this->assertNull($message->getConversation());
    }
}