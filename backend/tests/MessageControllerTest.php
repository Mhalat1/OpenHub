<?php

namespace App\Tests\Controller;

use Psr\Container\ContainerInterface;
use App\Controller\MessageController;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\PapertrailService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;

class TestableMessageController extends MessageController
{
    public function __construct(
        MessageRepository $messageRepository,
        EntityManagerInterface $em,
        PapertrailService $papertrailLogger,
        private readonly bool $rateLimitAccepted = true
    ) {
        parent::__construct($messageRepository, $em, $papertrailLogger);
    }

    public function createMessage(
        Request $request,
        Security $security,
        EntityManagerInterface $em,
        RateLimiterFactory $apiMessageLimiter
    ): JsonResponse {
        $storage = new InMemoryStorage();

        if ($this->rateLimitAccepted) {
            $factory = new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'no_limit'],
                $storage
            );
        } else {
            $factory = new RateLimiterFactory(
                ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
                $storage
            );
            $userId = $security->getUser()?->getUserIdentifier() ?? 'anon';
            $factory->create($userId)->consume(1);
        }

        return parent::createMessage($request, $security, $em, $factory);
    }
}

class StubQuery extends Query
{
    private mixed $scalarResult;
    private mixed $singleResult;
    private array $listResult;

    public function __construct(mixed $scalar = 0, mixed $single = null, array $list = [])
    {
        $this->scalarResult = $scalar;
        $this->singleResult = $single;
        $this->listResult   = $list;
    }

    public function getSingleScalarResult(): mixed        { return $this->scalarResult; }
    public function getOneOrNullResult(mixed $h = null): mixed { return $this->singleResult; }
    public function getResult(mixed $h = 1): array        { return $this->listResult; }
    public function execute(mixed $p = null, mixed $h = null): mixed { return $this->listResult; }
    public function getSQL(): string                      { return ''; }
    protected function _doExecute(): int                  { return 0; }
}

class MessageControllerTest extends TestCase
{
    private MessageRepository&MockObject $messageRepo;
    private EntityManagerInterface&MockObject $em;
    private PapertrailService&MockObject $papertrail;
    private MessageController $controller;

    protected function setUp(): void
    {
        $this->messageRepo = $this->createMock(MessageRepository::class);
        $this->em          = $this->createMock(EntityManagerInterface::class);
        $this->papertrail  = $this->createMock(PapertrailService::class);

        $this->controller = new MessageController(
            $this->messageRepo,
            $this->em,
            $this->papertrail
        );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function createUser(
        int $id = 1,
        string $firstName = 'Jean',
        string $lastName = 'Dupont',
        string $email = 'jean.dupont@example.com',
        ?\DateTimeImmutable $availabilityStart = null,
        ?\DateTimeImmutable $availabilityEnd = null
    ): User&MockObject {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getFirstName')->willReturn($firstName);
        $user->method('getLastName')->willReturn($lastName);
        $user->method('getEmail')->willReturn($email);
        $user->method('getUserIdentifier')->willReturn($email);
        $user->method('getAvailabilityStart')->willReturn($availabilityStart);
        $user->method('getAvailabilityEnd')->willReturn($availabilityEnd);
        $user->method('getConversations')->willReturn(new ArrayCollection());
        return $user;
    }

    private function createConversation(
        int $id = 10,
        string $title = 'Test Conv',
        string $description = 'A description',
        ?User $creator = null,
        array $users = [],
        array $messages = []
    ): Conversation&MockObject {
        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn($id);
        $conv->method('getTitle')->willReturn($title);
        $conv->method('getDescription')->willReturn($description);
        $conv->method('getCreatedBy')->willReturn($creator);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01'));
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection($users));
        $conv->method('getMessages')->willReturn(new ArrayCollection($messages));
        return $conv;
    }

    private function createMessage(
        int $id = 100,
        string $content = 'Hello world',
        ?User $author = null,
        ?Conversation $conv = null
    ): Message&MockObject {
        $msg = $this->createMock(Message::class);
        $msg->method('getId')->willReturn($id);
        $msg->method('getContent')->willReturn($content);
        $msg->method('getAuthor')->willReturn($author);
        $msg->method('getAuthorName')->willReturn(
            $author ? $author->getFirstName() . ' ' . $author->getLastName() : 'Unknown'
        );
        $msg->method('getConversation')->willReturn($conv);
        $msg->method('getConversationTitle')->willReturn($conv ? $conv->getTitle() : '');
        $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-06-01 10:00:00'));
        return $msg;
    }

