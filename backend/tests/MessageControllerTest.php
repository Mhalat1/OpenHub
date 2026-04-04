<?php

namespace App\Tests\Controller;

use App\Controller\MessageController;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\AxiomService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TestableMessageController extends MessageController
{
    public function __construct(MessageRepository $r, EntityManagerInterface $em, AxiomService $pt, private readonly bool $accept = true)
    {
        parent::__construct($r, $em, $pt);
    }

    public function createMessage(Request $request, Security $security, EntityManagerInterface $em, RateLimiterFactory $f): JsonResponse
    {
        $storage = new InMemoryStorage();
        $factory = $this->accept
            ? new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], $storage)
            : new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'], $storage);
        if (!$this->accept) $factory->create($security->getUser()?->getUserIdentifier() ?? 'anon')->consume(1);
        return parent::createMessage($request, $security, $em, $factory);
    }
}

class StubQuery extends Query
{
    public function __construct(private mixed $scalar = 0, private mixed $single = null, private array $list = []) {}
    public function getSingleScalarResult(): mixed { return $this->scalar; }
    public function getOneOrNullResult(mixed $h = null): mixed { return $this->single; }
    public function getResult(mixed $h = 1): array { return $this->list; }
    public function execute(mixed $p = null, mixed $h = null): mixed { return $this->list; }
    public function getSQL(): string { return ''; }
    protected function _doExecute(): int { return 0; }
}

class MessageControllerTest extends WebTestCase
{
    private MessageRepository&MockObject $messageRepo;
    private EntityManagerInterface&MockObject $em;
    private AxiomService&MockObject $Axiom;
    private MessageController $controller;

