<?php

namespace App\Tests\Repository;

use App\Entity\Invitations;
use App\Repository\InvitationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class InvitationsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private InvitationsRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        
        $this->repository = $this->entityManager->getRepository(Invitations::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testRepositoryIsInstanceOfInvitationsRepository(): void
    {
        $this->assertInstanceOf(InvitationsRepository::class, $this->repository);
    }

    public function testFindAll(): void
    {
        $invitations = $this->repository->findAll();
        $this->assertIsArray($invitations);
    }

    public function testFindOneBy(): void
    {
        // Create a test invitation
        $invitation = new Invitations();
        // Set required fields here based on your entity
        // $invitation->setEmail('test@example.com');
        // $invitation->setToken('test-token');
        
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $foundInvitation = $this->repository->findOneBy(['id' => $invitation->getId()]);
        
        $this->assertNotNull($foundInvitation);
        $this->assertInstanceOf(Invitations::class, $foundInvitation);
        $this->assertEquals($invitation->getId(), $foundInvitation->getId());

        // Cleanup
        $this->entityManager->remove($invitation);
        $this->entityManager->flush();
    }

    public function testFindBy(): void
    {
        // Create test invitations
        $invitation1 = new Invitations();
        $invitation2 = new Invitations();
        // Set required fields
        
        $this->entityManager->persist($invitation1);
        $this->entityManager->persist($invitation2);
        $this->entityManager->flush();

        $invitations = $this->repository->findBy([], ['id' => 'ASC']);
        
        $this->assertIsArray($invitations);
        $this->assertGreaterThanOrEqual(2, count($invitations));

        // Cleanup
        $this->entityManager->remove($invitation1);
        $this->entityManager->remove($invitation2);
        $this->entityManager->flush();
    }

    public function testCount(): void
    {
        $count = $this->repository->count([]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testFindOneByReturnsNullWhenNotFound(): void
    {
        $invitation = $this->repository->findOneBy(['id' => 999999]);
        $this->assertNull($invitation);
    }
}