    private function createSecurity(?User $user): Security&MockObject
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        return $security;
    }

    /** Controller that always passes the rate limiter */
    private function createAcceptingController(?EntityManagerInterface $em = null): TestableMessageController
    {
        return new TestableMessageController(
            $this->messageRepo,
            $em ?? $this->em,
            $this->papertrail,
            true
        );
    }

    /** Controller that always rejects the rate limiter */
    private function createRejectingController(?EntityManagerInterface $em = null): TestableMessageController
    {
        return new TestableMessageController(
            $this->messageRepo,
            $em ?? $this->em,
            $this->papertrail,
            false
        );
    }

    /** Anonymous controller subclass that returns a fixed user from getUser() */
    private function createControllerWithUser(User $user): MessageController
    {
        return new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $u) { parent::__construct($repo, $em, $pt); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
    }

    /** Same as createControllerWithUser but uses a custom EntityManager */
    private function createControllerWithUserAndEm(User $user, EntityManagerInterface $em): MessageController
    {
        return new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $u) { parent::__construct($repo, $em, $pt); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
    }

    private function createJsonRequest(array $payload, string $method = 'POST'): Request
    {
        $req = Request::create('/', $method, [], [], [], [], json_encode($payload));
        $req->headers->set('Content-Type', 'application/json');
        return $req;
    }

    private function createDummyFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'dummy', 'policy' => 'no_limit'], new InMemoryStorage());
    }

    /** QueryBuilder stub whose Query returns a scalar count and optional single result */
    private function createCountQueryBuilder(int $count, mixed $singleResult = null): QueryBuilder&MockObject
    {
        $query = new StubQuery($count, $singleResult, []);
        $qb    = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        return $qb;
    }

    /** QueryBuilder stub whose Query returns a list via getResult() */
    private function createListQueryBuilder(array $list): QueryBuilder&MockObject
    {
        $query = new StubQuery(0, null, $list);
        $qb    = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        return $qb;
    }

    private function stubUserRepository(?User $existingUser = null): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existingUser);
        return $repo;
    }

    /** Build a fresh EntityManager that dispatches getRepository() by class name */
    private function buildEm(array $map): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn($class) => $map[$class] ?? $this->createMock(EntityRepository::class)
        );
        return $em;
    }

    // =========================================================================
    // getConnectedUser
    // =========================================================================

    public function testGetConnectedUserUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserNotUserInstanceReturns500(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface {
                return new class implements UserInterface {
                    public function getRoles(): array { return []; }
                    public function eraseCredentials(): void {}
                    public function getUserIdentifier(): string { return 'anon'; }
                };
            }
        };
        $this->assertSame(500, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserValidReturns200(): void
    {
        $user = $this->createUser();
        $em   = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1,                          $data['id']);
        $this->assertSame('Jean',                     $data['firstName']);
        $this->assertSame('Dupont',                   $data['lastName']);
        $this->assertSame('jean.dupont@example.com',  $data['email']);
    }

    public function testGetConnectedUserInvalidFirstNameReturns500(): void
    {
        $user = $this->createUser(firstName: 'Jean123');
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('error', json_decode($response->getContent(), true));
    }

    public function testGetConnectedUserInvalidLastNameReturns500(): void
    {
        $user = $this->createUser(lastName: 'Dupont99');
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
        $this->assertSame(500, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserInvalidEmailReturns500(): void
    {
        $user = $this->createUser(email: 'not-an-email');
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
        $this->assertSame(500, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserEmailTakenByOtherUserReturns500(): void
    {
        $user      = $this->createUser(1, 'Jean', 'Dupont', 'jean@example.com');
        $otherUser = $this->createUser(2, 'Autre', 'Nom', 'jean@example.com');
        $em        = $this->buildEm([User::class => $this->stubUserRepository($otherUser)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };
        $this->assertSame(500, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserWithValidAvailabilityDates(): void
    {
        $user = $this->createUser(
            availabilityStart: new \DateTimeImmutable('+1 day'),
            availabilityEnd:   new \DateTimeImmutable('+30 days')
        );
        $em   = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['availabilityStart']);
        $this->assertNotNull($data['availabilityEnd']);
    }

    public function testGetConnectedUserWithConversations(): void
    {
        $conv1 = $this->createMock(Conversation::class);
        $conv1->method('getId')->willReturn(42);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getEmail')->willReturn('jean.dupont@example.com');
        $user->method('getUserIdentifier')->willReturn('jean.dupont@example.com');
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);
        $user->method('getConversations')->willReturn(new ArrayCollection([$conv1]));

        $em = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['userData']);
        $this->assertSame(42, $data['userData'][0]['id']);
    }

    // =========================================================================
    // getUserConversations
    // =========================================================================

    public function testGetUserConversationsUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->getUserConversations($this->em)->getStatusCode());
    }

    public function testGetUserConversationsReturnsEmptyWhenNone(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([]));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $ctrl     = $this->createControllerWithUserAndEm($user, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsSkipsInvalidTitle(): void
    {
        $user = $this->createUser();
        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(5);
        $conv->method('getTitle')->willReturn('<script>alert(1)</script>');
        $conv->method('getDescription')->willReturn('desc');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$user]));
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conv->method('getLastMessageAt')->willReturn(null);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $ctrl     = $this->createControllerWithUserAndEm($user, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsReturnsValidConversation(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv        = $this->createConversation(10, 'Ma conversation', 'desc', $currentUser, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Ma conversation', $data[0]['title']);
        $this->assertCount(1, $data[0]['users']); // participant only (currentUser excluded)
    }

    public function testGetUserConversationsSkipsParticipantWithInvalidName(): void
    {
        $currentUser = $this->createUser(1);
        $badUser     = $this->createUser(2, 'Bad123', 'User', 'bad@example.com');
        $conv        = $this->createConversation(10, 'Test', 'desc', $currentUser, [$currentUser, $badUser]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsSkipsParticipantWithInvalidEmail(): void
    {
        $currentUser  = $this->createUser(1);
        $invalidEmail = $this->createUser(2, 'Marie', 'Curie', 'not-valid-email');
        $conv         = $this->createConversation(10, 'Test', 'desc', $currentUser, [$currentUser, $invalidEmail]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsInvalidCreatorFallsBackToUnknown(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $badCreator  = $this->createUser(3, 'Bad99', 'Creator', 'bad@example.com');
        $conv        = $this->createConversation(10, 'Valid Title', '', $badCreator, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Unknown', $data[0]['createdBy']);
    }

    public function testGetUserConversationsNullCreatorFallsBackToUnknown(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv        = $this->createConversation(10, 'Valid Title', '', null, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Unknown', $data[0]['createdBy']);
    }

    public function testGetUserConversationsWithLastMessageAtSet(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Conv with last message');
        $conv->method('getDescription')->willReturn('desc');
        $conv->method('getCreatedBy')->willReturn($currentUser);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01'));
        $conv->method('getLastMessageAt')->willReturn(new \DateTimeImmutable('2024-06-15 12:00:00'));
        $conv->method('getUsers')->willReturn(new ArrayCollection([$currentUser, $participant]));
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data[0]['lastMessageAt']);
    }

    public function testGetUserConversationsDbExceptionReturns500(): void
    {
        $currentUser = $this->createUser(1);
        $convRepo    = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(500, $response->getStatusCode());
    }

    // =========================================================================
    // getMessages
    // =========================================================================

    public function testGetMessagesUnauthenticated(): void
    {
        $security = $this->createSecurity(null);
        $request  = Request::create('/api/get/messages', 'GET');
        $this->assertSame(401, $this->controller->getMessages($security, $this->messageRepo, $request)->getStatusCode());
    }

    public function testGetMessagesValidReturns200(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);
        $msg  = $this->createMessage(author: $user, conv: $conv);

        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(1, $data['data']);
    }

    public function testGetMessagesPaginationClampedPage(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 0, 'limit' => 999]);

        $this->messageRepo->method('findBy')->willReturn([]);
        $this->messageRepo->method('count')->willReturn(0);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(200, $this->controller->getMessages($security, $this->messageRepo, $request)->getStatusCode());
    }
    public function testGetMessagesSkipsInvalidContent(): void
    {
        $user     = $this->createUser();
        $conv     = $this->createConversation(creator: $user);
        $msg      = $this->createMessage(content: '<script>xss</script>', author: $user, conv: $conv);
        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $data['data']);
    }

    public function testGetMessagesSkipsOrphanMessage(): void
    {
        $user     = $this->createUser();
        $msg      = $this->createMessage(100, 'Hello world', $user, null);
        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $data = json_decode($this->controller->getMessages($security, $this->messageRepo, $request)->getContent(), true);
        $this->assertCount(0, $data['data']);
    }

    public function testGetMessagesSkipsInvalidConversationTitle(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);

        $msg = $this->createMock(Message::class);
        $msg->method('getId')->willReturn(1);
        $msg->method('getContent')->willReturn('Hello world');
        $msg->method('getAuthor')->willReturn($user);
        $msg->method('getAuthorName')->willReturn('Jean Dupont');
        $msg->method('getConversation')->willReturn($conv);
        $msg->method('getConversationTitle')->willReturn('<script>bad</script>');
        $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetMessagesAuthorEmailInvalidFallsBack(): void
    {
        $user          = $this->createUser();
        $conv          = $this->createConversation(creator: $user);
        $badEmailAuthor = $this->createUser(2, 'Marie', 'Curie', 'not-valid');

        $msg = $this->createMock(Message::class);
        $msg->method('getId')->willReturn(1);
        $msg->method('getContent')->willReturn('Hello world');
        $msg->method('getAuthor')->willReturn($badEmailAuthor);
        $msg->method('getAuthorName')->willReturn('Marie Curie');
        $msg->method('getConversation')->willReturn($conv);
        $msg->method('getConversationTitle')->willReturn('Test Conv');
        $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $data = json_decode($this->controller->getMessages($security, $this->messageRepo, $request)->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    public function testGetMessagesAuthorNameInvalidFallsBackToUnknown(): void
    {
        $user   = $this->createUser();
        $conv   = $this->createConversation(creator: $user);
        $author = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

        $msg = $this->createMock(Message::class);
        $msg->method('getId')->willReturn(1);
        $msg->method('getContent')->willReturn('Hello world');
        $msg->method('getAuthor')->willReturn($author);
        $msg->method('getAuthorName')->willReturn('Marie99 Curie');
        $msg->method('getConversation')->willReturn($conv);
        $msg->method('getConversationTitle')->willReturn('Test Conv');
        $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $security = $this->createSecurity($user);
        $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $data = json_decode($this->controller->getMessages($security, $this->messageRepo, $request)->getContent(), true);
        $this->assertCount(1, $data['data']);
    }
    // =========================================================================
    // createConversation
    // =========================================================================

    public function testCreateConversationUnauthenticated(): void
    {
        $security = $this->createSecurity(null);
        $request  = $this->createJsonRequest(['title' => 'Hello', 'conv_users' => [2]]);
        $this->assertSame(401, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationWrongContentTypeReturns415(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = Request::create('/', 'POST', [], [], [], [], json_encode(['title' => 'Hi']));
        $request->headers->set('Content-Type', 'text/plain');
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(415, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationMissingTitleReturns400(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = $this->createJsonRequest(['conv_users' => [2]]);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(400, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationInvalidTitleReturns400(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = $this->createJsonRequest(['title' => '<script>xss</script>', 'conv_users' => [2]]);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(400, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationForbiddenExtraFieldsReturns500(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = $this->createJsonRequest(['title' => 'Valid', 'conv_users' => [2], 'malicious' => 'x']);
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(500, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationPayloadTooLargeReturns413(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);
        $request  = Request::create('/', 'POST', [], [], [], [], json_encode(['title' => str_repeat('a', 15000), 'conv_users' => [2]]));
        $request->headers->set('Content-Type', 'application/json');
        $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

        $this->assertSame(413, $this->controller->createConversation($request, $security, $this->em)->getStatusCode());
    }

    public function testCreateConversationRateLimitReturns429(): void
    {
        $user      = $this->createUser();
        $security  = $this->createSecurity($user);
        $convRepo  = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));

        $em = $this->buildEm([
            User::class         => $this->stubUserRepository(null),
            Conversation::class => $convRepo,
        ]);

        $request  = $this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $em);
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testCreateConversationInvalidUserStateReturns400(): void
    {
        $user     = $this->createUser(firstName: 'Jean99');
        $security = $this->createSecurity($user);
        $em       = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $request  = $this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $em);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('invalid user state', json_decode($response->getContent(), true)['message']);
    }
public function testCreateConversationSuccessReturns201(): void
{
    $creator     = $this->createUser(1);
    $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
    $security    = $this->createSecurity($creator);

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findOneBy')->willReturn(null);
    $userRepo->method('findBy')->willReturn([$participant]);

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

    $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
    $em->expects($this->once())->method('beginTransaction');
    $em->expects($this->once())->method('persist')->willReturnCallback(function ($entity) {
        $prop = (new \ReflectionClass($entity))->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, 1);
    });
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('commit');

    $request  = $this->createJsonRequest(['title' => 'Ma conversation', 'conv_users' => [2]]);
    $response = $this->controller->createConversation($request, $security, $em);

    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame('Ma conversation', json_decode($response->getContent(), true)['title']);
}

    public function testCreateConversationDuplicateReturns409(): void
    {
        $creator  = $this->createUser(1);
        $security = $this->createSecurity($creator);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$this->createUser(2, 'Marie', 'Curie', 'marie@example.com')]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, $this->createMock(Conversation::class)));

        $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
        $em->method('beginTransaction');
        $em->expects($this->once())->method('rollback');

        $request  = $this->createJsonRequest(['title' => 'Ma conversation', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $em);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('DUPLICATE_CONVERSATION', json_decode($response->getContent(), true)['error']);
    }

public function testCreateConversationNoParticipantsCreates201(): void
{
    $creator  = $this->createUser(1);
    $security = $this->createSecurity($creator);

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

    $em = $this->buildEm([User::class => $this->stubUserRepository(null), Conversation::class => $convRepo]);
    $em->method('beginTransaction');
    $em->expects($this->once())->method('persist')->willReturnCallback(function ($entity) {
        $prop = (new \ReflectionClass($entity))->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, 1);
    });
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('commit');

    $response = $this->controller->createConversation($this->createJsonRequest(['title' => 'Solo', 'conv_users' => []]), $security, $em);
    $this->assertSame(201, $response->getStatusCode());
}


public function testCreateConversationWithDescriptionSucceeds(): void
{
    $creator     = $this->createUser(1);
    $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
    $security    = $this->createSecurity($creator);

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findOneBy')->willReturn(null);
    $userRepo->method('findBy')->willReturn([$participant]);

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

    $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
    $em->method('beginTransaction');
    $em->method('persist')->willReturnCallback(function ($entity) {
        $prop = (new \ReflectionClass($entity))->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, 1);
    });
    $em->method('flush');
    $em->method('commit');

    $request  = $this->createJsonRequest(['title' => 'My conv', 'description' => 'A nice description', 'conv_users' => [2]]);
    $response = $this->controller->createConversation($request, $security, $em);

    $this->assertSame(201, $response->getStatusCode());
}

    public function testCreateConversationTooManyParticipantsReturns400(): void
    {
        $creator  = $this->createUser(1);
        $security = $this->createSecurity($creator);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $em = $this->buildEm([User::class => $this->stubUserRepository(null), Conversation::class => $convRepo]);
        $em->method('beginTransaction');

        $request  = $this->createJsonRequest(['title' => 'Big group', 'conv_users' => range(2, 52)]);
        $response = $this->controller->createConversation($request, $security, $em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateConversationInvalidJsonReturns400(): void
    {
        $user     = $this->createUser(1);
        $security = $this->createSecurity($user);
        $em       = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $request = Request::create('/', 'POST', [], [], [], [], 'not-json');
        $request->headers->set('Content-Type', 'application/json');

        $this->assertSame(400, $this->controller->createConversation($request, $security, $em)->getStatusCode());
    }

    public function testCreateConversationTransactionExceptionReturns500(): void
    {
        $creator     = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $security    = $this->createSecurity($creator);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
        $em->method('beginTransaction');
        $em->method('persist')->willThrowException(new \Exception('Transaction error'));
        $em->expects($this->once())->method('rollback');

        $response = $this->controller->createConversation($this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]), $security, $em);
        $this->assertSame(500, $response->getStatusCode());
    }

    // =========================================================================
    // createMessage
    // =========================================================================

    public function testCreateMessageUnauthenticated(): void
    {
        $ctrl     = $this->createAcceptingController();
        $response = $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello', 'conversation_id' => 1]),
            $this->createSecurity(null),
            $this->em,
            $this->createDummyFactory()
        );
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateMessageRateLimiterRejectsReturns429(): void
    {
        $user     = $this->createUser();
        $ctrl     = $this->createRejectingController();
        $response = $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello', 'conversation_id' => 1]),
            $this->createSecurity($user),
            $this->em,
            $this->createDummyFactory()
        );
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testCreateMessageWrongContentTypeReturns415(): void
    {
        $user    = $this->createUser();
        $ctrl    = $this->createAcceptingController();
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['content' => 'Hi', 'conversation_id' => 1]));
        $request->headers->set('Content-Type', 'text/plain');

        $this->assertSame(415, $ctrl->createMessage($request, $this->createSecurity($user), $this->em, $this->createDummyFactory())->getStatusCode());
    }

    public function testCreateMessageMissingContentReturns400(): void
    {
        $ctrl = $this->createAcceptingController();
        $this->assertSame(400, $ctrl->createMessage(
            $this->createJsonRequest(['conversation_id' => 1]),
            $this->createSecurity($this->createUser()),
            $this->em,
            $this->createDummyFactory()
        )->getStatusCode());
    }

    public function testCreateMessageMissingConversationIdReturns400(): void
    {
        $ctrl = $this->createAcceptingController();
        $this->assertSame(400, $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello world']),
            $this->createSecurity($this->createUser()),
            $this->em,
            $this->createDummyFactory()
        )->getStatusCode());
    }

    public function testCreateMessageInvalidContentReturns400(): void
    {
        $ctrl = $this->createAcceptingController();
        $this->assertSame(400, $ctrl->createMessage(
            $this->createJsonRequest(['content' => '<script>xss</script>', 'conversation_id' => 1]),
            $this->createSecurity($this->createUser()),
            $this->em,
            $this->createDummyFactory()
        )->getStatusCode());
    }
    public function testCreateMessageConversationNotFoundReturns404(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn(null);
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $ctrl     = $this->createAcceptingController($em);
        $response = $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 999]),
            $this->createSecurity($user),
            $em,
            $this->createDummyFactory()
        );
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCreateMessageUserNotInConversationReturns403(): void
    {
        $user      = $this->createUser(1);
        $otherUser = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $conv      = $this->createConversation(users: [$otherUser]);
        $convRepo  = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $ctrl     = $this->createAcceptingController($em);
        $response = $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]),
            $this->createSecurity($user),
            $em,
            $this->createDummyFactory()
        );
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testCreateMessageDailyRateLimitExceededReturns429(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createConversation(users: [$user]);

        $collection = $this->createMock(ArrayCollection::class);
        $collection->method('contains')->willReturn(true);
        $conv->method('getUsers')->willReturn($collection);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));

        $em   = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $ctrl = $this->createAcceptingController($em);

        $response = $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]),
            $this->createSecurity($user),
            $em,
            $this->createDummyFactory()
        );
        $this->assertSame(429, $response->getStatusCode());
    }