    protected function setUp(): void
    {
        $this->messageRepo = $this->createMock(MessageRepository::class);
        $this->em          = $this->createMock(EntityManagerInterface::class);
        $this->Axiom  = $this->createMock(AxiomService::class);
        $this->controller  = new MessageController($this->messageRepo, $this->em, $this->Axiom);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function makeUser(int $id = 1, string $first = 'Jean', string $last = 'Dupont', string $email = 'jean.dupont@example.com', ?\DateTimeImmutable $start = null, ?\DateTimeImmutable $end = null, array $conversations = []): User&MockObject
    {
        $u = $this->createMock(User::class);
        $u->method('getId')->willReturn($id);
        $u->method('getFirstName')->willReturn($first);
        $u->method('getLastName')->willReturn($last);
        $u->method('getEmail')->willReturn($email);
        $u->method('getUserIdentifier')->willReturn($email);
        $u->method('getAvailabilityStart')->willReturn($start);
        $u->method('getAvailabilityEnd')->willReturn($end);
        $u->method('getConversations')->willReturn(new ArrayCollection($conversations));
        return $u;
    }

    public function makeConv(int $id = 10, string $title = 'Test Conv', string $desc = '', ?User $creator = null, array $users = [], array $messages = []): Conversation&MockObject
    {
        $c = $this->createMock(Conversation::class);
        $c->method('getId')->willReturn($id);
        $c->method('getTitle')->willReturn($title);
        $c->method('getDescription')->willReturn($desc);
        $c->method('getCreatedBy')->willReturn($creator);
        $c->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-01-01'));
        $c->method('getLastMessageAt')->willReturn(null);
        $c->method('getUsers')->willReturn(new ArrayCollection($users));
        $c->method('getMessages')->willReturn(new ArrayCollection($messages));
        return $c;
    }

    public function makeMsg(int $id = 100, string $content = 'Hello world', ?User $author = null, ?Conversation $conv = null): Message&MockObject
    {
        $m = $this->createMock(Message::class);
        $m->method('getId')->willReturn($id);
        $m->method('getContent')->willReturn($content);
        $m->method('getAuthor')->willReturn($author);
        $m->method('getAuthorName')->willReturn($author ? $author->getFirstName().' '.$author->getLastName() : 'Unknown');
        $m->method('getConversation')->willReturn($conv);
        $m->method('getConversationTitle')->willReturn($conv?->getTitle() ?? '');
        $m->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2024-06-01'));
        return $m;
    }

    public function ctrlWithUser(User $user, ?EntityManagerInterface $em = null): MessageController
    {
        $r = $this->messageRepo; $pt = $this->Axiom;
        return new class($r, $em ?? $this->em, $pt, $user) extends MessageController {
            private UserInterface $u;
            public function __construct($r, $e, $p, $u) { parent::__construct($r, $e, $p); $this->u = $u; }
            public function getUser(): ?UserInterface { return $this->u; }
        };
    }

    private function ctrlAccepting(?EntityManagerInterface $em = null): TestableMessageController
    {
        return new TestableMessageController($this->messageRepo, $em ?? $this->em, $this->Axiom, true);
    }

    private function ctrlRejecting(): TestableMessageController
    {
        return new TestableMessageController($this->messageRepo, $this->em, $this->Axiom, false);
    }

    private function jsonReq(array $payload): Request
    {
        $req = Request::create('/', 'POST', [], [], [], [], json_encode($payload));
        $req->headers->set('Content-Type', 'application/json');
        return $req;
    }

    private function dummyFactory(): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'dummy', 'policy' => 'no_limit'], new InMemoryStorage());
    }

    private function qbCount(int $count, mixed $single = null): QueryBuilder&MockObject
    {
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['select','where','andWhere','setParameter','orderBy','innerJoin'] as $m) $qb->method($m)->willReturnSelf();
        $qb->method('getQuery')->willReturn(new StubQuery($count, $single, []));
        return $qb;
    }

    private function qbList(array $list): QueryBuilder&MockObject
    {
        $qb = $this->createMock(QueryBuilder::class);
        foreach (['select','where','andWhere','setParameter','orderBy','innerJoin'] as $m) $qb->method($m)->willReturnSelf();
        $qb->method('getQuery')->willReturn(new StubQuery(0, null, $list));
        return $qb;
    }

    public function userRepo(?User $existing = null): EntityRepository&MockObject
    {
        $r = $this->createMock(EntityRepository::class);
        $r->method('findOneBy')->willReturn($existing);
        return $r;
    }

    public function buildEm(array $map): EntityManagerInterface&MockObject
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(fn($c) => $map[$c] ?? $this->createMock(EntityRepository::class));
        return $em;
    }

    private function callPrivate(string $method, ...$args): mixed
    {
        $m = new \ReflectionMethod(MessageController::class, $method);
        $m->setAccessible(true);
        return $m->invoke($this->controller, ...$args);
    }

    private function reflectMethod(string $method): \ReflectionMethod
    {
        $m = new \ReflectionMethod(MessageController::class, $method);
        $m->setAccessible(true);
        return $m;
    }

    private function buildEmForConv(User $creator, User $participant, mixed $duplicateConv = null): EntityManagerInterface&MockObject
    {
        $userRepo = $this->createMock(EntityRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);
        $userRepo->method('findBy')->willReturn([$participant]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->qbCount(0, $duplicateConv));
        $em = $this->buildEm([User::class => $userRepo, Conversation::class => $convRepo]);
        $em->method('beginTransaction');
        return $em;
    }

    // =========================================================================
    // getConnectedUser
    // =========================================================================

    public function testGetConnectedUserUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->Axiom) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserNotUserInstanceReturns500(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->Axiom) extends MessageController {
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
        $user = $this->makeUser();
        $ctrl = $this->ctrlWithUser($user, $this->buildEm([User::class => $this->userRepo(null)]));
        $response = $ctrl->getConnectedUser();
        $data = json_decode($response->getContent(), true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $data['id']);
        $this->assertSame('Jean', $data['firstName']);
        $this->assertSame('jean.dupont@example.com', $data['email']);
    }

    #[DataProvider('invalidUserProvider')]
    public function testGetConnectedUserInvalidFieldReturns500(string $first, string $last, string $email): void
    {
        $this->assertSame(500, $this->ctrlWithUser($this->makeUser(1, $first, $last, $email))->getConnectedUser()->getStatusCode());
    }

    public static function invalidUserProvider(): array
    {
        return [
            'bad first name' => ['Jean123', 'Dupont', 'jean@example.com'],
            'bad last name'  => ['Jean', 'Dupont99', 'jean@example.com'],
            'bad email'      => ['Jean', 'Dupont', 'not-an-email'],
        ];
    }

    public function testGetConnectedUserEmailTakenReturns500(): void
    {
        $user  = $this->makeUser(1, 'Jean', 'Dupont', 'jean@example.com');
        $other = $this->makeUser(2, 'Autre', 'Nom', 'jean@example.com');
        $this->assertSame(500, $this->ctrlWithUser($user, $this->buildEm([User::class => $this->userRepo($other)]))->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserInvalidDatesLogsWarningAndReturns200(): void
    {
        $user = $this->makeUser(start: new \DateTimeImmutable('+10 days'), end: new \DateTimeImmutable('+1 day'));
        $this->Axiom->expects($this->atLeast(2))->method('warning');
        $this->assertSame(200, $this->ctrlWithUser($user, $this->buildEm([User::class => $this->userRepo(null)]))->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserOnlyStartDateLogsWarning(): void
    {
        $user = $this->makeUser(start: new \DateTimeImmutable('+1 day'), end: null);
        $this->Axiom->expects($this->atLeastOnce())->method('warning');
        $this->assertSame(200, $this->ctrlWithUser($user, $this->buildEm([User::class => $this->userRepo(null)]))->getConnectedUser()->getStatusCode());
    }

    public function testGetConnectedUserDbExceptionReturns500(): void
    {
        $badRepo = $this->createMock(EntityRepository::class);
        $badRepo->method('findOneBy')->willThrowException(new \Exception('DB down'));
        $response = $this->ctrlWithUser($this->makeUser(), $this->buildEm([User::class => $badRepo]))->getConnectedUser();
        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('error', json_decode($response->getContent(), true));
    }

    public function testGetConnectedUserWithConversations(): void
    {
        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(42);
        // Conversations passed at construction time — avoids re-configuring a mock method
        $user = $this->makeUser(conversations: [$conv]);
        $data = json_decode($this->ctrlWithUser($user, $this->buildEm([User::class => $this->userRepo(null)]))->getConnectedUser()->getContent(), true);
        $this->assertSame(42, $data['userData'][0]['id']);
    }

    // =========================================================================
    // getUserConversations
    // =========================================================================

    public function testGetUserConversationsUnauthenticated(): void
    {
        $ctrl = new class($this->messageRepo, $this->em, $this->Axiom) extends MessageController {
            public function getUser(): ?UserInterface { return null; }
        };
        $this->assertSame(401, $ctrl->getUserConversations($this->em)->getStatusCode());
    }

    public function testGetUserConversationsReturnsValidConversation(): void
    {
        $me   = $this->makeUser(1);
        $p    = $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv = $this->makeConv(10, 'Ma conversation', 'desc', $me, [$me, $p]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->qbList([$conv]));
        $em   = $this->buildEm([Conversation::class => $convRepo, User::class => $this->userRepo(null)]);
        $data = json_decode($this->ctrlWithUser($me, $em)->getUserConversations($em)->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Ma conversation', $data[0]['title']);
        $this->assertCount(1, $data[0]['users']);
    }

    #[DataProvider('convListSkipProvider')]
    public function testGetUserConversationsSkipsInvalidData(string $title, string $first, string $last, string $email, int $expectedCount): void
    {
        $me   = $this->makeUser(1);
        $p    = $this->makeUser(2, $first, $last, $email);
        $conv = $this->makeConv(5, $title, 'desc', $me, [$me, $p]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->qbList([$conv]));
        $em   = $this->buildEm([Conversation::class => $convRepo, User::class => $this->userRepo(null)]);
        $data = json_decode($this->ctrlWithUser($me, $em)->getUserConversations($em)->getContent(), true);
        $this->assertCount($expectedCount, $data);
    }

    public static function convListSkipProvider(): array
    {
        return [
            'xss title'     => ['<script>xss</script>', 'Marie', 'Curie', 'marie@example.com', 0],
            'invalid name'  => ['Valid Title', 'Bad123', 'User', 'bad@example.com', 0],
            'invalid email' => ['Valid Title', 'Marie', 'Curie', 'not-valid-email', 0],
        ];
    }

    /**
     * @param 'null'|'bad' $creatorType
     */
    #[DataProvider('fallbackCreatorProvider')]
    public function testGetUserConversationsFallbackCreator(string $creatorType): void
    {
        $me      = $this->makeUser(1);
        $p       = $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com');
        $creator = match ($creatorType) {
            'null' => null,
            'bad'  => $this->makeUser(3, 'Bad99', 'Creator', 'bad@example.com'),
        };
        $conv = $this->makeConv(10, 'Valid Title', '', $creator, [$me, $p]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->qbList([$conv]));
        $em   = $this->buildEm([Conversation::class => $convRepo, User::class => $this->userRepo(null)]);
        $data = json_decode($this->ctrlWithUser($me, $em)->getUserConversations($em)->getContent(), true);
        $this->assertSame('Unknown', $data[0]['createdBy']);
    }

    // Static: returns string keys; mocks are created inside the test method
    public static function fallbackCreatorProvider(): array
    {
        return [
            'null creator' => ['null'],
            'bad creator'  => ['bad'],
        ];
    }

    public function testGetUserConversationsInvalidDescriptionLogsWarning(): void
    {
        $me = $this->makeUser(1); $p = $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com');
        $conv = $this->createMock(Conversation::class);
        $conv->method('getId')->willReturn(10);
        $conv->method('getTitle')->willReturn('Valid Title');
        $conv->method('getDescription')->willReturn('<script>bad</script>');
        $conv->method('getCreatedBy')->willReturn($me);
        $conv->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $conv->method('getLastMessageAt')->willReturn(null);
        $conv->method('getUsers')->willReturn(new ArrayCollection([$me, $p]));
        $conv->method('getMessages')->willReturn(new ArrayCollection([]));
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willReturn($this->qbList([$conv]));
        $em = $this->buildEm([Conversation::class => $convRepo, User::class => $this->userRepo(null)]);
        $this->Axiom->expects($this->atLeastOnce())->method('warning');
        $this->assertCount(1, json_decode($this->ctrlWithUser($me, $em)->getUserConversations($em)->getContent(), true));
    }

    public function testGetUserConversationsDbExceptionReturns500(): void
    {
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        $em = $this->buildEm([Conversation::class => $convRepo]);
        $this->assertSame(500, $this->ctrlWithUser($this->makeUser(1), $em)->getUserConversations($em)->getStatusCode());
    }

    // =========================================================================
    // getMessages
    // =========================================================================

    public function testGetMessagesUnauthenticated(): void
    {
        $this->assertSame(401, $this->controller->getMessages(
            $this->createMock(Security::class), $this->messageRepo, Request::create('/api/get/messages', 'GET')
        )->getStatusCode());
    }

    public function testGetMessagesValidReturns200(): void
    {
        $user = $this->makeUser();
        $msg  = $this->makeMsg(author: $user, conv: $this->makeConv(creator: $user));
        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->userRepo(null));
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $data = json_decode($this->controller->getMessages($sec, $this->messageRepo, Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]))->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertArrayHasKey('pagination', $data);
    }

    /**
     * @param bool $hasConv  true = attach a valid conversation, false = orphan message
     */
    #[DataProvider('invalidMessageProvider')]
    public function testGetMessagesSkipsInvalid(string $content, bool $hasConv): void
    {
        $user = $this->makeUser();
        $conv = $hasConv ? $this->makeConv(creator: $user) : null;
        $msg  = $this->makeMsg(content: $content, author: $user, conv: $conv);
        $this->messageRepo->method('findBy')->willReturn([$msg]);
        $this->messageRepo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->userRepo(null));
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $data = json_decode($this->controller->getMessages($sec, $this->messageRepo, Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]))->getContent(), true);
        $this->assertCount(0, $data['data']);
    }

    // Static: passes a bool flag; the conv mock is built inside the test method
    public static function invalidMessageProvider(): array
    {
        return [
            'xss content' => ['<script>xss</script>', true],
            'no conv'     => ['Hello world', false],
        ];
    }

    #[DataProvider('authorNameFallbackProvider')]
    public function testGetMessagesAuthorNameFallback(string $authorName, string $expected): void
    {
        $user   = $this->makeUser();
        $conv   = $this->makeConv(creator: $user);
        $author = $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com');
        $msg    = $this->createMock(Message::class);
        $msg->method('getId')->willReturn(1);
        $msg->method('getContent')->willReturn('Hello world');
        $msg->method('getAuthor')->willReturn($author);
        $msg->method('getAuthorName')->willReturn($authorName);
        $msg->method('getConversation')->willReturn($conv);
        $msg->method('getConversationTitle')->willReturn('Test Conv');
        $msg->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $repo = $this->createMock(MessageRepository::class);
        $repo->method('findBy')->willReturn([$msg]);
        $repo->method('count')->willReturn(1);
        $this->em->method('getRepository')->willReturn($this->userRepo(null));
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $data = json_decode($this->controller->getMessages($sec, $repo, Request::create('/api/get/messages', 'GET', ['page' => 1, 'limit' => 10]))->getContent(), true);
        $this->assertSame($expected, $data['data'][0]['authorName']);
    }

    public static function authorNameFallbackProvider(): array
    {
        return [
            'single word'    => ['Mononym', 'Unknown User'],
            'both parts bad' => ['Bad99 Name99', 'Unknown User'],
        ];
    }

    #[DataProvider('paginationProvider')]
    public function testGetMessagesPaginationClamping(int $page, int $limit): void
    {
        $user = $this->makeUser();
        $this->messageRepo->method('findBy')->willReturn([]);
        $this->messageRepo->method('count')->willReturn(0);
        $this->em->method('getRepository')->willReturn($this->userRepo(null));
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $response = $this->controller->getMessages($sec, $this->messageRepo, Request::create('/api/get/messages', 'GET', ['page' => $page, 'limit' => $limit]));
        $this->assertContains($response->getStatusCode(), [200, 400]);
    }

    public static function paginationProvider(): array
    {
        return [
            'zero page'     => [0, 999],
            'negative page' => [-5, 999999],
            'high page'     => [PHP_INT_MAX, 100],
            'low limit'     => [1, 0],
        ];
    }

    // =========================================================================
    // createConversation
    // =========================================================================

    // The unauthenticated case has its own test because the shared provider helper
    // always injects a valid user via $sec->getUser(); merging it in would require
    // a special branch and obscures intent.
    public function testCreateConversationUnauthenticated(): void
    {
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn(null);
        $this->assertSame(401, $this->controller->createConversation(
            $this->jsonReq(['title' => 'Hi', 'conv_users' => [2]]), $sec, $this->em
        )->getStatusCode());
    }

    #[DataProvider('createConvErrorProvider')]
    public function testCreateConversationErrors(array $payload, int $code, string $contentType = 'application/json'): void
    {
        $user = $this->makeUser();
        $this->em->method('getRepository')->willReturn($this->userRepo(null));
        $req  = Request::create('/', 'POST', [], [], [], [], json_encode($payload));
        $req->headers->set('Content-Type', $contentType);
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $this->assertSame($code, $this->controller->createConversation($req, $sec, $this->em)->getStatusCode());
    }

    public static function createConvErrorProvider(): array
    {
        return [
            'wrong content type' => [['title' => 'Hi'], 415, 'text/plain'],
            'missing title'      => [['conv_users' => [2]], 400],
            'xss title'          => [['title' => '<script>xss</script>', 'conv_users' => [2]], 400],
            'extra fields'       => [['title' => 'Valid', 'conv_users' => [2], 'malicious' => 'x'], 500],
            'too large'          => [['title' => str_repeat('a', 15000), 'conv_users' => [2]], 413],
            'too many users'     => [['title' => 'Big', 'conv_users' => range(2, 52)], 400],
        ];
    }

    public function testCreateConversationInvalidUserStateReturns400(): void
    {
        $user = $this->makeUser(first: 'Jean99');
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $response = $this->controller->createConversation($this->jsonReq(['title' => 'Test', 'conv_users' => [2]]), $sec, $this->buildEm([User::class => $this->userRepo(null)]));
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('invalid user state', json_decode($response->getContent(), true)['message']);
    }

    public function testCreateConversationSuccessReturns201(): void
    {
        $creator = $this->makeUser(1);
        $sec     = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($creator);
        $em = $this->buildEmForConv($creator, $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com'));
        $em->expects($this->once())->method('persist')->willReturnCallback(fn($e) => (new \ReflectionClass($e))->getProperty('id')->setValue($e, 1));
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $response = $this->controller->createConversation($this->jsonReq(['title' => 'Ma%20conv', 'conv_users' => [2]]), $sec, $em);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Ma conv', json_decode($response->getContent(), true)['title']);
    }

    public function testCreateConversationDuplicateReturns409(): void
    {
        $creator = $this->makeUser(1);
        $sec     = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($creator);
        $em = $this->buildEmForConv($creator, $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com'), $this->createMock(Conversation::class));
        $em->expects($this->once())->method('rollback');
        $response = $this->controller->createConversation($this->jsonReq(['title' => 'Ma conversation', 'conv_users' => [2]]), $sec, $em);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('DUPLICATE_CONVERSATION', json_decode($response->getContent(), true)['error']);
    }

    #[DataProvider('createConvExceptionProvider')]
    public function testCreateConversationPersistException(\Throwable $ex, int $code): void
    {
        $creator = $this->makeUser(1);
        $sec     = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($creator);
        $em = $this->buildEmForConv($creator, $this->makeUser(2, 'Marie', 'Curie', 'marie@example.com'));
        $em->method('persist')->willThrowException($ex);
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('clear');
        $this->assertSame($code, $this->controller->createConversation($this->jsonReq(['title' => 'Test', 'conv_users' => [2]]), $sec, $em)->getStatusCode());
    }

    public static function createConvExceptionProvider(): array
    {
        return [
            'generic exception'          => [new \Exception('err'), 500],
            'invalid argument exception' => [new \InvalidArgumentException('bad arg'), 400],
        ];
    }

    // =========================================================================
    // createMessage
    // =========================================================================

    #[DataProvider('createMsgErrorProvider')]
    public function testCreateMessageErrors(array $payload, int $code, bool $reject = false, string $contentType = 'application/json'): void
    {
        $user = $this->makeUser();
        $ctrl = $reject ? $this->ctrlRejecting() : $this->ctrlAccepting();
        $sec  = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $req  = Request::create('/', 'POST', [], [], [], [], json_encode($payload));
        $req->headers->set('Content-Type', $contentType);
        $this->assertSame($code, $ctrl->createMessage($req, $sec, $this->em, $this->dummyFactory())->getStatusCode());
    }

    public static function createMsgErrorProvider(): array
    {
        return [
            'rate limited'       => [['content' => 'Hello', 'conversation_id' => 1], 429, true],
            'wrong content type' => [['content' => 'Hi', 'conversation_id' => 1], 415, false, 'text/plain'],
            'missing content'    => [['conversation_id' => 1], 400],
            'missing conv id'    => [['content' => 'Hello world'], 400],
            'xss content'        => [['content' => '<script>xss</script>', 'conversation_id' => 1], 400],
            'extra field'        => [['content' => 'Hello world', 'conversation_id' => 1, 'injected' => 'bad'], 500],
        ];
    }

    public function testCreateMessageUnauthenticated(): void
    {
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn(null);
        $this->assertSame(401, $this->ctrlAccepting()->createMessage($this->jsonReq(['content' => 'Hello', 'conversation_id' => 1]), $sec, $this->em, $this->dummyFactory())->getStatusCode());
    }

    public function testCreateMessageLargePayloadReturns413(): void
    {
        $req = Request::create('/', 'POST', [], [], [], [], str_repeat('x', 10001));
        $req->headers->set('Content-Type', 'application/json');
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($this->makeUser());
        $this->assertSame(413, $this->ctrlAccepting()->createMessage($req, $sec, $this->em, $this->dummyFactory())->getStatusCode());
    }

    public function testCreateMessageConversationNotFoundReturns404(): void
    {
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn(null);
        $em  = $this->buildEm([Conversation::class => $convRepo]);
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($this->makeUser());
        $this->assertSame(404, $this->ctrlAccepting($em)->createMessage($this->jsonReq(['content' => 'Hello', 'conversation_id' => 999]), $sec, $em, $this->dummyFactory())->getStatusCode());
    }

    public function testCreateMessageUserNotInConversationReturns403(): void
    {
        $user     = $this->makeUser(1);
        $conv     = $this->makeConv(users: [$this->makeUser(2, 'Other', 'User', 'other@example.com')]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $em  = $this->buildEm([Conversation::class => $convRepo]);
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $this->assertSame(403, $this->ctrlAccepting($em)->createMessage($this->jsonReq(['content' => 'Hello', 'conversation_id' => 10]), $sec, $em, $this->dummyFactory())->getStatusCode());
    }

    public function testCreateMessageSuccessReturns201(): void
    {
        $user     = $this->makeUser(1);
        $conv     = $this->makeConv(10, 'Ma conv', '', $user, [$user]);
        $msgRepo  = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->qbCount(0));
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $em  = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $em->expects($this->once())->method('persist')->willReturnCallback(fn($e) => (new \ReflectionClass($e))->getProperty('id')->setValue($e, 100));
        $em->expects($this->once())->method('flush');
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $data = json_decode($this->ctrlAccepting($em)->createMessage($this->jsonReq(['content' => 'Hello world', 'conversation_id' => 10]), $sec, $em, $this->dummyFactory())->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('content', $data);
    }

    public function testCreateMessagePersistExceptionReturns500(): void
    {
        $user     = $this->makeUser(1);
        $conv     = $this->makeConv(10, 'Ma conv', '', $user, [$user]);
        $msgRepo  = $this->createMock(EntityRepository::class);
        $msgRepo->method('createQueryBuilder')->willReturn($this->qbCount(0));
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $em  = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $em->method('persist')->willThrowException(new \Exception('DB write error'));
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('clear');
        $sec = $this->createMock(Security::class);
        $sec->method('getUser')->willReturn($user);
        $data = json_decode($this->ctrlAccepting($em)->createMessage($this->jsonReq(['content' => 'Hello world', 'conversation_id' => 10]), $sec, $em, $this->dummyFactory())->getContent(), true);
        $this->assertSame('Error creating message', $data['message']);
        $this->assertSame('DB write error', $data['error']);
    }

    // =========================================================================
    // deleteConversation
    // =========================================================================

    #[DataProvider('deleteConvErrorProvider')]
    public function testDeleteConversationErrors(int $id, int $code, ?\Closure $setup = null): void
    {
        $user = $this->makeUser(1);
        if ($setup) {
            $convRepo = $this->createMock(EntityRepository::class);
            $setup($convRepo, $user, $this);
            $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);
        }
        $this->assertSame($code, $this->ctrlWithUser($user)->deleteConversation($id, $this->em)->getStatusCode());
    }

    public static function deleteConvErrorProvider(): array
    {
        return [
            'zero id'     => [0, 400],
            'negative id' => [-5, 400],
            'not found'   => [999, 404, fn($r) => $r->method('find')->willReturn(null)],
            'forbidden'   => [10, 403, fn($r, $u, $t) => $r->method('find')->willReturn($t->makeConv(10, 'T', '', $t->makeUser(2, 'Other', 'User', 'other@example.com')))],
        ];
    }

    public function testDeleteConversationTooManyMessagesReturns413(): void
    {
        $user = $this->makeUser(1);
        $max  = (new \ReflectionClassConstant(\App\BusinessLimits::class, 'CONVERSATION_MAX_MESSAGES_FOR_DELETE'))->getValue();
        $conv = $this->makeConv(10, 'Test', '', $user, [$user], array_fill(0, $max + 1, $this->createStub(Message::class)));
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);
        $data = json_decode($this->ctrlWithUser($user)->deleteConversation(10, $this->em)->getContent(), true);
        $this->assertStringContainsString('too large to delete', $data['message']);
        $this->assertStringContainsString((string)($max + 1), $data['message']);
    }

    public function testDeleteConversationSuccessDeletesMessagesAndReturns200(): void
    {
        $user = $this->makeUser(1);
        $msg1 = $this->createStub(Message::class);
        $msg2 = $this->createStub(Message::class);
        $conv = $this->makeConv(10, 'Test', '', $user, [$user], [$msg1, $msg2]);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willReturn($conv);
        $msgRepo  = $this->createMock(EntityRepository::class);
        $msgRepo->method('findBy')->willReturn([$msg1, $msg2]);
        $em = $this->buildEm([Conversation::class => $convRepo, Message::class => $msgRepo]);
        $em->expects($this->atLeast(3))->method('remove');
        $em->expects($this->once())->method('flush');
        $data = json_decode($this->ctrlWithUser($user, $em)->deleteConversation(10, $em)->getContent(), true);
        $this->assertStringContainsString('deleted', strtolower($data['message']));
        $this->assertSame(2, $data['deletedMessages']);
    }

    public function testDeleteConversationDbExceptionReturns500(): void
    {
        $user     = $this->makeUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $convRepo->method('find')->willThrowException(new \Exception('DB'));
        $this->em->method('getRepository')->with(Conversation::class)->willReturn($convRepo);
        $this->assertSame(500, $this->ctrlWithUser($user)->deleteConversation(10, $this->em)->getStatusCode());
    }

    // =========================================================================
    // deleteMessage
    // =========================================================================

    #[DataProvider('deleteMsgErrorProvider')]
    public function testDeleteMessageErrors(int $id, int $code, ?\Closure $setup = null): void
    {
        $user = $this->makeUser(1);
        if ($setup) {
            $msgRepo = $this->createMock(EntityRepository::class);
            $setup($msgRepo, $user, $this);
            $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);
        }
        $this->assertSame($code, $this->ctrlWithUser($user)->deleteMessage($id, $this->em)->getStatusCode());
    }

    public static function deleteMsgErrorProvider(): array
    {
        return [
            'zero id'      => [0, 400],
            'negative id'  => [-1, 400],
            'out of range' => [PHP_INT_MAX, 400],
            'not found'    => [1, 404, fn($r) => $r->method('find')->willReturn(null)],
            'forbidden'    => [100, 403, fn($r, $u, $t) => $r->method('find')->willReturn($t->makeMsg(100, 'Hi', $t->makeUser(2, 'Other', 'User', 'other@example.com'), $t->makeConv()))],
        ];
    }

    public function testDeleteMessageNullConversationReturns500(): void
    {
        $user = $this->makeUser(1);
        $msg  = $this->createMock(Message::class);
        $msg->method('getId')->willReturn(1);
        $msg->method('getContent')->willReturn('Hello');
        $msg->method('getAuthor')->willReturn($user);
        $msg->method('getConversation')->willReturn(null);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($msg);
        $em   = $this->buildEm([Message::class => $msgRepo]);
        $data = json_decode($this->ctrlWithUser($user, $em)->deleteMessage(1, $em)->getContent(), true);
        $this->assertSame(500, $this->ctrlWithUser($user, $em)->deleteMessage(1, $em)->getStatusCode());
        $this->assertSame('Invalid message conversation', $data['message']);
    }

    public function testDeleteMessageSuccessReturns200(): void
    {
        $user    = $this->makeUser(1);
        $msg     = $this->makeMsg(100, 'Hello', $user, $this->makeConv(10, 'T', '', $user));
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willReturn($msg);
        $em = $this->buildEm([Message::class => $msgRepo]);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');
        $this->assertSame(200, $this->ctrlWithUser($user, $em)->deleteMessage(100, $em)->getStatusCode());
    }

    public function testDeleteMessageDbExceptionReturns500(): void
    {
        $user    = $this->makeUser(1);
        $msgRepo = $this->createMock(EntityRepository::class);
        $msgRepo->method('find')->willThrowException(new \Exception('DB'));
        $this->em->method('getRepository')->with(Message::class)->willReturn($msgRepo);
        $this->assertSame(500, $this->ctrlWithUser($user)->deleteMessage(1, $this->em)->getStatusCode());
    }

    // =========================================================================
    // Private methods
    // =========================================================================

    #[DataProvider('availabilityDatesProvider')]
    public function testValidateAvailabilityDates(?\DateTimeImmutable $start, ?\DateTimeImmutable $end, bool $valid, ?string $errorContains = null): void
    {
        $result = $this->callPrivate('validateAvailabilityDates', $start, $end);
        $this->assertSame($valid, $result['valid']);
        if ($errorContains) $this->assertStringContainsString($errorContains, $result['error']);
    }

    public static function availabilityDatesProvider(): array
    {
        return [
            'both null'         => [null, null, false],
            'only start'        => [new \DateTimeImmutable('+1 day'), null, false],
            'only end'          => [null, new \DateTimeImmutable('+2 days'), false],
            'max before min'    => [new \DateTimeImmutable('+10 days'), new \DateTimeImmutable('+1 day'), false, 'after'],
            'exceeds two years' => [new \DateTimeImmutable('+1 day'), new \DateTimeImmutable('+800 days'), false, '2 years'],
            'max in past'       => [new \DateTimeImmutable('-10 days'), new \DateTimeImmutable('-1 day'), false, 'future'],
            'valid'             => [new \DateTimeImmutable('+1 day'), new \DateTimeImmutable('+30 days'), true],
        ];
    }

    /**
     * @param 'none'|'good'|'empty' $repoType  controls which User repo stub is injected
     */
    #[DataProvider('participantsProvider')]
    public function testValidateConversationParticipants(array $ids, bool $valid, ?string $errorContains = null, string $repoType = 'none'): void
    {
        $creator = $this->makeUser(1);
        $em = match ($repoType) {
            'good' => (function () {
                $repo = $this->createMock(EntityRepository::class);
                $repo->method('findBy')->willReturn([$this->makeUser(2, 'Marie', 'Curie', 'marie@example.com')]);
                return $this->buildEm([User::class => $repo]);
            })(),
            'empty' => (function () {
                $repo = $this->createMock(EntityRepository::class);
                $repo->method('findBy')->willReturn([]);
                return $this->buildEm([User::class => $repo]);
            })(),
            default => $this->em,
        };
        $result = $this->reflectMethod('validateConversationParticipants')->invoke($this->controller, $creator, $ids, $em);
        $this->assertSame($valid, $result['valid']);
        if ($errorContains) $this->assertStringContainsString($errorContains, $result['error']);
    }

    // Static: repo behaviour is encoded as a string key, mocks built in test method
    public static function participantsProvider(): array
    {
        return [
            'too many'         => [range(2, 52), false, '50',              'none'],
            'empty'            => [[],            false, '2 participants',  'none'],
            'creator filtered' => [[1],           false, null,             'none'],
            'non numeric'      => [['abc','xyz'], false, null,             'none'],
            'zero and neg'     => [[0,-1,'0'],    false, '2 participants', 'none'],
            'missing in db'    => [[2, 3],        false, 'Invalid user IDs','empty'],
            'valid'            => [[2],           true,  null,             'good'],
        ];
    }

    /**
     * @param 'none'|'other'|'same' $repoType  controls which existing-user stub is returned
     */
    #[DataProvider('emailProvider')]
    public function testValidateEmailAndUniqueness(string $email, int $userId, bool $valid, ?string $errorContains = null, string $repoType = 'none'): void
    {
        $em = match ($repoType) {
            'other' => $this->buildEm([User::class => $this->userRepo($this->makeUser(99))]),
            'same'  => $this->buildEm([User::class => $this->userRepo($this->makeUser(1))]),
            default => $this->em,
        };
        $result = $this->reflectMethod('validateEmailandUniqueness')->invoke($this->controller, $email, $userId, $em);
        $this->assertSame($valid, $result['valid']);
        if ($errorContains) $this->assertStringContainsString($errorContains, $result['error']);
    }

    // Static: repo scenario encoded as string key
    public static function emailProvider(): array
    {
        return [
            'too long'       => [str_repeat('a', 250).'@b.com', 1, false, '255',           'none'],
            'invalid format' => ['not-an-email',                1, false, null,             'none'],
            'taken by other' => ['taken@example.com',           1, false, 'already in use', 'other'],
            'same user ok'   => ['jean@example.com',            1, true,  null,             'same'],
        ];
    }

    #[DataProvider('canonicalDecodeProvider')]
    public function testCanonicalDecode(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('canonicalDecode', $input));
    }

    public static function canonicalDecodeProvider(): array
    {
        return [
            'html entities'  => ['Hello &amp; World', 'Hello & World'],
            'url encoded'    => ['Hello%20World', 'Hello World'],
            'double encoded' => ['Hello%20%26%20World', 'Hello & World'],
            'plain'          => ['Hello World', 'Hello World'],
        ];
    }

    public function testCanonicalDecodeTooLongThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->callPrivate('canonicalDecode', str_repeat('a', 200001));
    }

    #[DataProvider('validateStringProvider')]
    public function testValidateString(string $value, int $max, bool $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('validateString', $value, $max));
    }

    public static function validateStringProvider(): array
    {
        return [
            'empty'              => ['', 100, false],
            'too long'           => [str_repeat('a', 101), 100, false],
            'absolute >10000'    => [str_repeat('a', 10001), 20000, false],
            'csv injection'      => ['=SUM(A1)', 100, false],
            'xss'                => ['<script>alert(1)</script>', 100, false],
            'sql select'         => ['select * from users', 100, false],
            'sql union'          => ['union select password', 100, false],
            'sql drop'           => ['drop table users', 100, false],
            'iframe'             => ['<iframe src="x"></iframe>', 100, false],
            'javascript proto'   => ['javascript:alert(1)', 100, false],
            'on event'           => ['onload=bad', 100, false],
            'starts with hyphen' => ['-Hello', 100, false],
            'starts with plus'   => ['+Hello', 100, false],
            'valid'              => ['Hello world!', 100, true],
            'with newline'       => ["Hello\nworld", 100, true],
        ];
    }

    #[DataProvider('validateNameProvider')]
    public function testValidateName(string $name, bool $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('validateName', $name));
    }

    public static function validateNameProvider(): array
    {
        $dangerous = [];
        foreach (['<','>','&','"','\\','/','@','#','$','%','^','*','(',')','=','+','[',']','{','}'] as $i => $c) {
            $dangerous["dangerous_$i"] = [$c.'Jean', false];
        }
        return array_merge($dangerous, [
            'empty'              => ['', false],
            'too short'          => ['A', false],
            'too long'           => [str_repeat('a', 21), false],
            'starts with hyphen' => ['-Jean', false],
            'ends with space'    => ['Jean ', false],
            'contains digit'     => ['Jean2', false],
            'three consecutive'  => ['Jean---Luc', false],
            'valid hyphen'       => ['Jean-Luc', true],
            'valid apostrophe'   => ["O'Brien", true],
            'valid accented'     => ['Éléonore', true],
        ]);
    }

 
    public static function sanitizeHtmlProvider(): array
    {
        return [
            'null'                 => [null,  false, ''],
            'strips script'        => ['<strong>Hello</strong><script>bad</script>', false, 'Hello', '<script>'],
            'allows strong'        => ['<strong>Hello</strong>', true, '<strong>Hello</strong>'],
            'strips script+format' => ['<strong>ok</strong><script>bad</script>', true, '<strong>ok</strong>', '<script>'],
            'blocks external link' => ['<a href="https://evil.com">click</a>', true, '', 'evil.com'],
        ];
    }

    #[DataProvider('sanitizeJsonProvider')]
    public function testSanitizeForJson(mixed $input, mixed $expected): void
    {
        $this->assertSame($expected, $this->callPrivate('sanitizeForJson', $input));
    }

    public static function sanitizeJsonProvider(): array
    {
        return [
            'null'                  => [null, null],
            'integer'               => [42, 42],
            'strips control chars'  => ["hello\x00\x01\x1Fworld", 'helloworld'],
            'preserves newline tab' => ["hello\n\t\rworld", "hello\n\t\rworld"],
            'array'                 => [["hello\x00world", "clean"], ['helloworld', 'clean']],
        ];
    }

    /**
     * Assertion strategies (static, no closures):
     *   'notContains' => assertStringNotContainsString
     *   'count'       => assertCount
     *   'equals'      => assertSame
     */
    #[DataProvider('sanitizeDataProvider')]
    public function testSanitizeData(array $input, string $key, string $assertType, mixed $assertValue): void
    {
        $result = $this->callPrivate('sanitizeData', $input);
        match ($assertType) {
            'notContains' => $this->assertStringNotContainsString($assertValue, $result[$key]),
            'count'       => $this->assertCount($assertValue, $result[$key]),
            'equals'      => $this->assertSame($assertValue, $result[$key]),
        };
    }

    public static function sanitizeDataProvider(): array
    {
        return [
            'title strips tags'       => [['title' => '<b>hello</b>'],                                 'title',           'notContains', '<b>'],
            'desc strips script'      => [['description' => '<strong>ok</strong><script>bad</script>'], 'description',     'notContains', '<script>'],
            'conv_users deduplicates' => [['conv_users' => [1, 2, 2, 0, -1, 3]],                       'conv_users',      'count',       3],
            'conversation_id casts'   => [['conversation_id' => '42'],                                  'conversation_id', 'equals',      42],
        ];
    }

    #[DataProvider('rateLimitProvider')]
    public function testRateLimits(string $method, string $entityClass, int $count, bool $expected, bool $throwException = false): void
    {
        $repo = $this->createMock(EntityRepository::class);
        if ($throwException) {
            $repo->method('createQueryBuilder')->willThrowException(new \Exception('DB'));
        } else {
            $repo->method('createQueryBuilder')->willReturn($this->qbCount($count));
        }
        $em   = $this->buildEm([$entityClass => $repo]);
        $args = $method === 'validateMessageRateLimit'
            ? [$this->makeUser(), $this->makeConv(), $em]
            : [$this->makeUser(), $em];
        $result = $this->reflectMethod($method)->invoke($this->controller, ...$args);
        $this->assertSame($expected, $result['valid']);
    }

    public static function rateLimitProvider(): array
    {
        return [
            'msg under limit'          => ['validateMessageRateLimit',           Message::class,      5,   true],
            'msg at limit'             => ['validateMessageRateLimit',           Message::class,      100, false],
            'msg db exception'         => ['validateMessageRateLimit',           Message::class,      0,   true,  true],
            'conv create under limit'  => ['validateConversationCreateRateLimit', Conversation::class, 0,   true],
            'conv create exceeded'     => ['validateConversationCreateRateLimit', Conversation::class, 999, false],
            'conv create db exception' => ['validateConversationCreateRateLimit', Conversation::class, 0,   true,  true],
            'conv delete nominal'      => ['validateConversationDeleteRateLimit', Conversation::class, 0,   true],
            'conv delete db exception' => ['validateConversationDeleteRateLimit', Conversation::class, 0,   true,  true],
        ];
    }

    #[DataProvider('hashProvider')]
    public function testGenerateConversationHash(array $ids1, string $t1, array $ids2, string $t2, bool $equal): void
    {
        $h1 = $this->callPrivate('generateConversationHash', $ids1, $t1);
        $h2 = $this->callPrivate('generateConversationHash', $ids2, $t2);
        $equal ? $this->assertSame($h1, $h2) : $this->assertNotSame($h1, $h2);
    }

    public static function hashProvider(): array
    {
        return [
            'deterministic'         => [[1,2,3], 'My Conv',  [3,1,2], 'My Conv',  true],
            'case insensitive'      => [[1,2],   'My Conv',  [1,2],   'MY CONV',  true],
            'normalizes whitespace' => [[1,2],   'My  Conv', [1,2],   'My Conv',  true],
            'differs on title'      => [[1,2],   'Title A',  [1,2],   'Title B',  false],
        ];
    }

    #[DataProvider('duplicateConvProvider')]
    public function testCheckDuplicateConversation(mixed $single, bool $expected, bool $throw = false): void
    {
        $creator  = $this->makeUser(1);
        $convRepo = $this->createMock(EntityRepository::class);
        $throw
            ? $convRepo->method('createQueryBuilder')->willThrowException(new \Exception('DB'))
            : $convRepo->method('createQueryBuilder')->willReturn($this->qbCount(0, $single));
        $em = $this->buildEm([Conversation::class => $convRepo]);
        $this->assertSame($expected, $this->reflectMethod('checkDuplicateConversation')->invoke($this->controller, $creator, [2], 'Test', $em));
    }

    public static function duplicateConvProvider(): array
    {
        return [
            'no duplicate' => [null,           false],
            'found'        => [new \stdClass(), true],
            'db exception' => [null,           false, true],
        ];
    }

    /**
     * User configuration array keys (all optional — defaults produce a valid user):
     *   first (string), last (string), email (string),
     *   start (relative date string e.g. '+10 days'), end (relative date string)
     */
    #[DataProvider('userStateProvider')]
    public function testValidateUserState(array $userConfig, bool $valid, ?string $error = null, bool $useCustomEm = false): void
    {
        $user = $this->makeUser(
            first: $userConfig['first'] ?? 'Jean',
            last:  $userConfig['last']  ?? 'Dupont',
            email: $userConfig['email'] ?? 'jean.dupont@example.com',
            start: isset($userConfig['start']) ? new \DateTimeImmutable($userConfig['start']) : null,
            end:   isset($userConfig['end'])   ? new \DateTimeImmutable($userConfig['end'])   : null,
        );
        $em   = $useCustomEm ? $this->buildEm([User::class => $this->userRepo(null)]) : $this->em;
        $ctrl = new MessageController($this->messageRepo, $em, $this->Axiom);
        $result = $this->reflectMethod('validateUserState')->invoke($ctrl, $user);
        $this->assertSame($valid, $result['valid']);
        if ($error) $this->assertSame($error, $result['error']);
    }

    // Static: user properties passed as a plain array; DateTimeImmutable created in test
    public static function userStateProvider(): array
    {
        return [
            'valid'                => [[],                                              true,  null,                       true],
            'bad first name'       => [['first' => 'Jean99'],                          false, 'Invalid user name'],
            'first name too short' => [['first' => 'J'],                               false, 'Invalid user name'],
            'starts with hyphen'   => [['first' => '-Jean'],                           false, 'Invalid user name'],
            'dangerous char'       => [['first' => 'Jean<Luc'],                        false, 'Invalid user name'],
            'three consecutive'    => [['first' => 'Jean---Luc'],                      false, 'Invalid user name'],
            'bad last name'        => [['last'  => 'Dupont<>'],                        false],
            'bad email'            => [['email' => 'bad-email'],                       false, 'Invalid user email'],
            'invalid dates'        => [['start' => '+10 days', 'end' => '+1 day'],     false, 'Invalid availability dates', true],
        ];
    }

    public function testValidateUserStateEmailTakenByOther(): void
    {
        $user   = $this->makeUser(1, 'Jean', 'Dupont', 'jean@example.com');
        $em     = $this->buildEm([User::class => $this->userRepo($this->makeUser(2, 'A', 'B', 'jean@example.com'))]);
        $result = $this->reflectMethod('validateUserState')->invoke(new MessageController($this->messageRepo, $em, $this->Axiom), $user);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid user email', $result['error']);
    }

public function testSanitizeHtmlWithNull(): void
{
    // Récupère le service ou utilise la méthode privée directement
    $result = $this->callPrivate('sanitizeHtml', null);
    $this->assertEquals('', $result);
}
}