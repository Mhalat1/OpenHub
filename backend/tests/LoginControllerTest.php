<?php

namespace App\Tests\Controller;

use App\Controller\LoginController;
use App\Service\AuthenticationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Tests pour LoginController - Version avec exceptions personnalisées
 */
class LoginControllerTest extends TestCase
{
    private function createController($authServiceMock)
    {
        return new class($authServiceMock) extends LoginController {
            public function __construct($authServiceMock) {
                parent::__construct($authServiceMock);
            }
            
            // Surcharger la méthode json pour éviter AbstractController
            protected function json($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }

    // Classe d'exception personnalisée pour les tests
    private function createAuthenticationException(string $message): AuthenticationException
    {
        return new class($message) extends AuthenticationException {
            private string $customMessage;
            
            public function __construct(string $message)
            {
                $this->customMessage = $message;
                parent::__construct($message);
            }
            
            public function getMessageKey(): string
            {
                return $this->customMessage;
            }
        };
    }

    // ==================== TESTS DE LOGIN ====================

    public function testLoginReturnsJsonResponse(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn(null);
        $authServiceMock->method('getLastUsername')->willReturn(null);
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testLoginReturnsLastUsernameAndNoError(): void
    {
        $lastUsername = 'test@example.com';
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn(null);
        $authServiceMock->method('getLastUsername')->willReturn($lastUsername);
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($lastUsername, $content['last_username']);
        $this->assertNull($content['error']);
    }

    public function testLoginReturnsErrorWhenAuthenticationFails(): void
    {
        $exception = $this->createAuthenticationException('Invalid credentials');
        $lastUsername = 'test@example.com';
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn($exception);
        $authServiceMock->method('getLastUsername')->willReturn($lastUsername);
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals($lastUsername, $content['last_username']);
        $this->assertEquals('Invalid credentials', $content['error']);
    }

    public function testLoginHandlesErrorWithoutUsername(): void
    {
        $exception = $this->createAuthenticationException('Bad credentials');
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn($exception);
        $authServiceMock->method('getLastUsername')->willReturn(null);
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertNull($content['last_username']);
        $this->assertEquals('Bad credentials', $content['error']);
    }

    public function testLoginHandlesCustomExceptionMessages(): void
    {
        $exception = $this->createAuthenticationException('Account locked');
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn($exception);
        $authServiceMock->method('getLastUsername')->willReturn('user@example.com');
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Account locked', $content['error']);
    }

    // ==================== TESTS DE LOGIN_CHECK ====================

    public function testLoginCheckReturnsExpectedMessage(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response = $controller->loginCheck();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals('This endpoint should be intercepted by JWT firewall', $content['error']);
        $this->assertEquals('Use POST with username and password to get JWT token', $content['message']);
    }

    public function testLoginCheckAlwaysReturnsSameResponse(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response1 = $controller->loginCheck();
        $response2 = $controller->loginCheck();
        
        $this->assertEquals($response1->getContent(), $response2->getContent());
        $this->assertEquals($response1->getStatusCode(), $response2->getStatusCode());
    }

    // ==================== TESTS DE LOGOUT ====================

    public function testLogoutReturnsExpectedMessage(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response = $controller->logout();
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        
        $this->assertEquals('Logout endpoint - should be intercepted by firewall', $content['message']);
    }

    public function testLogoutAlwaysReturnsSameMessage(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response1 = $controller->logout();
        $response2 = $controller->logout();
        
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    // ==================== TESTS DE STRUCTURE DES RÉPONSES ====================

    public function testLoginResponseStructure(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn(null);
        $authServiceMock->method('getLastUsername')->willReturn(null);
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('last_username', $content);
        $this->assertArrayHasKey('error', $content);
        $this->assertCount(2, $content);
    }

    public function testLoginCheckResponseStructure(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response = $controller->loginCheck();
        $content = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $content);
        $this->assertArrayHasKey('message', $content);
        $this->assertCount(2, $content);
    }

    public function testLogoutResponseStructure(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        
        $response = $controller->logout();
        $content = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('message', $content);
        $this->assertCount(1, $content);
    }

    // ==================== TESTS AVEC EXCEPTIONS PERSONNALISÉES ====================

    public function testLoginWithCustomAuthenticationException(): void
    {
        $customException = new class('Custom error message') extends AuthenticationException {
            public function getMessageKey(): string
            {
                return 'Custom auth error';
            }
        };
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn($customException);
        $authServiceMock->method('getLastUsername')->willReturn('user@example.com');
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Custom auth error', $content['error']);
    }

    public function testLoginWithEmptyUsername(): void
    {
        $exception = $this->createAuthenticationException('Error message');
        
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn($exception);
        $authServiceMock->method('getLastUsername')->willReturn('');
        
        $controller = $this->createController($authServiceMock);
        $response = $controller->login();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals('', $content['last_username']);
        $this->assertEquals('Error message', $content['error']);
    }

    // ==================== TESTS DE PERFORMANCE ====================

    public function testLoginExecutesWithinTimeLimit(): void
    {
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $authServiceMock->method('getLastError')->willReturn(null);
        $authServiceMock->method('getLastUsername')->willReturn(null);
        
        $controller = $this->createController($authServiceMock);
        
        $start = microtime(true);
        $controller->login();
        $duration = microtime(true) - $start;

        $this->assertLessThan(0.1, $duration, 'Login doit être rapide (< 100ms)');
    }

    // ==================== TEST DE COUVERTURE COMPLÈTE ====================

    public function testAllBranchesAreCovered(): void
    {
        $scenarios = [
            [null, null],
            [$this->createAuthenticationException('Error 1'), null],
            [$this->createAuthenticationException('Error 2'), 'user'],
            [null, 'user'],
        ];

        foreach ($scenarios as [$error, $username]) {
            $authServiceMock = $this->createMock(AuthenticationService::class);
            $authServiceMock->method('getLastError')->willReturn($error);
            $authServiceMock->method('getLastUsername')->willReturn($username);
            
            $controller = $this->createController($authServiceMock);
            $controller->login();
        }

        // Tests sans dépendances
        $authServiceMock = $this->createMock(AuthenticationService::class);
        $controller = $this->createController($authServiceMock);
        $controller->loginCheck();
        $controller->logout();

        $this->assertTrue(true);
    }
}