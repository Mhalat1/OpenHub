<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class AuthControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Nettoyer la base de données avant chaque test
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    private function request(string $method, string $uri, array $data = []): Response
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );
        
        return $this->client->getResponse();
    }

    private function createUser(string $email = 'test@example.com', string $password = 'password123'): void
    {
        $this->request('POST', '/api/register', [
            'email' => $email,
            'password' => $password,
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);
    }

    // =========================================================================
    // LOGIN TESTS
    // =========================================================================

    public function testLoginReturnsUnauthorizedWhenUserNotFound(): void
    {
        $response = $this->request('POST', '/api/login_check', [
            'email' => 'unknown@example.com',
            'password' => 'secret'
        ]);
        
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Invalid credentials', $response->getContent());
    }



    // =========================================================================
    // REGISTER TESTS
    // =========================================================================

    public function testRegisterReturnsBadRequestWhenFieldsMissing(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => '',
            'password' => '',
            'firstName' => '',
            'lastName' => ''
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterReturnsBadRequestOnInvalidEmail(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'not-an-email',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid email format', $response->getContent());
    }

    public function testRegisterReturnsBadRequestOnInvalidFirstName(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => '123',
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid first name format', $response->getContent());
    }

    public function testRegisterReturnsBadRequestOnInvalidLastName(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => '!!!'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid last name format', $response->getContent());
    }

    public function testRegisterReturnsBadRequestWhenFirstNameTooShort(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'J',
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterReturnsBadRequestWhenFirstNameTooLong(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => str_repeat('a', 101),
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRegisterReturnsBadRequestWhenLastNameTooShort(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => 'D'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }


    public function testRegisterWithValidAvailabilityDates(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'availabilityStart' => '2025-01-01',
            'availabilityEnd' => '2025-12-31'
        ]);
        
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2025-01-01', $data['user']['availabilityStart']);
        $this->assertEquals('2025-12-31', $data['user']['availabilityEnd']);
    }

    public function testRegisterReturnsBadRequestOnInvalidAvailabilityStart(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'availabilityStart' => 'not-a-date'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid availability start date format', $response->getContent());
    }

    public function testRegisterReturnsBadRequestOnInvalidAvailabilityEnd(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'jane@example.com',
            'password' => 'Secret123!',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'availabilityEnd' => 'not-a-date'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid availability end date format', $response->getContent());
    }


    public function testEmailTldTooShortReturnsBadRequest(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'test@example.c',
            'password' => 'Secret123!',
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Extension must be at least 2', $response->getContent());
    }



public function testRegisterReturnsConflictWhenEmailAlreadyUsed(): void
{
    $email = 'test_' . uniqid() . '@example.com';
    
    // 1. CRÉER UN PREMIER UTILISATEUR DIRECTEMENT DANS LA BDD (sans passer par l'API)
    $user = new \App\Entity\User();
    $user->setEmail($email);
    $user->setPassword(password_hash('Password123!', PASSWORD_BCRYPT));
    $user->setFirstName('John');
    $user->setLastName('Doe');
    $user->setRoles(['ROLE_USER']);
    
    // Initialiser les dates de disponibilité pour éviter l'erreur NOT NULL
    $user->setAvailabilityStart(new \DateTimeImmutable('2025-01-01'));
    $user->setAvailabilityEnd(new \DateTimeImmutable('2025-12-31'));
    
    $this->entityManager->persist($user);
    $this->entityManager->flush();
    
    // 2. ESSAYER DE CRÉER LE MÊME UTILISATEUR VIA L'API (doit échouer avec 409)
    $this->client->request('POST', '/api/register', [], [], [
        'CONTENT_TYPE' => 'application/json'
    ], json_encode([
        'email' => $email,
        'password' => 'Password456!',
        'firstName' => 'Jane',
        'lastName' => 'Smith',
        'availabilityStart' => '2025-01-01',
        'availabilityEnd' => '2025-12-31'
    ]));
    
    $response = $this->client->getResponse();
    $responseContent = json_decode($response->getContent(), true);
    
    // 3. VÉRIFIER QUE LE CODE 409 CONFLICT EST RETOURNÉ
    $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
    $this->assertFalse($responseContent['status']);
    $this->assertEquals('This email is already in use', $responseContent['message']);
    
    // 4. VÉRIFIER QU'IL N'Y A QU'UN SEUL UTILISATEUR EN BASE
    $userRepository = $this->entityManager->getRepository(\App\Entity\User::class);
    $users = $userRepository->findBy(['email' => $email]);
    $this->assertCount(1, $users);
}



 public function testRegisterReturnsBadRequestWhenEmailDomainMissingDot(): void
    {
        // Utiliser un email avec un domaine qui a un point mais le TLD fait 1 caractère
        // Cela passe filter_var mais échoue la validation du point dans le domaine
        $response = $this->request('POST', '/api/register', [
            'email' => 'test@example.c',  // Domaine avec un point mais TLD trop court
            'password' => 'Password123!',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'availabilityStart' => '2025-01-01',
            'availabilityEnd' => '2025-12-31'
        ]);
        
        // Vérifier le code HTTP 400 Bad Request
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        // Vérifier le message d'erreur
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertEquals(
            'Invalid email format. Extension must be at least 2 characters (ex: .com, .fr)',
            $data['message']
        );
    }








     public function testRegisterReturnsBadRequestWhenPasswordEmpty(): void
    {
        $response = $this->request('POST', '/api/register', [
            'email' => 'test@example.com',
            'password' => '',  // Password vide
            'firstName' => 'John',
            'lastName' => 'Doe'
        ]);
        
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertEquals('Email, password, first name and last name are required', $data['message']);
    }


}