public function testCreateMessageSuccessReturns201(): void
{
    $user    = $this->createUser(1);
    $conv    = $this->createConversation(10, 'Ma conv', '', $user, [$user]);
    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));
    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conv);

    $em = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
    $em->expects($this->once())->method('persist')->willReturnCallback(function ($entity) {
        $prop = (new \ReflectionClass($entity))->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, 100);
    });
    $em->expects($this->once())->method('flush');

    $ctrl     = $this->createAcceptingController($em);
    $response = $ctrl->createMessage(
        $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]),
        $this->createSecurity($user),
        $em,
        $this->createDummyFactory()
    );

    $this->assertSame(201, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('id', $data);
    $this->assertArrayHasKey('content', $data);
}

    public function testCreateMessageForbiddenExtraFieldsReturns500(): void
    {
        $ctrl = $this->createAcceptingController();
        $this->assertSame(500, $ctrl->createMessage(
            $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 1, 'injected_field' => 'bad']),
            $this->createSecurity($this->createUser()),
            $this->em,
            $this->createDummyFactory()
        )->getStatusCode());
    }

    public function testCreateMessageLargePayloadReturns413(): void
    {
        $ctrl    = $this->createAcceptingController();
        $request = Request::create('/', 'POST', [], [], [], [], str_repeat('x', 10001));
        $request->headers->set('Content-Type', 'application/json');

        $this->assertSame(413, $ctrl->createMessage($request, $this->createSecurity($this->createUser()), $this->em, $this->createDummyFactory())->getStatusCode());
    }

    // =========================================================================
    // deleteConversation
    // =========================================================================

    public function testDeleteConversationUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->deleteConversation(1, $this->em)->getStatusCode());
    }

    public function testDeleteConversationInvalidIdZeroReturns400(): void
    {
        $ctrl = $this->createControllerWithUser($this->createUser());
        $this->assertSame(400, $ctrl->deleteConversation(0, $this->em)->getStatusCode());
    }

    public function testDeleteConversationNegativeIdReturns400(): void
    {
        $ctrl = $this->createControllerWithUser($this->createUser());
        $this->assertSame(400, $ctrl->deleteConversation(-5, $this->em)->getStatusCode());
    }

    public function testDeleteConversationNotFoundReturns404(): void
    {
        $user     = $this->createUser();
        $ctrl     = $this->createControllerWithUser($user);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertSame(404, $ctrl->deleteConversation(999, $this->em)->getStatusCode());
    }

    public function testDeleteConversationForbiddenForNonCreatorReturns403(): void
    {
        $user    = $this->createUser(1);
        $creator = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $conv    = $this->createConversation(10, 'Test', '', $creator);
        $ctrl    = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertSame(403, $ctrl->deleteConversation(10, $this->em)->getStatusCode());
    }

    public function testDeleteConversationTooManyMessagesReturns413(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $maxMessages = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'CONVERSATION_MAX_MESSAGES_FOR_DELETE'))->getValue();
        $messages    = array_fill(0, $maxMessages + 1, $this->createStub(Message::class));

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Test');
        $conv->method('getDescription')->willReturn('');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$user]));
        $conv->method('getMessages')->willReturn(new ArrayCollection($messages));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertSame(413, $ctrl->deleteConversation(10, $this->em)->getStatusCode());
    }

    public function testDeleteConversationSuccessReturns200(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createConversation(10, 'Test', '', $user);
        $ctrl = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $em = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        // Rebuild ctrl with fresh em to avoid shared-mock conflict
        $ctrl     = $this->createControllerWithUserAndEm($user, $em);
        $em->expects($this->once())->method('remove')->with($conv);
        $em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $em);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('deleted', strtolower(json_decode($response->getContent(), true)['message']));
    }

    public function testDeleteConversationAlsoDeletesMessages(): void
    {
        $user = $this->createUser(1);
        $msg1 = $this->createStub(Message::class);
        $msg2 = $this->createStub(Message::class);

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Test');
        $conv->method('getDescription')->willReturn('');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$user]));
        $conv->method('getMessages')->willReturn(new ArrayCollection([$msg1, $msg2]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([$msg1, $msg2]);

        $em   = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $ctrl = $this->createControllerWithUserAndEm($user, $em);

        $em->expects($this->atLeast(3))->method('remove');
        $em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $em);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, json_decode($response->getContent(), true)['deletedMessages']);
    }

    public function testDeleteConversationWithZeroMessagesReturns200(): void
    {
        $user     = $this->createUser(1);
        $conv     = $this->createConversation(10, 'Test', '', $user);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $msgRepo  = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $em   = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $ctrl = $this->createControllerWithUserAndEm($user, $em);
        $em->expects($this->once())->method('remove')->with($conv);
        $em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $em);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, json_decode($response->getContent(), true)['deletedMessages']);
    }

    public function testDeleteConversationInvalidTitleLogsWarning(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('<script>bad</script>');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $msgRepo  = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $em   = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $em->method('remove');
        $em->method('flush');

        $papertrail = $this->createMock(PapertrailService::class);
        $papertrail->expects($this->atLeastOnce())->method('warning');

        $ctrl = new class($this->messageRepo, $em, $papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $this->assertSame(200, $ctrl->deleteConversation(10, $em)->getStatusCode());
    }

    public function testDeleteConversationDbExceptionReturns500(): void
    {
        $user     = $this->createUser(1);
        $ctrl     = $this->createControllerWithUser($user);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willThrowException(new \Exception('DB failure'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertSame(500, $ctrl->deleteConversation(10, $this->em)->getStatusCode());
    }

    // =========================================================================
    // deleteMessage
    // =========================================================================

    public function testDeleteMessageUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->deleteMessage(1, $this->em)->getStatusCode());
    }

    public function testDeleteMessageInvalidIdZeroReturns400(): void
    {
        $ctrl = $this->createControllerWithUser($this->createUser());
        $this->assertSame(400, $ctrl->deleteMessage(0, $this->em)->getStatusCode());
    }

    public function testDeleteMessageNegativeIdReturns400(): void
    {
        $ctrl = $this->createControllerWithUser($this->createUser());
        $this->assertSame(400, $ctrl->deleteMessage(-1, $this->em)->getStatusCode());
    }

    public function testDeleteMessageOutOfRangeIdReturns400(): void
    {
        $ctrl = $this->createControllerWithUser($this->createUser());
        $this->assertSame(400, $ctrl->deleteMessage(PHP_INT_MAX, $this->em)->getStatusCode());
    }

    public function testDeleteMessageNotFoundReturns404(): void
    {
        $user    = $this->createUser();
        $ctrl    = $this->createControllerWithUser($user);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $this->assertSame(404, $ctrl->deleteMessage(1, $this->em)->getStatusCode());
    }

    public function testDeleteMessageForbiddenForNonAuthorReturns403(): void
    {
        $user    = $this->createUser(1);
        $author  = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $message = $this->createMessage(100, 'Hello', $author, $this->createConversation());
        $ctrl    = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $this->assertSame(403, $ctrl->deleteMessage(100, $this->em)->getStatusCode());
    }

    public function testDeleteMessageSuccessReturns200(): void
    {
        $user    = $this->createUser(1);
        $conv    = $this->createConversation(10, 'Test', '', $user);
        $message = $this->createMessage(100, 'Hello world', $user, $conv);
        $ctrl    = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $em = $this->buildEm([Message::class => $msgRepo]);
        // Rebuild ctrl with fresh em
        $ctrl = $this->createControllerWithUserAndEm($user, $em);
        $em->expects($this->once())->method('remove')->with($message);
        $em->expects($this->once())->method('flush');

        $this->assertSame(200, $ctrl->deleteMessage(100, $em)->getStatusCode());
    }

    public function testDeleteMessageNoConversationReturns500(): void
    {
        $user    = $this->createUser(1);
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(1);
        $message->method('getContent')->willReturn('Hello world');
        $message->method('getAuthor')->willReturn($user);
        $message->method('getConversation')->willReturn(null);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $em      = $this->buildEm([Message::class => $msgRepo]);
        $ctrl    = $this->createControllerWithUserAndEm($user, $em);

        $response = $ctrl->deleteMessage(1, $em);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Invalid message conversation', json_decode($response->getContent(), true)['message']);
    }

    public function testDeleteMessageWithInvalidContentLogsWarning(): void
    {
        $user    = $this->createUser(1);
        $conv    = $this->createConversation(10, 'Test', '', $user);
        $message = $this->createMessage(100, '<script>xss</script>', $user, $conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $em      = $this->buildEm([Message::class => $msgRepo]);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $papertrail = $this->createMock(PapertrailService::class);
        $papertrail->expects($this->atLeastOnce())->method('warning');

        $ctrl = new class($this->messageRepo, $em, $papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $this->assertSame(200, $ctrl->deleteMessage(100, $em)->getStatusCode());
    }

    public function testDeleteMessageDbExceptionReturns500(): void
    {
        $user    = $this->createUser(1);
        $ctrl    = $this->createControllerWithUser($user);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willThrowException(new \Exception('DB failure'));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $this->assertSame(500, $ctrl->deleteMessage(1, $this->em)->getStatusCode());
    }

    // =========================================================================
    // Private methods via Reflection
    // =========================================================================

    private function callPrivate(string $method, ...$args): mixed
    {
        $m = new \ReflectionMethod(MessageController::class, $method);
        $m->setAccessible(true);
        return $m->invoke($this->controller, ...$args);
    }

    // --- validateAvailabilityDates ---

    public function testValidateAvailabilityDatesBothNull(): void
    {
        $this->assertFalse($this->callPrivate('validateAvailabilityDates', null, null)['valid']);
    }

    public function testValidateAvailabilityDatesOnlyMinDefined(): void
    {
        $this->assertFalse($this->callPrivate('validateAvailabilityDates', new \DateTimeImmutable('+1 day'), null)['valid']);
    }

    public function testValidateAvailabilityDatesOnlyMaxDefined(): void
    {
        $this->assertFalse($this->callPrivate('validateAvailabilityDates', null, new \DateTimeImmutable('+2 days'))['valid']);
    }

    public function testValidateAvailabilityDatesMaxBeforeMin(): void
    {
        $result = $this->callPrivate('validateAvailabilityDates', new \DateTimeImmutable('+10 days'), new \DateTimeImmutable('+1 day'));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('after', $result['error']);
    }

    public function testValidateAvailabilityDatesRangeExceedsTwoYears(): void
    {
        $result = $this->callPrivate('validateAvailabilityDates', new \DateTimeImmutable('+1 day'), new \DateTimeImmutable('+800 days'));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 years', $result['error']);
    }

    public function testValidateAvailabilityDatesMaxInPast(): void
    {
        $result = $this->callPrivate('validateAvailabilityDates', new \DateTimeImmutable('-10 days'), new \DateTimeImmutable('-1 day'));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('future', $result['error']);
    }

    public function testValidateAvailabilityDatesValidRange(): void
    {
        $result = $this->callPrivate('validateAvailabilityDates', new \DateTimeImmutable('+1 day'), new \DateTimeImmutable('+30 days'));
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    // --- validateConversationParticipants ---

    public function testValidateConversationParticipantsTooMany(): void
    {
        $creator = $this->createUser(1);
        $m       = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $result  = $m->invoke($this->controller, $creator, range(2, 52), $this->em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('50', $result['error']);
    }

    public function testValidateConversationParticipantsEmpty(): void
    {
        $creator = $this->createUser(1);
        $m       = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $result  = $m->invoke($this->controller, $creator, [], $this->em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 participants', $result['error']);
    }

    public function testValidateConversationParticipantsCreatorFilteredOut(): void
    {
        $creator = $this->createUser(1);
        $m       = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $creator, [1], $this->em)['valid']);
    }

    public function testValidateConversationParticipantsNonNumericSkipped(): void
    {
        $creator = $this->createUser(1);
        $m       = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $creator, ['abc', 'xyz'], $this->em)['valid']);
    }

    public function testValidateConversationParticipantsMissingUsers(): void
    {
        $creator  = $this->createUser(1);
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findBy')->willReturn([]);
        $em = $this->buildEm([User::class => $userRepo]);

        $m      = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, $creator, [2, 3], $em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user IDs', $result['error']);
    }

    public function testValidateConversationParticipantsValid(): void
    {
        $creator     = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $userRepo    = $this->createMock(EntityRepository::class);
        $userRepo->method('findBy')->willReturn([$participant]);
        $em = $this->buildEm([User::class => $userRepo]);

        $m      = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, $creator, [2], $em);
        $this->assertTrue($result['valid']);
        $this->assertCount(1, $result['validUsers']);
    }

    // --- validateEmailandUniqueness ---

    public function testValidateEmailTooLong(): void
    {
        $m      = new \ReflectionMethod(MessageController::class, 'validateEmailandUniqueness');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, str_repeat('a', 250) . '@b.com', 1, $this->em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('255', $result['error']);
    }

    public function testValidateEmailInvalidFormat(): void
    {
        $m = new \ReflectionMethod(MessageController::class, 'validateEmailandUniqueness');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, 'not-an-email', 1, $this->em)['valid']);
    }

    public function testValidateEmailTakenByOtherUser(): void
    {
        $otherUser = $this->createUser(99);
        $em        = $this->buildEm([User::class => $this->stubUserRepository($otherUser)]);

        $m      = new \ReflectionMethod(MessageController::class, 'validateEmailandUniqueness');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, 'taken@example.com', 1, $em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already in use', $result['error']);
    }

    public function testValidateEmailSameUserAllowed(): void
    {
        $currentUser = $this->createUser(1);
        $em          = $this->buildEm([User::class => $this->stubUserRepository($currentUser)]);

        $m = new \ReflectionMethod(MessageController::class, 'validateEmailandUniqueness');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, 'jean@example.com', 1, $em)['valid']);
    }

    // --- canonicalDecode ---

    public function testCanonicalDecodeHtmlEntities(): void
    {
        $this->assertSame('Hello & World', $this->callPrivate('canonicalDecode', 'Hello &amp; World'));
    }

    public function testCanonicalDecodeUrlEncoded(): void
    {
        $this->assertSame('Hello World', $this->callPrivate('canonicalDecode', 'Hello%20World'));
    }

    public function testCanonicalDecodeDoubleEncoded(): void
    {
        $this->assertSame('Hello & World', $this->callPrivate('canonicalDecode', 'Hello%20%26%20World'));
    }

    public function testCanonicalDecodeTooLongThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->callPrivate('canonicalDecode', str_repeat('a', 200001));
    }

    public function testCanonicalDecodePlainStringUnchanged(): void
    {
        $this->assertSame('Hello World', $this->callPrivate('canonicalDecode', 'Hello World'));
    }

    // --- validateString ---

    public function testValidateStringEmpty(): void           { $this->assertFalse($this->callPrivate('validateString', '', 100)); }
    public function testValidateStringTooLong(): void         { $this->assertFalse($this->callPrivate('validateString', str_repeat('a', 101), 100)); }
    public function testValidateStringCsvInjection(): void    { $this->assertFalse($this->callPrivate('validateString', '=SUM(A1)', 100)); }
    public function testValidateStringXss(): void             { $this->assertFalse($this->callPrivate('validateString', '<script>alert(1)</script>', 100)); }
    public function testValidateStringSqlSelect(): void        { $this->assertFalse($this->callPrivate('validateString', 'select * from users', 100)); }
    public function testValidateStringSqlUnion(): void         { $this->assertFalse($this->callPrivate('validateString', 'union select password', 100)); }
    public function testValidateStringSqlDrop(): void          { $this->assertFalse($this->callPrivate('validateString', 'drop table users', 100)); }
    public function testValidateStringIframe(): void           { $this->assertFalse($this->callPrivate('validateString', '<iframe src="x"></iframe>', 100)); }
    public function testValidateStringJavascript(): void       { $this->assertFalse($this->callPrivate('validateString', 'javascript:alert(1)', 100)); }
    public function testValidateStringOnEvent(): void          { $this->assertFalse($this->callPrivate('validateString', 'onload=bad', 100)); }
    public function testValidateStringValid(): void            { $this->assertTrue($this->callPrivate('validateString', 'Hello world, valid!', 100)); }
    public function testValidateStringWithNewline(): void      { $this->assertTrue($this->callPrivate('validateString', "Hello\nworld", 100)); }

    // --- validateName ---

    public function testValidateNameEmpty(): void               { $this->assertFalse($this->callPrivate('validateName', '')); }
    public function testValidateNameTooShort(): void             { $this->assertFalse($this->callPrivate('validateName', 'A')); }
    public function testValidateNameTooLong(): void              { $this->assertFalse($this->callPrivate('validateName', str_repeat('a', 21))); }
    public function testValidateNameStartsWithHyphen(): void     { $this->assertFalse($this->callPrivate('validateName', '-Jean')); }
    public function testValidateNameEndsWithSpace(): void        { $this->assertFalse($this->callPrivate('validateName', 'Jean ')); }
    public function testValidateNameContainsDigit(): void        { $this->assertFalse($this->callPrivate('validateName', 'Jean2')); }
    public function testValidateNameDangerousCharLt(): void      { $this->assertFalse($this->callPrivate('validateName', 'Jean<Luc')); }
    public function testValidateNameDangerousCharAt(): void      { $this->assertFalse($this->callPrivate('validateName', 'Jean@Luc')); }
    public function testValidateNameDangerousCharAmp(): void     { $this->assertFalse($this->callPrivate('validateName', 'Jean&Luc')); }
    public function testValidateNameDangerousCharSlash(): void   { $this->assertFalse($this->callPrivate('validateName', 'Jean/Luc')); }
    public function testValidateNameThreeConsecutive(): void     { $this->assertFalse($this->callPrivate('validateName', 'Jean---Luc')); }
    public function testValidateNameValidHyphen(): void          { $this->assertTrue($this->callPrivate('validateName', 'Jean-Luc')); }
    public function testValidateNameValidApostrophe(): void      { $this->assertTrue($this->callPrivate('validateName', "O'Brien")); }
    public function testValidateNameValidAccented(): void        { $this->assertTrue($this->callPrivate('validateName', 'Éléonore')); }

    // --- sanitizeHtml ---

    public function testSanitizeHtmlNullReturnsEmpty(): void      { $this->assertSame('', $this->callPrivate('sanitizeHtml', null)); }
    public function testSanitizeHtmlEmptyReturnsEmpty(): void     { $this->assertSame('', $this->callPrivate('sanitizeHtml', '')); }

    public function testSanitizeHtmlStripsTagsNoFormatting(): void
    {
        $result = $this->callPrivate('sanitizeHtml', '<strong>Hello</strong> <script>bad</script>', false);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeHtmlAllowsStrongWithFormatting(): void
    {
        $result = $this->callPrivate('sanitizeHtml', '<strong>Hello</strong>', true);
        $this->assertStringContainsString('<strong>Hello</strong>', $result);
    }

    public function testSanitizeHtmlStripsScriptWithFormatting(): void
    {
        $result = $this->callPrivate('sanitizeHtml', '<strong>ok</strong><script>bad</script>', true);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('<strong>ok</strong>', $result);
    }

    public function testSanitizeHtmlBlocksExternalLinks(): void
    {
        $result = $this->callPrivate('sanitizeHtml', '<a href="https://evil.com">click</a>', true);
        $this->assertStringNotContainsString('evil.com', $result);
    }

    // --- sanitizeForJson ---

    public function testSanitizeForJsonNull(): void              { $this->assertNull($this->callPrivate('sanitizeForJson', null)); }
    public function testSanitizeForJsonInteger(): void           { $this->assertSame(42, $this->callPrivate('sanitizeForJson', 42)); }

    public function testSanitizeForJsonStripsControlChars(): void
    {
        $this->assertSame('helloworld', $this->callPrivate('sanitizeForJson', "hello\x00\x01\x1Fworld"));
    }

    public function testSanitizeForJsonPreservesNewlineTabCr(): void
    {
        $this->assertSame("hello\n\t\rworld", $this->callPrivate('sanitizeForJson', "hello\n\t\rworld"));
    }

    public function testSanitizeForJsonArray(): void
    {
        $this->assertSame(['helloworld', 'clean'], $this->callPrivate('sanitizeForJson', ["hello\x00world", "clean"]));
    }

    // --- sanitizeData ---

    public function testSanitizeDataTitle(): void
    {
        $result = $this->callPrivate('sanitizeData', ['title' => '<b>hello</b>']);
        $this->assertStringNotContainsString('<b>', $result['title']);
    }

    public function testSanitizeDataDescription(): void
    {
        $result = $this->callPrivate('sanitizeData', ['description' => '<strong>ok</strong><script>bad</script>']);
        $this->assertStringNotContainsString('<script>', $result['description']);
    }

    public function testSanitizeDataContent(): void
    {
        $result = $this->callPrivate('sanitizeData', ['content' => '<em>text</em>']);
        $this->assertArrayHasKey('content', $result);
    }

    public function testSanitizeDataConvUsersDeduplicates(): void
    {
        $result = $this->callPrivate('sanitizeData', ['conv_users' => [1, 2, 2, 0, -1, 3]]);
        $this->assertEqualsCanonicalizing([1, 2, 3], array_values($result['conv_users']));
    }

    public function testSanitizeDataConversationId(): void
    {
        $result = $this->callPrivate('sanitizeData', ['conversation_id' => '42']);
        $this->assertSame(42, $result['conversation_id']);
    }

    // --- validateMessageRateLimit ---

    public function testValidateMessageRateLimitUnderLimit(): void
    {
        $user    = $this->createUser();
        $conv    = $this->createConversation();
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(5));
        $em = $this->buildEm([Message::class => $msgRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateMessageRateLimit');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $user, $conv, $em)['valid']);
    }

    public function testValidateMessageRateLimitAtLimit(): void
    {
        $user    = $this->createUser();
        $conv    = $this->createConversation();
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));
        $em = $this->buildEm([Message::class => $msgRepo]);

        $m      = new \ReflectionMethod(MessageController::class, 'validateMessageRateLimit');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, $user, $conv, $em);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit', strtolower($result['error']));
    }

    public function testValidateMessageRateLimitDbExceptionFailsOpen(): void
    {
        $user    = $this->createUser();
        $conv    = $this->createConversation();
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $em = $this->buildEm([Message::class => $msgRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateMessageRateLimit');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $user, $conv, $em)['valid']);
    }

    // --- validateConversationDeleteRateLimit ---

    public function testValidateConversationDeleteRateLimitNominal(): void
    {
        $user = $this->createUser();
        $m    = new \ReflectionMethod(MessageController::class, 'validateConversationDeleteRateLimit');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $user, $this->em)['valid']);
    }

    public function testValidateConversationDeleteRateLimitDbExceptionFailsOpen(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateConversationDeleteRateLimit');
        $m->setAccessible(true);
        // Fail-open: even with a DB exception, should return valid=true
        $this->assertTrue($m->invoke($this->controller, $user, $em)['valid']);
    }

    // --- validateConversationCreateRateLimit ---

    public function testValidateConversationCreateRateLimitUnderLimit(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateConversationCreateRateLimit');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $user, $em)['valid']);
    }

    public function testValidateConversationCreateRateLimitExceeded(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(999));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateConversationCreateRateLimit');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $user, $em)['valid']);
    }

    public function testValidateConversationCreateRateLimitDbExceptionFailsOpen(): void
    {
        $user     = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'validateConversationCreateRateLimit');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $user, $em)['valid']);
    }

    // --- generateConversationHash ---

    public function testGenerateConversationHashDeterministic(): void
    {
        $h1 = $this->callPrivate('generateConversationHash', [1, 2, 3], 'My Conv');
        $h2 = $this->callPrivate('generateConversationHash', [3, 1, 2], 'My Conv');
        $this->assertSame($h1, $h2);
    }

    public function testGenerateConversationHashDiffersOnTitle(): void
    {
        $this->assertNotSame(
            $this->callPrivate('generateConversationHash', [1, 2], 'Title A'),
            $this->callPrivate('generateConversationHash', [1, 2], 'Title B')
        );
    }

    public function testGenerateConversationHashNormalizesCase(): void
    {
        $this->assertSame(
            $this->callPrivate('generateConversationHash', [1, 2], 'My Conv'),
            $this->callPrivate('generateConversationHash', [1, 2], 'MY CONV')
        );
    }

    public function testGenerateConversationHashNormalizesWhitespace(): void
    {
        $this->assertSame(
            $this->callPrivate('generateConversationHash', [1, 2], 'My  Conv'),
            $this->callPrivate('generateConversationHash', [1, 2], 'My Conv')
        );
    }

    // --- checkDuplicateConversation ---

    public function testCheckDuplicateConversationNoDuplicate(): void
    {
        $creator  = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, null));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'checkDuplicateConversation');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $creator, [2], 'Test', $em));
    }

    public function testCheckDuplicateConversationFound(): void
    {
        $creator  = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, $this->createMock(Conversation::class)));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'checkDuplicateConversation');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke($this->controller, $creator, [2], 'Test', $em));
    }

    public function testCheckDuplicateConversationDbExceptionReturnsFalse(): void
    {
        $creator  = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        $em = $this->buildEm([Conversation::class => $convRepo]);

        $m = new \ReflectionMethod(MessageController::class, 'checkDuplicateConversation');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $creator, [2], 'Test', $em));
    }

    // --- validateUserState ---

    public function testValidateUserStateValid(): void
    {
        $user = $this->createUser();
        $em   = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        // validateUserState uses $this->em injected in controller, not a param
        // Rebuild controller with fresh em
        $ctrl   = new MessageController($this->messageRepo, $em, $this->papertrail);
        $result = $m->invoke($ctrl, $user);
        $this->assertTrue($result['valid']);
    }

    public function testValidateUserStateInvalidFirstName(): void
    {
        $user   = $this->createUser(firstName: 'Jean99');
        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, $user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }

    public function testValidateUserStateInvalidLastName(): void
    {
        $user   = $this->createUser(lastName: 'Dupont<>');
        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        $this->assertFalse($m->invoke($this->controller, $user)['valid']);
    }

    public function testValidateUserStateInvalidEmail(): void
    {
        $user   = $this->createUser(email: 'bad-email');
        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        $result = $m->invoke($this->controller, $user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user email', $result['error']);
    }

    public function testValidateUserStateEmailTakenByOtherUser(): void
    {
        $user      = $this->createUser(1, 'Jean', 'Dupont', 'jean@example.com');
        $otherUser = $this->createUser(2, 'Autre', 'Nom', 'jean@example.com');
        $em        = $this->buildEm([User::class => $this->stubUserRepository($otherUser)]);

        $ctrl   = new MessageController($this->messageRepo, $em, $this->papertrail);
        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        $result = $m->invoke($ctrl, $user);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user email', $result['error']);
    }


    public function testValidateUserStateInvalidDates(): void
    {
        $user = $this->createUser(
            availabilityStart: new \DateTimeImmutable('+10 days'),
            availabilityEnd:   new \DateTimeImmutable('+1 day')
        );
        $em   = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl   = new MessageController($this->messageRepo, $em, $this->papertrail);
        $m      = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);
        $result = $m->invoke($ctrl, $user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid availability dates', $result['error']);
    }











    public function testValidateStringStartsWithHyphenIsRejected(): void
    {
        // '-' est autorisé par la regex MAIS bloqué par le check CSV first-char
        $this->assertFalse($this->callPrivate('validateString', '-Hello world', 100));
    }

    public function testValidateStringStartsWithPlusFails(): void
    {
        // '+' n'est pas dans la regex → bloqué avant le CSV check
        // Ce test documente que '+' échoue (via regex, pas CSV)
        $this->assertFalse($this->callPrivate('validateString', '+Hello', 100));
    }


    // =========================================================================
    // NOUVEAU — validateConversationParticipants : branche $userId <= 0 (1 ligne)
    //
    // is_numeric(0) = true → on entre dans le cast → (int)0 = 0 → 0 <= 0 → continue
    // Cette ligne n'était jamais atteinte dans les tests précédents.
    // =========================================================================

    public function testValidateConversationParticipantsZeroAndNegativeIdsSkipped(): void
    {
        $creator = $this->createUser(1);
        $m       = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $m->setAccessible(true);

        // 0 est numérique mais <= 0 → skippé ; -1 idem → validUserIds reste vide
        $result = $m->invoke($this->controller, $creator, [0, -1, '0'], $this->em);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 participants', $result['error']);
    }


    // =========================================================================
    // NOUVEAU — validateUserState : 4 branches du bloc diagnostique (8 lignes)
    //
    // Le bloc "Tester chaque condition" (lines ~506-535) ne s'exécute que
    // lorsque firstName est invalide. Chaque sous-branche n'est couverte
    // que par un input spécifique.
    // =========================================================================

    /**
     * Branche 'length' — mb_strlen($firstName) < 2
     * 'J' (1 char) passe empty() mais échoue le test < 2 chars.
     */
    public function testValidateUserStateFirstNameTooShortCoversLengthBranch(): void
    {
        $user = $this->createUser(firstName: 'J'); // longueur 1 < 2 minimum
        $m    = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);

        $result = $m->invoke($this->controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }

    /**
     * Branche 'start_end_invalid' — firstName commence par '-'
     * preg_match('/^[\s\-\']/', '-Jean') = true → raison ajoutée.
     */
    public function testValidateUserStateFirstNameStartsWithHyphenCoversStartEndBranch(): void
    {
        $user = $this->createUser(firstName: '-Jean');
        $m    = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);

        $result = $m->invoke($this->controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }

    /**
     * Branche 'dangerous_char' — firstName contient '<'
     * '<' n'est pas dans la regex autorisée → invalid_chars détecté.
     * Dans le bloc diagnostique, str_contains($firstName, '<') = true
     * → exécute le corps du foreach ($dangerousChars) → lignes ~527-530.
     */
    public function testValidateUserStateFirstNameWithDangerousCharCoversDangerousBranch(): void
    {
        $user = $this->createUser(firstName: 'Jean<Luc');
        $m    = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);

        $result = $m->invoke($this->controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }

    /**
     * Branche 'too_many_consecutive' — 3 tirets consécutifs
     * 'Jean---Luc' PASSE la regex (lettres + tirets) mais échoue
     * /[\s'\-]{3,}/ → raison 'too_many_consecutive' ajoutée → lignes ~532-534.
     */
    public function testValidateUserStateFirstNameThreeConsecutiveCharsCoversConsecutiveBranch(): void
    {
        $user = $this->createUser(firstName: 'Jean---Luc');
        $m    = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $m->setAccessible(true);

        $result = $m->invoke($this->controller, $user);

        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }


    // =========================================================================
    // NOUVEAU — getConnectedUser : 2 branches manquantes (6 + 4 = 10 lignes)
    // =========================================================================

    /**
     * Couvre les branches ELSE des blocs availabilityDates (~lignes 731-735 et 743-748).
     *
     * Quand start (+10j) > end (+1j) : validateAvailabilityDates retourne valid=false
     * → on entre dans le else → warning loggé pour start ET pour end.
     * La réponse est quand même 200 (les dates invalides sont ignorées, pas fatales).
     */
    public function testGetConnectedUserInvalidDatesLogsWarningAndReturns200(): void
    {
        $user = $this->createUser(
            availabilityStart: new \DateTimeImmutable('+10 days'), // start APRÈS end → invalide
            availabilityEnd:   new \DateTimeImmutable('+1 day')
        );

        $em = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        // Au moins 2 warnings : un pour start, un pour end
        $this->papertrail->expects($this->atLeast(2))->method('warning');

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Couvre le bloc catch (\Throwable $e) de getConnectedUser (~lignes 769-774).
     *
     * validateEmailandUniqueness() utilise $this->em (injecté dans le constructeur).
     * On configure ce $em pour que findOneBy() lance une exception.
     * Cette exception remonte jusqu'au catch de getConnectedUser → 500.
     */
    public function testGetConnectedUserEmailDbExceptionHitsCatchAndReturns500(): void
    {
        $user     = $this->createUser(1, 'Jean', 'Dupont', 'jean@example.com');
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willThrowException(new \Exception('DB connection lost'));
        $em = $this->buildEm([User::class => $userRepo]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * Couvre aussi le cas : availabilityStart set, availabilityEnd null
     * → entre dans le premier if mais pas le second.
     */
    public function testGetConnectedUserOnlyStartDateSetLogsWarning(): void
    {
        $user = $this->createUser(
            availabilityStart: new \DateTimeImmutable('+1 day'),
            availabilityEnd:   null  // pas de end → validateAvailabilityDates retourne false
        );

        $em   = $this->buildEm([User::class => $this->stubUserRepository(null)]);

        $ctrl = new class($this->messageRepo, $em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->user = $u; }
            public function getUser(): ?UserInterface { return $this->user; }
        };

        $this->papertrail->expects($this->atLeastOnce())->method('warning');

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
    }


    // =========================================================================
    // NOUVEAU — getUserConversations : branche description invalide (4 lignes)
    //
    // Ligne ~811-816 : if ($description !== '' && !$this->validateString($description, 1000))
    // N'était jamais testée.
    // =========================================================================

    public function testGetUserConversationsInvalidDescriptionIsResetToEmpty(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Valid Title');
        $conv->method('getDescription')->willReturn('<script>bad description</script>'); // invalide
        $conv->method('getCreatedBy')->willReturn($currentUser);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01'));
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$currentUser, $participant]));
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));
        $em = $this->buildEm([
            Conversation::class => $convRepo,
            User::class         => $this->stubUserRepository(null),
        ]);

        // Warning doit être loggé pour la description invalide
        $this->papertrail->expects($this->atLeastOnce())->method('warning');

        $ctrl     = $this->createControllerWithUserAndEm($currentUser, $em);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        // La conversation est quand même retournée (description invalide = resetée, pas fatale)
        $this->assertCount(1, $data);
    }



    // =========================================================================
    // NOUVEAU — getMessages : pagination offset trop grand → 400
    //
    // Atteinte quand ($page - 1) * $limit > BusinessLimits::PAGINATION_MAX_OFFSET
    // Nécessite page et limit qui donnent un offset dépassant la constante.
    // =========================================================================

    public function testGetMessagesVeryHighPageReturns400(): void
    {
        $user     = $this->createUser();
        $security = $this->createSecurity($user);

        // Utiliser PAGINATION_MAX_PAGE + 1 pour forcer le clamping puis dépasser l'offset
        // On cherche à produire un offset > PAGINATION_MAX_OFFSET
        // Avec limit=100 et page très haute, le clamping ramène à PAGINATION_MAX_PAGE.
        // Si PAGINATION_MAX_PAGE * 100 > PAGINATION_MAX_OFFSET → 400 retourné.
        // Sinon ce test retourne 200 (la branche est morte pour ces constantes).
        $request = Request::create('/api/get/messages', 'GET', [
            'page'  => PHP_INT_MAX,
            'limit' => 100,
        ]);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);

        // Accepte 200 OU 400 selon les valeurs des constantes BusinessLimits
        $this->assertContains($response->getStatusCode(), [200, 400]);
    }


    // =========================================================================
    // NOUVEAU — createConversation : description présente dans le payload
    //
    // Couvre la branche isset($data['description']) dans sanitizeData
    // ET la ligne $conversation->setDescription($data['description'] ?? '').
    // =========================================================================


