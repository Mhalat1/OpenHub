<?php

namespace App\Tests\Controller;
use Psr\Container\ContainerInterface; // Add this at the top of your test file
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
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RateLimiterFactory is declared final — it cannot be extended or mocked by PHPUnit.
 *
 * Solution: subclass MessageController and override createMessage() so that it
 * builds a real RateLimiterFactory backed by InMemoryStorage. We control
 * accept/reject by choosing a "no_limit" policy (always accepted) or a
 * "fixed_window" policy with limit=1 that is pre-exhausted (always rejected).
 */
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

/**
 * Concrete stub for Doctrine Query — avoids mocking the abstract class.
 */
class StubQuery extends Query
{
    private mixed $scalarResult;
    private mixed $singleResult;
    private array $listResult;

    public function __construct(mixed $scalar = 0, mixed $single = null, array $list = [])
    {
        $this->scalarResult = $scalar;
        $this->singleResult = $single;
        $this->listResult = $list;
    }

    public function getSingleScalarResult(): mixed
    {
        return $this->scalarResult;
    }
    public function getOneOrNullResult(mixed $hydrationMode = null): mixed
    {
        return $this->singleResult;
    }
    public function getResult(mixed $hydrationMode = 1): array
    {
        return $this->listResult;
    }
    public function execute(mixed $parameters = null, mixed $hydrationMode = null): mixed
    {
        return $this->listResult;
    }
    public function getSQL(): string
    {
        return '';
    }
    protected function _doExecute(): int
    {
        return 0;
    }
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
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->papertrail = $this->createMock(PapertrailService::class);

