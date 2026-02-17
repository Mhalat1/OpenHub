<?php

namespace App\Tests\Controller;

use App\Controller\AuthController;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AuthControllerTest extends TestCase
{
    private MockObject|EntityManagerInterface $entityManager;
    private MockObject|UserPasswordHasherInterface $passwordHasher;
    private MockObject|JWTTokenManagerInterface $jwtManager;
    private MockObject|EntityRepository $userRepository;
    private MockObject|ContainerInterface $container;
    private MockObject|SerializerInterface $serializer;
    private AuthController $controller;

    protected function setUp(): void
    {
        // Mock des dépendances
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        // Configuration du repository
        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->userRepository);

        // Configuration du container pour json()
        $this->container
            ->method('has')
            ->willReturnCallback(function ($id) {
                return $id === 'serializer';
            });

        $this->container
            ->method('get')
            ->willReturnCallback(function ($id) {
                if ($id === 'serializer') {
                    return $this->serializer;
                }
                return null;
            });

        // Mock du serializer pour json()
        $this->serializer
            ->method('serialize')
            ->willReturnCallback(function ($data, $format) {
                return json_encode($data);
            });

        // Instancier le contrôleur
        $this->controller = new AuthController(
            $this->entityManager,
            $this->passwordHasher,
            $this->jwtManager
        );

        // Injecter le container dans le contrôleur
        $this->controller->setContainer($this->container);
    }

    // ========== TESTS LOGIN ==========

    public function testLoginSuccess(): void
    {
        // Créer un utilisateur de test
        $user = $this->createUser(
            1,
            'test@example.com',
            'John',
            'Doe',
            'hashedPassword'
        );

        // Mock du repository pour trouver l'utilisateur
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        // Mock de la vérification du mot de passe
        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'password123')
            ->willReturn(true);

        // Mock de la génération du token
        $this->jwtManager
            ->expects($this->once())
            ->method('create')
            ->with($user)
            ->willReturn('fake.jwt.token');

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]));

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('fake.jwt.token', $data['token']);
        $this->assertEquals(1, $data['user']['id']);
        $this->assertEquals('test@example.com', $data['user']['email']);
        $this->assertEquals('John', $data['user']['firstName']);
        $this->assertEquals('Doe', $data['user']['lastName']);
    }

    public function testLoginUserNotFound(): void
    {
        // Mock du repository pour ne pas trouver l'utilisateur
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'notfound@example.com'])
            ->willReturn(null);

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'notfound@example.com',
            'password' => 'password123'
        ]));

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid credentials', $data['message']);
    }

    public function testLoginInvalidPassword(): void
    {
        // Créer un utilisateur de test
        $user = $this->createUser(
            1,
            'test@example.com',
            'John',
            'Doe',
            'hashedPassword'
        );

        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        // Mock de la vérification du mot de passe (invalide)
        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'wrongpassword')
            ->willReturn(false);

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]));

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Invalid credentials', $data['message']);
    }

    public function testLoginWithEmptyEmail(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => ''])
            ->willReturn(null);

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => '',
            'password' => 'password123'
        ]));

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithEmptyPassword(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => ''
        ]));

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithMalformedJson(): void
    {
        // Créer la requête avec un JSON invalide
        $request = new Request([], [], [], [], [], [], '{invalid json}');

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions - devrait retourner unauthorized car les champs seront vides
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginWithNoJsonBody(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => ''])
            ->willReturn(null);

        // Créer la requête sans corps
        $request = new Request();

        // Exécuter la méthode
        $response = $this->controller->login($request);

        // Assertions
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // ========== TESTS REGISTER ==========

    public function testRegisterSuccess(): void
    {
        // Mock du repository pour vérifier que l'email n'existe pas
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'newuser@example.com'])
            ->willReturn(null);

        // Mock du hasher de mot de passe
        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        // Mock de persist et flush
        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        // Exécuter la méthode
        $response = $this->controller->register($request);

        // Assertions
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['status']);
        $this->assertEquals('User created successfully', $data['message']);
        $this->assertEquals('newuser@example.com', $data['user']['email']);
        $this->assertEquals('Jane', $data['user']['firstName']);
        $this->assertEquals('Smith', $data['user']['lastName']);
    }

    public function testRegisterWithAvailabilityDates(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'newuser@example.com'])
            ->willReturn(null);

        // Mock du hasher
        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        // Mock de persist et flush
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Créer la requête avec des dates
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'availabilityStart' => '2024-01-01',
            'availabilityEnd' => '2024-12-31'
        ]));

        // Exécuter la méthode
        $response = $this->controller->register($request);

        // Assertions
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2024-01-01', $data['user']['availabilityStart']);
        $this->assertEquals('2024-12-31', $data['user']['availabilityEnd']);
    }

    public function testRegisterMissingRequiredFields(): void
    {
        // Test avec email manquant
        $request = new Request([], [], [], [], [], [], json_encode([
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('required', $data['message']);
    }

    public function testRegisterEmailAlreadyExists(): void
    {
        // Créer un utilisateur existant
        $existingUser = $this->createUser(
            1,
            'existing@example.com',
            'Existing',
            'User',
            'hashedPassword'
        );

        // Mock du repository pour trouver l'utilisateur existant
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        // Créer la requête
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'existing@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        // Exécuter la méthode
        $response = $this->controller->register($request);

        // Assertions
        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertEquals('This email is already in use', $data['message']);
    }

    public function testRegisterInvalidAvailabilityStartDate(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        // Créer la requête avec une date invalide
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'availabilityStart' => 'invalid-date'
        ]));

        // Exécuter la méthode
        $response = $this->controller->register($request);

        // Assertions
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('availability start date', $data['message']);
    }

    public function testRegisterInvalidAvailabilityEndDate(): void
    {
        // Mock du repository
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        // Créer la requête avec une date de fin invalide
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'availabilityEnd' => 'not-a-date'
        ]));

        // Exécuter la méthode
        $response = $this->controller->register($request);

        // Assertions
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('availability end date', $data['message']);
    }

    public function testRegisterMissingPassword(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('required', $data['message']);
    }

    public function testRegisterMissingFirstName(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
    }

    public function testRegisterMissingLastName(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
            'firstName' => 'Jane'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
    }

    public function testRegisterWithEmptyEmail(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => '',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithEmptyPassword(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => '',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithEmptyFirstName(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
            'firstName' => '',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithEmptyLastName(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => ''
        ]));

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithMalformedJson(): void
    {
        $request = new Request([], [], [], [], [], [], '{invalid json}');

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithNoJsonBody(): void
    {
        $request = new Request();

        $response = $this->controller->register($request);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterWithOnlyAvailabilityStart(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'availabilityStart' => '2024-01-01'
        ]));

        $response = $this->controller->register($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2024-01-01', $data['user']['availabilityStart']);
        $this->assertNull($data['user']['availabilityEnd']);
    }

    public function testRegisterWithOnlyAvailabilityEnd(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'availabilityEnd' => '2024-12-31'
        ]));

        $response = $this->controller->register($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNull($data['user']['availabilityStart']);
        $this->assertEquals('2024-12-31', $data['user']['availabilityEnd']);
    }

    public function testRegisterWithSkillsField(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'skills' => ['PHP', 'Symfony', 'JavaScript']
        ]));

        $response = $this->controller->register($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['status']);
    }

    public function testRegisterVerifiesUserDoesNotExist(): void
    {
        // Premier appel pour vérifier l'existence
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'duplicate@example.com'])
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'firstName' => 'Jane',
            'lastName' => 'Smith'
        ]));

        $response = $this->controller->register($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
    }

    public function testLoginResponseContainsAllUserFields(): void
    {
        $user = $this->createUser(
            123,
            'complete@example.com',
            'Complete',
            'User',
            'hashedPassword'
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $this->passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->willReturn(true);

        $this->jwtManager
            ->expects($this->once())
            ->method('create')
            ->willReturn('complete.jwt.token');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'complete@example.com',
            'password' => 'password123'
        ]));

        $response = $this->controller->login($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        
        // Vérifier tous les champs
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertArrayHasKey('firstName', $data['user']);
        $this->assertArrayHasKey('lastName', $data['user']);
    }

    public function testRegisterResponseContainsAllUserFields(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashedPassword123');

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $request = new Request([], [], [], [], [], [], json_encode([
            'email' => 'complete@example.com',
            'password' => 'password123',
            'firstName' => 'Complete',
            'lastName' => 'User',
            'availabilityStart' => '2024-01-01',
            'availabilityEnd' => '2024-12-31'
        ]));

        $response = $this->controller->register($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        
        // Vérifier tous les champs
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('id', $data['user']);
        $this->assertArrayHasKey('email', $data['user']);
        $this->assertArrayHasKey('firstName', $data['user']);
        $this->assertArrayHasKey('lastName', $data['user']);
        $this->assertArrayHasKey('availabilityStart', $data['user']);
        $this->assertArrayHasKey('availabilityEnd', $data['user']);
        $this->assertArrayHasKey('skills', $data['user']);
    }

    // ========== MÉTHODES UTILITAIRES ==========

    private function createUser(
        int $id,
        string $email,
        string $firstName,
        string $lastName,
        string $password
    ): User {
        $user = new User();
        
        // Utiliser la réflexion pour définir l'ID (propriété privée)
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);
        
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($password);
        $user->setRoles(['ROLE_USER']);
        
        return $user;
    }
}