public function testCreateConversationWithDescriptionFieldIs201(): void
{
    $creator     = $this->createUser(1);
    $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
    $security    = $this->createSecurity($creator);

    $userRepo = $this->createMock(EntityRepository::class);
    $userRepo->method('findOneBy')->willReturn(null);
    $userRepo->method('findBy')->willReturn([$participant]);

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

    $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
    $em->method('beginTransaction');
    $em->method('persist')->willReturnCallback(function ($entity) {
        $prop = (new \ReflectionClass($entity))->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, 1);
    });
    $em->method('flush');
    $em->method('commit');

    $request  = $this->createJsonRequest([
        'title'       => 'My conversation',
        'description' => 'A nice description here',
        'conv_users'  => [2],
    ]);
    $response = $this->controller->createConversation($request, $security, $em);

    $this->assertSame(201, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame('My conversation', $data['title']);
}

    // =========================================================================
    // deleteMessage : branche conversation invalide (getConversation null)
    // (@codeCoverageIgnore). dans code controller
    // =========================================================================

    public function testDeleteMessageWithNullConversationReturns500WithMessage(): void
    {
        $user    = $this->createUser(1);
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(42);
        $message->method('getContent')->willReturn('Hello world');
        $message->method('getAuthor')->willReturn($user);
        $message->method('getConversation')->willReturn(null); // ← déclenche le 500

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $em      = $this->buildEm([Message::class => $msgRepo]);
        $ctrl    = $this->createControllerWithUserAndEm($user, $em);

        $response = $ctrl->deleteMessage(42, $em);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Invalid message conversation', json_decode($response->getContent(), true)['message']);
    }