        $this->controller = new MessageController(
            $this->messageRepo,
            $this->em,
            $this->papertrail
        );
    }

    // =======================================================================
    // Helpers améliorés
    // =======================================================================

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
        $msg->method('getAuthorName')->willReturn($author ? $author->getFirstName() . ' ' . $author->getLastName() : 'Unknown');
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

    private function createAcceptingController(?EntityManagerInterface $em = null): TestableMessageController
    {
        return new TestableMessageController(
            $this->messageRepo,
            $em ?? $this->em,
            $this->papertrail,
            true
        );
    }

    private function createRejectingController(?EntityManagerInterface $em = null): TestableMessageController
    {
        return new TestableMessageController(
            $this->messageRepo,
            $em ?? $this->em,
            $this->papertrail,
            false
        );
    }

    private function createControllerWithUser(User $user): MessageController
    {
        return new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };
    }

    private function createJsonRequest(array $payload, string $method = 'POST'): Request
    {
        $request = Request::create('/', $method, [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        return $request;
    }

    private function createDummyFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'dummy', 'policy' => 'no_limit'],
            new InMemoryStorage()
        );
    }

    private function createCountQueryBuilder(int $count, mixed $singleResult = null): QueryBuilder&MockObject
    {
        $query = new StubQuery($count, $singleResult, []);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        return $qb;
    }

    private function createListQueryBuilder(array $list): QueryBuilder&MockObject
    {
        $query = new StubQuery(0, null, $list);
        $qb = $this->createMock(QueryBuilder::class);
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

    // =======================================================================
    // Tests getConnectedUser
    // =======================================================================



    public function testGetConnectedUserUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface
            {
                return null;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGetConnectedUserValidReturns200(): void
    {
        $user = $this->createUser();
        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('jean.dupont@example.com', $data['email']);
        $this->assertSame('Jean', $data['firstName']);
        $this->assertSame('Dupont', $data['lastName']);
    }

    public function testGetConnectedUserInvalidFirstNameReturns500(): void
    {
        $user = $this->createUser(firstName: 'Jean123');
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetConnectedUserWithValidAvailabilityDates(): void
    {
        $start = new \DateTimeImmutable('+1 day');
        $end = new \DateTimeImmutable('+30 days');
        $user = $this->createUser(availabilityStart: $start, availabilityEnd: $end);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['availabilityStart']);
        $this->assertNotNull($data['availabilityEnd']);
    }

    public function testGetConnectedUserInvalidLastNameReturns500(): void
    {
        $user = $this->createUser(lastName: 'Dupont99');
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetConnectedUserNotUserInstanceReturns500(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface
            {
                return new class implements UserInterface {
                    public function getRoles(): array
                    {
                        return [];
                    }
                    public function eraseCredentials(): void {}
                    public function getUserIdentifier(): string
                    {
                        return 'anon';
                    }
                };
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
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

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['userData']);
        $this->assertSame(42, $data['userData'][0]['id']);
    }

    public function testGetConnectedUserInvalidEmailReturns500(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getFirstName')->willReturn('Jean');
        $user->method('getLastName')->willReturn('Dupont');
        $user->method('getEmail')->willReturn('not-an-email');
        $user->method('getUserIdentifier')->willReturn('not-an-email');
        $user->method('getAvailabilityStart')->willReturn(null);
        $user->method('getAvailabilityEnd')->willReturn(null);
        $user->method('getConversations')->willReturn(new ArrayCollection());

        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
    }


    























    









































    // =======================================================================
    // Tests getUserConversations
    // =======================================================================

    public function testGetUserConversationsUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface
            {
                return null;
            }
        };

        $response = $ctrl->getUserConversations($this->em);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGetUserConversationsReturnsEmptyArrayWhenNone(): void
    {
        $user = $this->createUser();
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([]));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $ctrl = $this->createControllerWithUser($user);
        $response = $ctrl->getUserConversations($this->em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsSkipsConversationWithInvalidTitle(): void
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
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $ctrl = $this->createControllerWithUser($user);
        $response = $ctrl->getUserConversations($this->em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsReturnsValidConversationWithParticipants(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv = $this->createConversation(10, 'Ma conversation', 'desc', $currentUser, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));

        $userRepo = $this->stubUserRepository(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            User::class => $userRepo,
        });

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Ma conversation', $data[0]['title']);
        $this->assertCount(1, $data[0]['users']);
    }

    public function testGetUserConversationsSkipsParticipantWithInvalidName(): void
    {
        $currentUser = $this->createUser(1);
        $badUser = $this->createUser(2, 'Bad123', 'User', 'bad@example.com');
        $conv = $this->createConversation(10, 'Test', 'desc', $currentUser, [$currentUser, $badUser]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));

        $userRepo = $this->stubUserRepository(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            User::class => $userRepo,
        });

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }

    public function testGetUserConversationsSkipsParticipantWithInvalidEmail(): void
    {
        $currentUser = $this->createUser(1);
        $invalidEmail = $this->createUser(2, 'Marie', 'Curie', 'not-valid-email');
        $conv = $this->createConversation(10, 'Test', 'desc', $currentUser, [$currentUser, $invalidEmail]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));

        $userRepo = $this->stubUserRepository(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            User::class => $userRepo,
        });

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], json_decode($response->getContent(), true));
    }


    public function testGetUserConversationsCreatorNameInvalidFallsBackToUnknown(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $badCreator = $this->createUser(3, 'Bad99', 'Creator', 'bad@example.com');
        $conv = $this->createConversation(10, 'Valid Title', '', $badCreator, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));

        $userRepo = $this->stubUserRepository(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            User::class => $userRepo,
        });

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Unknown', $data[0]['createdBy']);
    }

    public function testGetUserConversationsCreatedByNull(): void
    {
        $currentUser = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv = $this->createConversation(10, 'Valid Title', '', null, [$currentUser, $participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createListQueryBuilder([$conv]));

        $userRepo = $this->stubUserRepository(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            User::class => $userRepo,
        });

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Unknown', $data[0]['createdBy']);
    }

    public function testGetUserConversationsDbException(): void
    {
        $currentUser = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $ctrl = $this->createControllerWithUser($currentUser);
        $response = $ctrl->getUserConversations($em);

        $this->assertSame(500, $response->getStatusCode());
    }

    // =======================================================================
    // Tests getMessages
    // =======================================================================

    public function testGetMessagesUnauthenticated(): void
    {
        $security = $this->createSecurity(null);
        $request = Request::create('/api/get/messages', 'GET');

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGetMessagesValidReturns200(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);
        $msg = $this->createMessage(author: $user, conv: $conv);

        $security = $this->createSecurity($user);
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(1, $data['data']);
    }

    public function testGetMessagesPaginationDefaultClamping(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = Request::create('/api/get/messages', 'GET', ['page' => 0, 'limit' => 999]);

        $this->messageRepo->method('findBy')->willReturn([]);
        $this->messageRepo->method('count')->willReturn(0);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetMessagesSkipsMessageWithInvalidContent(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);
        $msg = $this->createMessage(content: '<script>xss</script>', author: $user, conv: $conv);

        $security = $this->createSecurity($user);
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $data['data']);
    }

    public function testGetMessagesSkipsOrphanMessage(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $msg = $this->createMessage(100, 'Hello world', $user, null);
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $data['data']);
    }

    public function testGetMessagesSkipsMessageWithInvalidConversationTitle(): void
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
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetMessagesAuthorEmailInvalidFallsBack(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);
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
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
    }

    public function testGetMessagesAuthorNameBothPartsInvalidFallsBackToUnknown(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation(creator: $user);
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
        $request = Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]);

        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $response = $this->controller->getMessages($security, $this->messageRepo, $request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data['data']);
    }

    // =======================================================================
    // Tests createConversation
    // =======================================================================

    public function testCreateConversationUnauthenticated(): void
    {
        $security = $this->createSecurity(null);
        $request = $this->createJsonRequest(['title' => 'Hello', 'conv_users' => [2]]);

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateConversationWrongContentTypeReturns415(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['title' => 'Hi']));
        $request->headers->set('Content-Type', 'text/plain');

        $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($this->stubUserRepository(null));

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(415, $response->getStatusCode());
    }

    public function testCreateConversationMissingTitleReturns400(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['conv_users' => [2]]);

        $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($this->stubUserRepository(null));

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateConversationInvalidTitleFormatReturns400(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['title' => '<script>xss</script>', 'conv_users' => [2]]);

        $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($this->stubUserRepository(null));

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateConversationForbiddenExtraFieldsReturns500(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest([
            'title' => 'Valid title',
            'conv_users' => [2],
            'malicious' => 'payload',
        ]);

        $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($this->stubUserRepository(null));

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateConversationPayloadTooLargeReturns413(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $bigPayload = json_encode(['title' => str_repeat('a', 15000), 'conv_users' => [2]]);
        $request = Request::create('/', 'POST', [], [], [], [], $bigPayload);
        $request->headers->set('Content-Type', 'application/json');

        $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($this->stubUserRepository(null));

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(413, $response->getStatusCode());
    }

    public function testCreateConversationRateLimitReturns429(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);

        $userRepo = $this->stubUserRepository(null);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
            default => $this->createMock(EntityRepository::class),
        });

        $request = $this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testCreateConversationInvalidUserStateReturns400(): void
    {
        $user = $this->createUser(firstName: 'Jean99');
        $security = $this->createSecurity($user);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);

        $request = $this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('invalid user state', $data['message']);
    }

    public function testCreateConversationSuccessReturns201(): void
    {
        $creator = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $security = $this->createSecurity($creator);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
        });
        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');

        $request = $this->createJsonRequest(['title' => 'Ma conversation', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Ma conversation', $data['title']);
    }

    public function testCreateConversationDuplicateReturns409(): void
    {
        $creator = $this->createUser(1);
        $security = $this->createSecurity($creator);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$this->createUser(2, 'Marie', 'Curie', 'marie@example.com')]);

        $existingConv = $this->createMock(Conversation::class);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, $existingConv));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
        });
        $this->em->method('beginTransaction');
        $this->em->expects($this->once())->method('rollback');

        $request = $this->createJsonRequest(['title' => 'Ma conversation', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('DUPLICATE_CONVERSATION', $data['error']);
    }

    public function testCreateConversationNoParticipantsStillCreates(): void
    {
        $creator = $this->createUser(1);
        $security = $this->createSecurity($creator);

        $userRepo = $this->stubUserRepository(null);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
        });
        $this->em->method('beginTransaction');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->em->expects($this->once())->method('commit');

        $request = $this->createJsonRequest(['title' => 'Solo', 'conv_users' => []]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreateConversationParticipantsExceedMaxReturns400(): void
    {
        $creator = $this->createUser(1);
        $security = $this->createSecurity($creator);

        $userRepo = $this->stubUserRepository(null);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
        });
        $this->em->method('beginTransaction');

        $request = $this->createJsonRequest(['title' => 'Big group', 'conv_users' => range(2, 52)]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateConversationInvalidJsonReturns400(): void
    {
        $user = $this->createUser(1);
        $security = $this->createSecurity($user);

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->willReturn($userRepo);
        $this->em->method('beginTransaction');

        $request = Request::create('/', 'POST', [], [], [], [], 'not-json');
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->createConversation($request, $security, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    // =======================================================================
    // Tests createMessage
    // =======================================================================

    public function testCreateMessageUnauthenticated(): void
    {
        $security = $this->createSecurity(null);
        $request = $this->createJsonRequest(['content' => 'Hello', 'conversation_id' => 1]);
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateMessageRateLimiterRejectsReturns429(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello', 'conversation_id' => 1]);
        $ctrl = $this->createRejectingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testCreateMessageWrongContentTypeReturns415(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = Request::create('/', 'POST', [], [], [], [], json_encode(['content' => 'Hi', 'conversation_id' => 1]));
        $request->headers->set('Content-Type', 'text/plain');
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(415, $response->getStatusCode());
    }

    public function testCreateMessageMissingContentReturns400(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['conversation_id' => 1]);
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateMessageMissingConversationIdReturns400(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello world']);
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateMessageInvalidContentFormatReturns400(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => '<script>xss</script>', 'conversation_id' => 1]);
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateMessageConversationNotFoundReturns404(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 999]);

        $em = $this->createMock(EntityManagerInterface::class);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn(null);
        $em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $ctrl = $this->createAcceptingController($em);
        $response = $ctrl->createMessage($request, $security, $em, $this->createDummyFactory());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCreateMessageUserNotInConversationReturns403(): void
    {
        $user = $this->createUser(1);
        $otherUser = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $conv = $this->createConversation(users: [$otherUser]);
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]);

        $em = $this->createMock(EntityManagerInterface::class);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $ctrl = $this->createAcceptingController($em);
        $response = $ctrl->createMessage($request, $security, $em, $this->createDummyFactory());

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testCreateMessageDailyRateLimitExceededReturns429(): void
    {
        $user = $this->createUser(1);
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]);

        $em = $this->createMock(EntityManagerInterface::class);
        $conv = $this->createConversation(users: [$user]);

        $collection = $this->createMock(ArrayCollection::class);
        $collection->method('contains')->willReturn(true);
        $conv->method('getUsers')->willReturn($collection);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));

        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });

        $ctrl = $this->createAcceptingController($em);
        $response = $ctrl->createMessage($request, $security, $em, $this->createDummyFactory());

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testCreateMessageSuccessReturns201(): void
    {
        $user = $this->createUser(1);
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest(['content' => 'Hello world', 'conversation_id' => 10]);

        $em = $this->createMock(EntityManagerInterface::class);
        $conv = $this->createConversation(10, 'Ma conv', '', $user, [$user]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $ctrl = $this->createAcceptingController($em);
        $response = $ctrl->createMessage($request, $security, $em, $this->createDummyFactory());

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('content', $data);
    }

    public function testCreateMessageForbiddenExtraFieldsThrowsException(): void
    {
        $user = $this->createUser();
        $security = $this->createSecurity($user);
        $request = $this->createJsonRequest([
            'content' => 'Hello world',
            'conversation_id' => 1,
            'injected_field' => 'bad',
        ]);
        $ctrl = $this->createAcceptingController();

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testCreateMessageLargePayloadReturns413(): void
    {
        $user = $this->createUser(1);
        $security = $this->createSecurity($user);
        $ctrl = $this->createAcceptingController();

        $request = Request::create('/', 'POST', [], [], [], [], str_repeat('x', 10001));
        $request->headers->set('Content-Type', 'application/json');

        $response = $ctrl->createMessage($request, $security, $this->em, $this->createDummyFactory());
        $this->assertSame(413, $response->getStatusCode());
    }

    // =======================================================================
    // Tests deleteConversation
    // =======================================================================

    public function testDeleteConversationUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface
            {
                return null;
            }
        };

        $response = $ctrl->deleteConversation(1, $this->em);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testDeleteConversationInvalidIdReturns400(): void
    {
        $user = $this->createUser();
        $ctrl = $this->createControllerWithUser($user);

        $response = $ctrl->deleteConversation(0, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteConversationNotFoundReturns404(): void
    {
        $user = $this->createUser();
        $ctrl = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $response = $ctrl->deleteConversation(999, $this->em);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteConversationForbiddenForNonCreatorReturns403(): void
    {
        $user = $this->createUser(1);
        $creator = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $conv = $this->createConversation(10, 'Test', '', $creator, [], []);
        $ctrl = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testDeleteConversationTooManyMessagesReturns413(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $maxMessages = (new \ReflectionClassConstant(
            \App\BusinessLimits::class,
            'CONVERSATION_MAX_MESSAGES_FOR_DELETE'
        ))->getValue();

        $messages = array_fill(0, $maxMessages + 1, $this->createStub(Message::class));
        $messagesCollection = new ArrayCollection($messages);

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Test');
        $conv->method('getDescription')->willReturn('');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$user]));
        $conv->method('getMessages')->willReturn($messagesCollection);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(413, $response->getStatusCode());
    }

    public function testDeleteConversationSuccessReturns200(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createConversation(10, 'Test', '', $user, [], []);
        $ctrl = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });
        $this->em->expects($this->once())->method('remove')->with($conv);
        $this->em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', strtolower($data['message']));
    }

    public function testDeleteConversationAlsoDeletesMessages(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

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

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });

        $this->em->expects($this->atLeast(3))->method('remove');
        $this->em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['deletedMessages']);
    }

    public function testDeleteConversationWithInvalidTitleLogsWarning(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('<script>bad</script>');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });
        $this->em->method('remove');
        $this->em->method('flush');

        $this->papertrail->expects($this->atLeastOnce())->method('warning');

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteConversationDbExceptionReturns500(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willThrowException(new \Exception('DB failure'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(500, $response->getStatusCode());
    }

    // =======================================================================
    // Tests deleteMessage
    // =======================================================================

    public function testDeleteMessageUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail) extends MessageController {
            public function getUser(): ?UserInterface
            {
                return null;
            }
        };

        $response = $ctrl->deleteMessage(1, $this->em);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testDeleteMessageInvalidIdReturns400(): void
    {
        $user = $this->createUser();
        $ctrl = $this->createControllerWithUser($user);

        $response = $ctrl->deleteMessage(0, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteMessageOutOfRangeIdReturns400(): void
    {
        $user = $this->createUser();
        $ctrl = $this->createControllerWithUser($user);

        $response = $ctrl->deleteMessage(PHP_INT_MAX, $this->em);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteMessageNotFoundReturns404(): void
    {
        $user = $this->createUser();
        $ctrl = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn(null);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $response = $ctrl->deleteMessage(1, $this->em);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteMessageForbiddenForNonAuthorReturns403(): void
    {
        $user = $this->createUser(1);
        $author = $this->createUser(2, 'Other', 'User', 'other@example.com');
        $conv = $this->createConversation();
        $message = $this->createMessage(100, 'Hello', $author, $conv);

        $ctrl = $this->createControllerWithUser($user);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $response = $ctrl->deleteMessage(100, $this->em);
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testDeleteMessageSuccessReturns200(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createConversation(10, 'Test', '', $user);
        $message = $this->createMessage(100, 'Hello world', $user, $conv);
        $ctrl = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);
        $this->em->expects($this->once())->method('remove')->with($message);
        $this->em->expects($this->once())->method('flush');

        $response = $ctrl->deleteMessage(100, $this->em);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteMessageDbExceptionReturns500(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willThrowException(new \Exception('DB failure'));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $response = $ctrl->deleteMessage(1, $this->em);
        $this->assertSame(500, $response->getStatusCode());
    }

    public function testDeleteMessageWithInvalidContentLogsWarning(): void
    {
        $user = $this->createUser(1);
        $conv = $this->createConversation(10, 'Test', '', $user);
        $message = $this->createMessage(100, '<script>xss</script>', $user, $conv);
        $ctrl = $this->createControllerWithUser($user);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);
        $this->em->expects($this->once())->method('remove');
        $this->em->expects($this->once())->method('flush');

        $this->papertrail->expects($this->atLeastOnce())->method('warning');

        $response = $ctrl->deleteMessage(100, $this->em);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteMessageNoConversationReturns500(): void
    {
        $user = $this->createUser(1);
        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn(1);
        $message->method('getContent')->willReturn('Hello world');
        $message->method('getAuthor')->willReturn($user);
        $message->method('getConversation')->willReturn(null);

        $ctrl = $this->createControllerWithUser($user);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($message);
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $response = $ctrl->deleteMessage(1, $this->em);
        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid message conversation', $data['message']);
    }

    // =======================================================================
    // Tests des méthodes privées via Reflection
    // =======================================================================

    private function callValidateAvailabilityDates(?\DateTimeImmutable $min, ?\DateTimeImmutable $max): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateAvailabilityDates');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $min, $max);
    }

    public function testValidateAvailabilityDatesBothNull(): void
    {
        $result = $this->callValidateAvailabilityDates(null, null);
        $this->assertFalse($result['valid']);
    }

    public function testValidateAvailabilityDatesOnlyMinDefined(): void
    {
        $result = $this->callValidateAvailabilityDates(new \DateTimeImmutable('+1 day'), null);
        $this->assertFalse($result['valid']);
    }

    public function testValidateAvailabilityDatesOnlyMaxDefined(): void
    {
        $result = $this->callValidateAvailabilityDates(null, new \DateTimeImmutable('+2 days'));
        $this->assertFalse($result['valid']);
    }

    public function testValidateAvailabilityDatesMaxBeforeMin(): void
    {
        $result = $this->callValidateAvailabilityDates(
            new \DateTimeImmutable('+10 days'),
            new \DateTimeImmutable('+1 day')
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('after', $result['error']);
    }

    public function testValidateAvailabilityDatesRangeExceedsTwoYears(): void
    {
        $result = $this->callValidateAvailabilityDates(
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable('+800 days')
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 years', $result['error']);
    }

    public function testValidateAvailabilityDatesMaxInPast(): void
    {
        $result = $this->callValidateAvailabilityDates(
            new \DateTimeImmutable('-10 days'),
            new \DateTimeImmutable('-1 day')
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('future', $result['error']);
    }

    public function testValidateAvailabilityDatesValidRange(): void
    {
        $result = $this->callValidateAvailabilityDates(
            new \DateTimeImmutable('+1 day'),
            new \DateTimeImmutable('+30 days')
        );
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    private function callValidateConversationParticipants(User $creator, array $userIds): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateConversationParticipants');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $creator, $userIds, $this->em);
    }

    public function testValidateConversationParticipantsTooMany(): void
    {
        $creator = $this->createUser(1);
        $ids = range(2, 52);
        $result = $this->callValidateConversationParticipants($creator, $ids);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('50', $result['error']);
    }

    public function testValidateConversationParticipantsEmpty(): void
    {
        $creator = $this->createUser(1);
        $result = $this->callValidateConversationParticipants($creator, []);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('2 participants', $result['error']);
    }

    public function testValidateConversationParticipantsCreatorFilteredOut(): void
    {
        $creator = $this->createUser(1);
        $result = $this->callValidateConversationParticipants($creator, [1]);
        $this->assertFalse($result['valid']);
    }

    public function testValidateConversationParticipantsNonNumericSkipped(): void
    {
        $creator = $this->createUser(1);
        $result = $this->callValidateConversationParticipants($creator, ['abc', 'xyz']);
        $this->assertFalse($result['valid']);
    }

    public function testValidateConversationParticipantsMissingUsers(): void
    {
        $creator = $this->createUser(1);
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findBy')->willReturn([]);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateConversationParticipants($creator, [2, 3]);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user IDs', $result['error']);
    }

    public function testValidateConversationParticipantsValid(): void
    {
        $creator = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findBy')->willReturn([$participant]);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateConversationParticipants($creator, [2]);
        $this->assertTrue($result['valid']);
        $this->assertCount(1, $result['validUsers']);
    }

    private function callValidateEmail(string $email, int $userId): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateEmailandUniqueness');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $email, $userId, $this->em);
    }

    public function testValidateEmailTooLong(): void
    {
        $email = str_repeat('a', 250) . '@b.com';
        $result = $this->callValidateEmail($email, 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('255', $result['error']);
    }

    public function testValidateEmailInvalidFormat(): void
    {
        $result = $this->callValidateEmail('not-an-email', 1);
        $this->assertFalse($result['valid']);
    }

    public function testValidateEmailAlreadyUsedByOtherUser(): void
    {
        $otherUser = $this->createUser(99);
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($otherUser);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateEmail('taken@example.com', 1);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already in use', $result['error']);
    }

    public function testValidateEmailSameUserAllowed(): void
    {
        $currentUser = $this->createUser(1);
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($currentUser);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateEmail('jean@example.com', 1);
        $this->assertTrue($result['valid']);
    }

    private function callCanonicalDecode(string $input): string
    {
        $method = new \ReflectionMethod(MessageController::class, 'canonicalDecode');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $input);
    }

    public function testCanonicalDecodeHtmlEntities(): void
    {
        $result = $this->callCanonicalDecode('Hello &amp; World');
        $this->assertSame('Hello & World', $result);
    }

    public function testCanonicalDecodeUrlEncoded(): void
    {
        $result = $this->callCanonicalDecode('Hello%20World');
        $this->assertSame('Hello World', $result);
    }

    public function testCanonicalDecodeDoubleEncoded(): void
    {
        $result = $this->callCanonicalDecode('Hello%20%26%20World');
        $this->assertSame('Hello & World', $result);
    }

    public function testCanonicalDecodeThrowsOnTooLongInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->callCanonicalDecode(str_repeat('a', 200001));
    }

    public function testCanonicalDecodePlainStringUnchanged(): void
    {
        $result = $this->callCanonicalDecode('Hello World');
        $this->assertSame('Hello World', $result);
    }

    private function callValidateString(string $value, int $maxLength): bool
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateString');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $value, $maxLength);
    }

    public function testValidateStringEmpty(): void
    {
        $this->assertFalse($this->callValidateString('', 100));
    }

    public function testValidateStringTooLong(): void
    {
        $this->assertFalse($this->callValidateString(str_repeat('a', 101), 100));
    }

    public function testValidateStringDangerousFirstCharCsv(): void
    {
        $this->assertFalse($this->callValidateString('=SUM(A1)', 100));
    }

    public function testValidateStringXssScript(): void
    {
        $this->assertFalse($this->callValidateString('<script>alert(1)</script>', 100));
    }

    public function testValidateStringSqlSelect(): void
    {
        $this->assertFalse($this->callValidateString('select * from users', 100));
    }

    public function testValidateStringSqlUnion(): void
    {
        $this->assertFalse($this->callValidateString('union select password', 100));
    }

    public function testValidateStringSqlDrop(): void
    {
        $this->assertFalse($this->callValidateString('drop table users', 100));
    }

    public function testValidateStringIframe(): void
    {
        $this->assertFalse($this->callValidateString('<iframe src="x"></iframe>', 100));
    }

    public function testValidateStringJavascriptProtocol(): void
    {
        $this->assertFalse($this->callValidateString('javascript:alert(1)', 100));
    }

    public function testValidateStringOnEventHandler(): void
    {
        $this->assertFalse($this->callValidateString('onload=bad', 100));
    }

    public function testValidateStringValid(): void
    {
        $this->assertTrue($this->callValidateString('Hello world, this is valid!', 100));
    }

    public function testValidateStringWithNewline(): void
    {
        $this->assertTrue($this->callValidateString("Hello\nworld", 100));
    }

    private function callValidateName(string $name, int $maxLength = 20): bool
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateName');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $name, $maxLength);
    }

    public function testValidateNameEmpty(): void
    {
        $this->assertFalse($this->callValidateName(''));
    }

    public function testValidateNameTooShort(): void
    {
        $this->assertFalse($this->callValidateName('A'));
    }

    public function testValidateNameTooLong(): void
    {
        $this->assertFalse($this->callValidateName(str_repeat('a', 21)));
    }

    public function testValidateNameStartsWithHyphen(): void
    {
        $this->assertFalse($this->callValidateName('-Jean'));
    }

    public function testValidateNameEndsWithSpace(): void
    {
        $this->assertFalse($this->callValidateName('Jean '));
    }

    public function testValidateNameContainsDigit(): void
    {
        $this->assertFalse($this->callValidateName('Jean2'));
    }

    public function testValidateNameContainsDangerousChar(): void
    {
        $this->assertFalse($this->callValidateName('Jean<Luc'));
    }

    public function testValidateNameThreeConsecutiveSpecialChars(): void
    {
        $this->assertFalse($this->callValidateName("Jean---Luc"));
    }

    public function testValidateNameValidWithHyphen(): void
    {
        $this->assertTrue($this->callValidateName('Jean-Luc'));
    }

    public function testValidateNameValidWithApostrophe(): void
    {
        $this->assertTrue($this->callValidateName("O'Brien"));
    }

    public function testValidateNameValidAccented(): void
    {
        $this->assertTrue($this->callValidateName('Éléonore'));
    }

    public function testValidateNameDangerousCharAmpersand(): void
    {
        $this->assertFalse($this->callValidateName('Jean&Luc'));
    }

    public function testValidateNameDangerousCharAt(): void
    {
        $this->assertFalse($this->callValidateName('Jean@Luc'));
    }

    public function testValidateNameDangerousCharSlash(): void
    {
        $this->assertFalse($this->callValidateName('Jean/Luc'));
    }

    private function callSanitizeHtml(?string $html, bool $allowFormatting = false): string
    {
        $method = new \ReflectionMethod(MessageController::class, 'sanitizeHtml');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $html, $allowFormatting);
    }

    public function testSanitizeHtmlNullReturnsEmpty(): void
    {
        $this->assertSame('', $this->callSanitizeHtml(null));
    }

    public function testSanitizeHtmlEmptyReturnsEmpty(): void
    {
        $this->assertSame('', $this->callSanitizeHtml(''));
    }

    public function testSanitizeHtmlStripsAllTagsNoFormatting(): void
    {
        $result = $this->callSanitizeHtml('<strong>Hello</strong> <script>bad</script>', false);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testSanitizeHtmlAllowsStrongWithFormatting(): void
    {
        $result = $this->callSanitizeHtml('<strong>Hello</strong>', true);
        $this->assertStringContainsString('<strong>Hello</strong>', $result);
    }

    public function testSanitizeHtmlStripsScriptWithFormatting(): void
    {
        $result = $this->callSanitizeHtml('<strong>ok</strong><script>bad</script>', true);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('<strong>ok</strong>', $result);
    }

    public function testSanitizeHtmlBlocksExternalLinks(): void
    {
        $result = $this->callSanitizeHtml('<a href="https://evil.com">click</a>', true);
        $this->assertStringNotContainsString('evil.com', $result);
    }

    private function callSanitizeForJson(mixed $value): mixed
    {
        $method = new \ReflectionMethod(MessageController::class, 'sanitizeForJson');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $value);
    }

    public function testSanitizeForJsonNull(): void
    {
        $this->assertNull($this->callSanitizeForJson(null));
    }

    public function testSanitizeForJsonArray(): void
    {
        $result = $this->callSanitizeForJson(["hello\x00world", "clean"]);
        $this->assertSame(['helloworld', 'clean'], $result);
    }

    public function testSanitizeForJsonStripsControlChars(): void
    {
        $result = $this->callSanitizeForJson("hello\x00\x01\x1Fworld");
        $this->assertSame('helloworld', $result);
    }

    public function testSanitizeForJsonPreservesNewlineTabCr(): void
    {
        $result = $this->callSanitizeForJson("hello\n\t\rworld");
        $this->assertSame("hello\n\t\rworld", $result);
    }

    public function testSanitizeForJsonInteger(): void
    {
        $this->assertSame(42, $this->callSanitizeForJson(42));
    }

    private function callSanitizeData(array $data): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'sanitizeData');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $data);
    }

    public function testSanitizeDataTitle(): void
    {
        $result = $this->callSanitizeData(['title' => '<b>hello</b>']);
        $this->assertArrayHasKey('title', $result);
        $this->assertStringNotContainsString('<b>', $result['title']);
    }

    public function testSanitizeDataDescription(): void
    {
        $result = $this->callSanitizeData(['description' => '<strong>ok</strong><script>bad</script>']);
        $this->assertArrayHasKey('description', $result);
        $this->assertStringNotContainsString('<script>', $result['description']);
    }

    public function testSanitizeDataContent(): void
    {
        $result = $this->callSanitizeData(['content' => '<em>text</em>']);
        $this->assertArrayHasKey('content', $result);
    }

    public function testSanitizeDataConvUsersDeduplicatesAndFilters(): void
    {
        $result = $this->callSanitizeData(['conv_users' => [1, 2, 2, 0, -1, 3]]);
        $this->assertEqualsCanonicalizing([1, 2, 3], array_values($result['conv_users']));
    }

    public function testSanitizeDataConversationId(): void
    {
        $result = $this->callSanitizeData(['conversation_id' => '42']);
        $this->assertSame(42, $result['conversation_id']);
    }

    private function callValidateMessageRateLimit(User $user, Conversation $conv): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateMessageRateLimit');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $user, $conv, $this->em);
    }

    public function testValidateMessageRateLimitUnderLimit(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation();

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(5));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $result = $this->callValidateMessageRateLimit($user, $conv);
        $this->assertTrue($result['valid']);
    }

    public function testValidateMessageRateLimitAtLimit(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation();

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(100));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $result = $this->callValidateMessageRateLimit($user, $conv);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit', strtolower($result['error']));
    }

    public function testValidateMessageRateLimitDbExceptionFailsOpen(): void
    {
        $user = $this->createUser();
        $conv = $this->createConversation();

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);

        $result = $this->callValidateMessageRateLimit($user, $conv);
        $this->assertTrue($result['valid']);
    }

    private function callValidateConversationDeleteRateLimit(User $user): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateConversationDeleteRateLimit');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $user, $this->em);
    }

    public function testValidateConversationDeleteRateLimitAlwaysValid(): void
    {
        $user = $this->createUser();
        $result = $this->callValidateConversationDeleteRateLimit($user);
        $this->assertTrue($result['valid']);
    }

    private function callValidateConversationCreateRateLimit(User $user): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateConversationCreateRateLimit');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $user, $this->em);
    }

    public function testValidateConversationCreateRateLimitUnderLimit(): void
    {
        $user = $this->createUser();

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $result = $this->callValidateConversationCreateRateLimit($user);
        $this->assertTrue($result['valid']);
    }

    public function testValidateConversationCreateRateLimitExceeded(): void
    {
        $user = $this->createUser();

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(999));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $result = $this->callValidateConversationCreateRateLimit($user);
        $this->assertFalse($result['valid']);
    }

    public function testValidateConversationCreateRateLimitDbExceptionFailsOpen(): void
    {
        $user = $this->createUser();

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $result = $this->callValidateConversationCreateRateLimit($user);
        $this->assertTrue($result['valid']);
    }

    private function callGenerateConversationHash(array $userIds, string $title): string
    {
        $method = new \ReflectionMethod(MessageController::class, 'generateConversationHash');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $userIds, $title);
    }

    public function testGenerateConversationHashIsDeterministic(): void
    {
        $hash1 = $this->callGenerateConversationHash([1, 2, 3], 'My Conv');
        $hash2 = $this->callGenerateConversationHash([3, 1, 2], 'My Conv');
        $this->assertSame($hash1, $hash2);
    }

    public function testGenerateConversationHashDiffersOnTitle(): void
    {
        $hash1 = $this->callGenerateConversationHash([1, 2], 'Title A');
        $hash2 = $this->callGenerateConversationHash([1, 2], 'Title B');
        $this->assertNotSame($hash1, $hash2);
    }

    public function testGenerateConversationHashNormalizesCase(): void
    {
        $hash1 = $this->callGenerateConversationHash([1, 2], 'My Conv');
        $hash2 = $this->callGenerateConversationHash([1, 2], 'MY CONV');
        $this->assertSame($hash1, $hash2);
    }

    public function testGenerateConversationHashNormalizesWhitespace(): void
    {
        $hash1 = $this->callGenerateConversationHash([1, 2], 'My  Conv');
        $hash2 = $this->callGenerateConversationHash([1, 2], 'My Conv');
        $this->assertSame($hash1, $hash2);
    }

    private function callCheckDuplicateConversation(User $creator, array $userIds, string $title): bool
    {
        $method = new \ReflectionMethod(MessageController::class, 'checkDuplicateConversation');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $creator, $userIds, $title, $this->em);
    }

    public function testCheckDuplicateConversationNoDuplicate(): void
    {
        $creator = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, null));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertFalse($this->callCheckDuplicateConversation($creator, [2], 'Test'));
    }

    public function testCheckDuplicateConversationFound(): void
    {
        $creator = $this->createUser(1);
        $existingConv = $this->createMock(Conversation::class);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0, $existingConv));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertTrue($this->callCheckDuplicateConversation($creator, [2], 'Test'));
    }

    public function testCheckDuplicateConversationDbExceptionReturnsFalse(): void
    {
        $creator = $this->createUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $this->assertFalse($this->callCheckDuplicateConversation($creator, [2], 'Test'));
    }

    private function callValidateUserState(User $user): array
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $user);
    }

    public function testValidateUserStateValidUser(): void
    {
        $user = $this->createUser();
        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateUserState($user);
        $this->assertTrue($result['valid']);
    }

    public function testValidateUserStateInvalidFirstName(): void
    {
        $user = $this->createUser(firstName: 'Jean99');
        $result = $this->callValidateUserState($user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user name', $result['error']);
    }

    public function testValidateUserStateInvalidLastName(): void
    {
        $user = $this->createUser(lastName: 'Dupont<>');
        $result = $this->callValidateUserState($user);
        $this->assertFalse($result['valid']);
    }

    public function testValidateUserStateInvalidEmail(): void
    {
        $user = $this->createUser(email: 'bad-email');
        $result = $this->callValidateUserState($user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid user email', $result['error']);
    }

    public function testValidateUserStateInvalidDates(): void
    {
        $user = $this->createUser(
            availabilityStart: new \DateTimeImmutable('+10 days'),
            availabilityEnd: new \DateTimeImmutable('+1 day')
        );

        $userRepo = $this->stubUserRepository(null);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $result = $this->callValidateUserState($user);
        $this->assertFalse($result['valid']);
        $this->assertSame('Invalid availability dates', $result['error']);
    }


    // =======================================================================
// Tests supplémentaires pour améliorer la couverture de code
// =======================================================================

    /**
     * Test validateConversationDeleteRateLimit - méthode avec faible couverture
     */
    public function testValidateConversationDeleteRateLimit(): void
    {
        $user = $this->createUser();

        // Appel de la méthode privée via réflexion
        $method = new \ReflectionMethod(MessageController::class, 'validateConversationDeleteRateLimit');
        $method->setAccessible(true);

        // Test avec un utilisateur normal
        $result = $method->invoke($this->controller, $user, $this->em);
        $this->assertTrue($result['valid']);

        // Test avec une exception (fail-open)
        $emWithException = $this->createMock(EntityManagerInterface::class);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB error'));
        $emWithException->method('getRepository')->with(Conversation::class)->willReturn($convRepo);

        $result = $method->invoke($this->controller, $user, $emWithException);
        $this->assertTrue($result['valid']); // Fail-open
    }


    /**
     * Test validateUserState - atteindre les branches manquantes
     */
    public function testValidateUserStateEdgeCases(): void
    {
        $method = new \ReflectionMethod(MessageController::class, 'validateUserState');
        $method->setAccessible(true);

        // Test avec un nom valide mais prénom invalide (déjà couvert)
        // Test avec email déjà utilisé par un autre utilisateur
        $user = $this->createUser(1, 'Jean', 'Dupont', 'jean@example.com');

        $otherUser = $this->createUser(2, 'Autre', 'Utilisateur', 'autre@example.com');
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($otherUser);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        // Modifier l'email pour qu'il soit valide mais déjà pris
        $userWithTakenEmail = $this->createUser(1, 'Jean', 'Dupont', 'autre@example.com');

        $result = $method->invoke($this->controller, $userWithTakenEmail);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user email', $result['error']);

        // Test avec une exception lors de la validation d'email
        $userRepoException = $this->createMock(EntityRepository::class);
        $userRepoException->method('findOneBy')->willThrowException(new \Exception('DB error'));
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepoException);

        $result = $method->invoke($this->controller, $user);
        $this->assertFalse($result['valid']); // Doit échouer en cas d'exception
    }

    /**
     * Test getConnectedUser - branches manquantes
     */
    public function testGetConnectedUserWithInvalidEmailUniqueness(): void
    {
        // Créer un utilisateur avec un email valide mais déjà utilisé par un autre
        $user = $this->createUser(1, 'Jean', 'Dupont', 'jean@example.com');

        $otherUser = $this->createUser(2, 'Autre', 'Utilisateur', 'jean@example.com'); // Même email
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn($otherUser);
        $this->em->method('getRepository')->with(User::class)->willReturn($userRepo);

        $ctrl = new class($this->messageRepo, $this->em, $this->papertrail, $user) extends MessageController {
            private UserInterface $user;
            public function __construct($repo, $em, $pt, $user)
            {
                parent::__construct($repo, $em, $pt);
                $this->user = $user;
            }
            public function getUser(): ?UserInterface
            {
                return $this->user;
            }
        };

        $response = $ctrl->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
    }


    public function testCreateConversationWithTransactionException(): void
    {
        $creator = $this->createUser(1);
        $participant = $this->createUser(2, 'Marie', 'Curie', 'marie@example.com');
        $security = $this->createSecurity($creator);

        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$participant]);

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->createCountQueryBuilder(0));

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            User::class => $userRepo,
            Conversation::class => $convRepo,
        });
        $this->em->method('beginTransaction');
        $this->em->method('persist')->willThrowException(new \Exception('Transaction error'));
        $this->em->expects($this->once())->method('rollback');

        $request = $this->createJsonRequest(['title' => 'Test', 'conv_users' => [2]]);
        $response = $this->controller->createConversation($request, $security, $this->em);

        $this->assertSame(500, $response->getStatusCode());
    }

    /**
     * Test deleteConversation - branches manquantes
     */
    public function testDeleteConversationWithNoMessages(): void
    {
        $user = $this->createUser(1);
        $ctrl = $this->createControllerWithUser($user);

        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Test');
        $conv->method('getDescription')->willReturn('');
        $conv->method('getCreatedBy')->willReturn($user);
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));

        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);

        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
            Conversation::class => $convRepo,
            Message::class => $msgRepo,
        });
        $this->em->expects($this->once())->method('remove')->with($conv);
        $this->em->expects($this->once())->method('flush');

        $response = $ctrl->deleteConversation(10, $this->em);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['deletedMessages']);
    }








    public function testdeleteConversation(): void
    {
            $user = $this->createUser(1);
            $ctrl = $this->createControllerWithUser($user);
    
            $conv = $this->createMock(Conversation::class);
            $conv->method('getId')->willReturn(10);
            $conv->method('getTitle')->willReturn('Test');
            $conv->method('getDescription')->willReturn('');
            $conv->method('getCreatedBy')->willReturn($user);
            $conv->method('getMessages')->willReturn(new ArrayCollection([]));
    
            $convRepo = $this->createMock(EntityRepository::class);
            $convRepo->method('find')->willReturn($conv);
    
            $msgRepo = $this->createMock(EntityRepository::class);
            $msgRepo->method('findBy')->willReturn([]);
    
            $this->em->method('getRepository')->willReturnCallback(fn($class) => match ($class) {
                Conversation::class => $convRepo,
                Message::class => $msgRepo,
            });
            $this->em->expects($this->once())->method('remove')->with($conv);
            $this->em->expects($this->once())->method('flush');
    
            $response = $ctrl->deleteConversation(10, $this->em);
            $this->assertSame(200, $response->getStatusCode());
    }




























    public function testGetUserConversationsWithValidData(): void
{
    // Créer les mocks nécessaires
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $papertrail = $this->createMock(PapertrailService::class);
    
    // Créer un mock partiel du contrôleur
    $controller = $this->getMockBuilder(MessageController::class)
        ->setConstructorArgs([$this->messageRepo, $entityManager, $papertrail])
        ->onlyMethods(['getUser'])
        ->getMock();
    
    // Créer le mock de l'utilisateur
    $user = $this->createMock(User::class);
    $user->method('getId')->willReturn(1);
    $user->method('getEmail')->willReturn('jean.dupont@example.com');
    $user->method('getFirstName')->willReturn('Jean');
    $user->method('getLastName')->willReturn('Dupont');
    $user->method('getUserIdentifier')->willReturn('jean.dupont@example.com');
    
    // Configurer le mock pour retourner l'utilisateur
    $controller->method('getUser')->willReturn($user);
    
    // Créer un mock de conversation valide
    $conversation = $this->createMock(Conversation::class);
    $conversation->method('getId')->willReturn(10);
    $conversation->method('getTitle')->willReturn('Ma conversation');
    $conversation->method('getDescription')->willReturn('Description valide');
    $conversation->method('getCreatedBy')->willReturn($user);
    $conversation->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01'));
    $conversation->method('getLastMessageAt')->willReturn(new \DateTimeImmutable('2024-06-01'));
    
    // Mock des participants
    $participant = $this->createMock(User::class);
    $participant->method('getId')->willReturn(2);
    $participant->method('getEmail')->willReturn('marie@example.com');
    $participant->method('getFirstName')->willReturn('Marie');
    $participant->method('getLastName')->willReturn('Curie');
    
    // IMPORTANT: Configurer la collection d'utilisateurs
    $usersCollection = new ArrayCollection([$user, $participant]);
    $conversation->method('getUsers')->willReturn($usersCollection);
    
    // Mock du repository User pour la validation d'email
    $userRepo = $this->createMock(EntityRepository::class);
    
    // Configuration pour que findOneBy retourne null (email unique)
    $userRepo->method('findOneBy')
        ->willReturnCallback(function($criteria) use ($user, $participant) {
            // Si l'email correspond à l'utilisateur courant, retourner l'utilisateur
            if (isset($criteria['email']) && $criteria['email'] === 'jean.dupont@example.com') {
                return $user;
            }
            // Si l'email correspond au participant, retourner le participant
            if (isset($criteria['email']) && $criteria['email'] === 'marie@example.com') {
                return $participant;
            }
            // Sinon, aucun utilisateur existant
            return null;
        });
    
    // Créer un mock du repository de conversation
    $convRepo = $this->createMock(EntityRepository::class);
    
    // Configurer le QueryBuilder pour retourner notre conversation
    $queryBuilder = $this->createMock(QueryBuilder::class);
    $queryBuilder->method('select')->willReturnSelf();
    $queryBuilder->method('where')->willReturnSelf();
    $queryBuilder->method('andWhere')->willReturnSelf();
    $queryBuilder->method('setParameter')->willReturnSelf();
    $queryBuilder->method('innerJoin')->willReturnSelf();
    $queryBuilder->method('orderBy')->willReturnSelf();
    
    // Créer un mock de Query qui retourne nos conversations
    $query = $this->createMock(Query::class);
    $query->method('getResult')->willReturn([$conversation]);
    
    $queryBuilder->method('getQuery')->willReturn($query);
    $convRepo->method('createQueryBuilder')->willReturn($queryBuilder);
    
    // Configurer l'entity manager
    $entityManager->method('getRepository')
        ->willReturnCallback(function($class) use ($convRepo, $userRepo) {
            if ($class === Conversation::class) {
                return $convRepo;
            }
            if ($class === User::class) {
                return $userRepo;
            }
            return $this->createMock(EntityRepository::class);
        });
    
    // Mock du container
    $container = $this->createMock(ContainerInterface::class);
    $container->method('has')->willReturn(false);
    $controller->setContainer($container);
    
    // Appeler la méthode testée
    $response = $controller->getUserConversations($entityManager);
    
    // Déboguer si nécessaire
    if ($response->getStatusCode() !== 200) {
        $content = json_decode($response->getContent(), true);
        var_dump('Status: ' . $response->getStatusCode());
        var_dump('Response: ', $content);
    }
    
    // Assertions sur la réponse
    $this->assertSame(200, $response->getStatusCode());
    
    // Vérifier le contenu de la réponse
    $data = json_decode($response->getContent(), true);
    $this->assertIsArray($data);
    $this->assertCount(1, $data);
    $this->assertSame('Ma conversation', $data[0]['title']);
    $this->assertSame('Jean Dupont', $data[0]['createdBy']);
    $this->assertArrayHasKey('users', $data[0]);
    $this->assertCount(1, $data[0]['users']); // Seulement les participants avec données valides
}


}
