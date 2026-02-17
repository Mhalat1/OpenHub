<?php

namespace App\Tests\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConversationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ConversationRepository $conversationRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->conversationRepository = $this->entityManager->getRepository(Conversation::class);
    }

    public function testFindAllReturnsArray(): void
    {
        $conversations = $this->conversationRepository->findAll();
        
        $this->assertIsArray($conversations);
        $this->assertContainsOnlyInstancesOf(Conversation::class, $conversations);
    }

    public function testFindReturnsNullForInvalidId(): void
    {
        $conversation = $this->conversationRepository->find(99999);
        
        $this->assertNull($conversation);
    }


}