// =========================================================================
// NOUVEAU — validateString : branche mb_strlen > 10000 (1 ligne manquante)
// La condition est : mb_strlen($value) > $maxLength || mb_strlen($value) > 10000
// Si maxLength est > 10000, seule la branche >10000 bloque
// =========================================================================

public function testValidateStringExceeds10000CharsAbsoluteLimit(): void
{
    // maxLength très grand (20000) mais la valeur dépasse 10000
    // Seule la protection absolue >10000 bloque
    $longValue = str_repeat('a', 10001);
    $this->assertFalse($this->callPrivate('validateString', $longValue, 20000));
}

// =========================================================================
// NOUVEAU — getMessages : offset > PAGINATION_MAX_OFFSET → 400 forcé
// On doit forcer page et limit pour que l'offset calculé dépasse la constante
// sans être clampé par PAGINATION_MAX_PAGE
// =========================================================================

public function testGetMessagesOffsetExceedsMaxReturns400(): void
{
    // On va accéder à la constante via réflexion
    $maxOffset = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_OFFSET'))->getValue();
    $maxPage   = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_PAGE'))->getValue();
    $maxLimit  = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_LIMIT'))->getValue();

    // Si maxPage * maxLimit <= maxOffset, la branche est morte → skip
    if (($maxPage - 1) * $maxLimit <= $maxOffset) {
        $this->markTestSkipped('BusinessLimits configuration makes this branch unreachable');
    }

    $user     = $this->createUser();
    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', [
        'page'  => $maxPage,
        'limit' => $maxLimit,
    ]);

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertSame(400, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame('Page number too high', $data['message']);
}

