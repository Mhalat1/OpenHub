<?php

namespace App\Tests\Security;

use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticatorTest extends TestCase
{
    private ApiTokenAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApiTokenAuthenticator();
    }

    public function testSupportsReturnsTrueWithBearerToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer abc123token');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWithoutAuthorizationHeader(): void
    {
        $request = new Request();

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWithoutBearerPrefix(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testSupportsReturnsFalseWithEmptyAuthorizationHeader(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', '');

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testAuthenticateReturnsPassportWithCorrectToken(): void
    {
        $token = 'my-secret-token-123';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        
        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
    }

    public function testAuthenticateStripsBearer(): void
    {
        $token = 'token-without-bearer-prefix';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
        $this->assertStringNotContainsString('Bearer', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateWithLongToken(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
    }

    public function testAuthenticateWithSpecialCharactersInToken(): void
    {
        $token = 'token-with-special_chars.and-dashes_123';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token = $this->createMock(TokenInterface::class);
        $firewallName = 'main';

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, $firewallName);

        $this->assertNull($result);
    }

    public function testOnAuthenticationFailureReturnsJsonResponse(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Invalid credentials');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testOnAuthenticationFailureReturnsCorrectJsonContent(): void
    {
        $request = new Request();
        $exception = new AuthenticationException('Invalid credentials');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $content = json_decode($response->getContent(), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals('Authentication Failed', $content['error']);
    }

    public function testOnAuthenticationFailureWithDifferentExceptions(): void
    {
        $exceptions = [
            new AuthenticationException('Bad credentials'),
            new AuthenticationException('Token expired'),
            new AuthenticationException('Invalid token'),
        ];

        foreach ($exceptions as $exception) {
            $request = new Request();
            $response = $this->authenticator->onAuthenticationFailure($request, $exception);

            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
            
            $content = json_decode($response->getContent(), true);
            $this->assertEquals('Authentication Failed', $content['error']);
        }
    }

    public function testAuthenticateWithEmptyToken(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ');

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals('', $userBadge->getUserIdentifier());
    }

    public function testSupportsWithMultipleBearerOccurrences(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer Bearer token123');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testAuthenticateRemovesAllBearerOccurrences(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer Bearer token123');

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        // str_replace retire TOUTES les occurrences de "Bearer "
        $this->assertEquals('token123', $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationSuccessWithDifferentFirewalls(): void
    {
        $firewalls = ['main', 'api', 'admin', 'public'];

        foreach ($firewalls as $firewall) {
            $request = new Request();
            $token = $this->createMock(TokenInterface::class);

            $result = $this->authenticator->onAuthenticationSuccess($request, $token, $firewall);

            $this->assertNull($result);
        }
    }

    public function testSupportsWithUppercaseBearer(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer TOKEN123');

        $result = $this->authenticator->supports($request);

        // str_contains est sensible à la casse, donc "Bearer" avec B majuscule fonctionne
        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWithLowercaseBearer(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'bearer token123');

        $result = $this->authenticator->supports($request);

        // str_contains est sensible à la casse, donc "bearer" ne correspond pas à "Bearer"
        $this->assertFalse($result);
    }

    public function testAuthenticateWithNumericToken(): void
    {
        $token = '123456789';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationFailureResponseIsJson(): void
    {
        $request = new Request();
        $exception = new AuthenticationException();

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testSupportsWithBearerInMiddleOfHeader(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Token Bearer abc123');

        $result = $this->authenticator->supports($request);

        // str_contains cherche "Bearer" n'importe où dans la chaîne
        $this->assertTrue($result);
    }

    public function testAuthenticateWithTokenContainingSpaces(): void
    {
        $token = 'token with spaces';
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($token, $userBadge->getUserIdentifier());
    }

    public function testSupportsWithOnlyBearerKeyword(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testAuthenticateStripsBearerCaseSensitive(): void
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer bearer token');

        $passport = $this->authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        // str_replace est sensible à la casse, donc seul "Bearer " (avec espace) est retiré
        $this->assertEquals('bearer token', $userBadge->getUserIdentifier());
    }
}