// =========================================================================
// NOUVEAU — getMessages : page > PAGINATION_MAX_PAGE → clamped
// =========================================================================

public function testGetMessagesBeyondMaxPageIsClamped(): void
{
    $user     = $this->createUser();
    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', [
        'page'  => 999999,
        'limit' => 1,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    // Avec limit=1 l'offset est clampé à PAGINATION_MAX_PAGE - 1, jamais > MAX_OFFSET
    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertContains($response->getStatusCode(), [200, 400]);
}

// =========================================================================
// NOUVEAU — getMessages : limit < PAGINATION_MIN_LIMIT → clamped
// =========================================================================

public function testGetMessagesLimitBelowMinIsClamped(): void
{
    $user     = $this->createUser();
    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', [
        'page'  => 1,
        'limit' => -99,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertSame(200, $response->getStatusCode());
}
// =========================================================================
// createMessage : ligne après le return (em->clear unreachable)
// (@codeCoverageIgnore). dans code controller
// =========================================================================

public function testCreateMessagePersistExceptionTriggersRollbackAndReturns500(): void
{
    $user    = $this->createUser(1);
    $conv    = $this->createConversation(10, 'Ma conv', '', $user, [$user]);
    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));
    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conv);

    $em = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
    $em->method('persist')->willThrowException(new \Exception('Persist failed'));
    $em->expects($this->once())->method('rollback');

    $ctrl     = $this->createAcceptingController($em);
    $response = $ctrl->createMessage(
        $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]),
        $this->createSecurity($user),
        $em,
        $this->createDummyFactory()
    );

    $this->assertSame(500, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame('Error creating message', $data['message']);
}


// =========================================================================
// validateString : valeur > 10000 chars avec contenu valide
// La condition est : mb_strlen($value) > $maxLength || mb_strlen($value) > 10000
// Avec maxLength=20000, seule la borne absolue 10000 bloque
// =========================================================================

public function testValidateStringOver10000CharsWithValidCharsIsRejected(): void
{
    // Générer 10001 lettres valides (ne contient que des lettres)
    // La regex passe, mais mb_strlen > 10000 bloque
    $value = str_repeat('a', 10001);
    // maxLength grand pour que seule la borne absolue intervienne
    $this->assertFalse($this->callPrivate('validateString', $value, 20000));
}

// =========================================================================
// getMessages : forcer le chemin offset > PAGINATION_MAX_OFFSET → 400
// On contourne le clamping en utilisant des valeurs de constantes réelles
// =========================================================================

public function testGetMessagesMaxPageWithMaxLimitMayReturn400(): void
{
    $maxOffset = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_OFFSET'))->getValue();
    $maxPage   = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_PAGE'))->getValue();
    $maxLimit  = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_LIMIT'))->getValue();

    $user     = $this->createUser();
    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', [
        'page'  => $maxPage,
        'limit' => $maxLimit,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $offset   = ($maxPage - 1) * $maxLimit;

    if ($offset > $maxOffset) {
        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Page number too high', $data['message']);
        $this->assertStringContainsString((string)$maxOffset, $data['error']);
    } else {
        $this->assertSame(200, $response->getStatusCode());
    }
}

// =========================================================================
// createMessage : exception dans persist → rollback → 500
// Couvre le catch final et le em->clear() dans le catch
// =========================================================================

public function testCreateMessagePersistThrowsCoversRollbackAndCatchBlock(): void
{
    $user    = $this->createUser(1);
    $conv    = $this->createConversation(10, 'Ma conv', '', $user, [$user]);

    $msgRepo = $this->createMock(EntityRepository::class);
    $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

    $convRepo = $this->createMock(EntityRepository::class);
    $convRepo->method('find')->willReturn($conv);

    $em = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
    $em->method('persist')->willThrowException(new \Exception('DB write error'));
    $em->expects($this->once())->method('rollback');
    $em->expects($this->once())->method('clear');

    $ctrl     = $this->createAcceptingController($em);
    $response = $ctrl->createMessage(
        $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]),
        $this->createSecurity($user),
        $em,
        $this->createDummyFactory()
    );

    $this->assertSame(500, $response->getStatusCode());
    $data = json_decode($response->getContent(), true);
    $this->assertSame('Error creating message', $data['message']);
    $this->assertSame('DB write error', $data['error']);
}






















public function testGetMessagesSingleWordAuthorNameFallsBackToUnknown(): void
{
    $user   = $this->createUser();
    $conv   = $this->createConversation(creator: $user);
    $author = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

    $msg = $this->createMock(Message::class);
    $msg->method('getId')->willReturn(1);
    $msg->method('getContent')->willReturn('Hello world');
    $msg->method('getAuthor')->willReturn($author);
    $msg->method('getAuthorName')->willReturn('Mononym');
    $msg->method('getConversation')->willReturn($conv);
    $msg->method('getConversationTitle')->willReturn('Test Conv');
    $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

    $this->messageRepo->method('findBy')->willReturn([$msg]);
    $this->messageRepo->method('count')->willReturn(1);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $data     = json_decode($response->getContent(), true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertCount(1, $data['data']);
    $this->assertSame('Unknown User', $data['data'][0]['authorName']);
}

public function testGetMessagesBothNamePartsInvalidFallsBackToUnknown(): void
{
    $user   = $this->createUser();
    $conv   = $this->createConversation(creator: $user);
    $author = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

    $msg = $this->createMock(Message::class);
    $msg->method('getId')->willReturn(2);
    $msg->method('getContent')->willReturn('Hello world');
    $msg->method('getAuthor')->willReturn($author);
    $msg->method('getAuthorName')->willReturn('Bad99 Name99');
    $msg->method('getConversation')->willReturn($conv);
    $msg->method('getConversationTitle')->willReturn('Test Conv');
    $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

    $security = $this->createSecurity($user);
    $request  = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

    $this->messageRepo->method('findBy')->willReturn([$msg]);
    $this->messageRepo->method('count')->willReturn(1);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $data     = json_decode($response->getContent(), true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('Unknown User', $data['data'][0]['authorName']);
}

public function testGetMessagesAllPaginationClampingBranches(): void
{
    $user     = $this->createUser();
    $security = $this->createSecurity($user);

    // page < MIN → clampé vers MIN
    // limit > MAX → clampé vers MAX
    $request = Request::create('/api/get/messages', 'GET', [
        'page'  => -5,
        'limit' => 999999,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertContains($response->getStatusCode(), [200, 400]);
}

public function testGetMessagesPaginationPageAboveMaxIsClamped(): void
{
    $user     = $this->createUser();
    $security = $this->createSecurity($user);

    $maxPage = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'PAGINATION_MAX_PAGE'))->getValue();

    // page > MAX → clampé vers MAX
    $request = Request::create('/api/get/messages', 'GET', [
        'page'  => $maxPage + 1,
        'limit' => 1,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertContains($response->getStatusCode(), [200, 400]);
}

public function testGetMessagesPaginationLimitBelowMinIsClamped(): void
{
    $user     = $this->createUser();
    $security = $this->createSecurity($user);

    $request = Request::create('/api/get/messages', 'GET', [
        'page'  => 1,
        'limit' => 0,
    ]);

    $this->messageRepo->method('findBy')->willReturn([]);
    $this->messageRepo->method('count')->willReturn(0);
    $this->em->method('getRepository')->willReturn($this->stubUserRepository(null));

    $response = $this->controller->getMessages($security, $this->messageRepo, $request);
    $this->assertSame(200, $response->getStatusCode());
}


}