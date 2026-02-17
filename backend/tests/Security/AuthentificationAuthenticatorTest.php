<?php

namespace App\Tests\Security;

use App\Security\AuthentificationAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class AuthentificationAuthenticatorTest extends TestCase
{
    public function testConstructorAcceptsUrlGenerator(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $this->assertInstanceOf(AuthentificationAuthenticator::class, $authenticator);
    }

    public function testAuthenticateReturnsPassport(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $request = $this->createRequestWithPayload(
            'user@example.com',
            'password123',
            'csrf_token_value'
        );

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(Passport::class, $passport);
    }

    public function testAuthenticateCreatesUserBadgeWithCorrectIdentifier(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $motDePasse = 'user@example.com';
        $request = $this->createRequestWithPayload(
            $motDePasse,
            'password123',
            'csrf_token_value'
        );

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertInstanceOf(UserBadge::class, $userBadge);
        $this->assertEquals($motDePasse, $userBadge->getUserIdentifier());
    }


    public function testAuthenticateCreatesCsrfTokenBadge(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $csrfToken = 'my_csrf_token_123';
        $request = $this->createRequestWithPayload(
            'user@example.com',
            'password123',
            $csrfToken
        );

        $passport = $authenticator->authenticate($request);

        $csrfBadge = $passport->getBadge(CsrfTokenBadge::class);
        $this->assertInstanceOf(CsrfTokenBadge::class, $csrfBadge);
    }

    public function testAuthenticateCreatesRememberMeBadge(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $request = $this->createRequestWithPayload(
            'user@example.com',
            'password123',
            'csrf_token'
        );

        $passport = $authenticator->authenticate($request);

        $rememberMeBadge = $passport->getBadge(RememberMeBadge::class);
        $this->assertInstanceOf(RememberMeBadge::class, $rememberMeBadge);
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $targetPath = '/dashboard';
        $firewallName = 'main';

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('get')
            ->with('_security.' . $firewallName . '.target_path')
            ->willReturn($targetPath);

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $authenticator->onAuthenticationSuccess($request, $token, $firewallName);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($targetPath, $response->headers->get('Location'));
    }

    public function testOnAuthenticationSuccessThrowsExceptionWhenNoTargetPath(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);
        $firewallName = 'main';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('TODO: provide a valid redirect inside');

        $authenticator->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function testGetLoginUrlReturnsCorrectRoute(): void
    {
        $expectedUrl = '/login';

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(AuthentificationAuthenticator::LOGIN_ROUTE)
            ->willReturn($expectedUrl);

        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $request = new Request();

        $loginUrl = $this->invokeProtectedMethod(
            $authenticator,
            'getLoginUrl',
            [$request]
        );

        $this->assertEquals($expectedUrl, $loginUrl);
    }

    public function testLoginRouteConstant(): void
    {
        $this->assertEquals('app_login', AuthentificationAuthenticator::LOGIN_ROUTE);
    }

    public function testAuthenticateWithEmptyCredentials(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $request = $this->createRequestWithPayload('', '', '');

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals('', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateWithSpecialCharactersInUsername(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $motDePasse = 'user+tag@example.com';
        $request = $this->createRequestWithPayload(
            $motDePasse,
            'password123',
            'csrf_token'
        );

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals($motDePasse, $userBadge->getUserIdentifier());
    }

    public function testAuthenticateWithLongPassword(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $longPassword = str_repeat('a', 100);
        $request = $this->createRequestWithPayload(
            'user@example.com',
            $longPassword,
            'csrf_token'
        );

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(Passport::class, $passport);
    }

    public function testOnAuthenticationSuccessWithDifferentFirewalls(): void
    {
        $firewalls = ['main', 'api', 'admin'];
        $targetPath = '/success';

        foreach ($firewalls as $firewall) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $authenticator = new AuthentificationAuthenticator($urlGenerator);

            $session = $this->createMock(SessionInterface::class);
            $session
                ->expects($this->once())
                ->method('get')
                ->with('_security.' . $firewall . '.target_path')
                ->willReturn($targetPath);

            $request = new Request();
            $request->setSession($session);

            $token = $this->createMock(TokenInterface::class);

            $response = $authenticator->onAuthenticationSuccess($request, $token, $firewall);

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertEquals($targetPath, $response->headers->get('Location'));
        }
    }

    public function testAuthenticateWithComplexCsrfToken(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $csrfToken = 'AbC123-_+=./~!@#$%';
        $request = $this->createRequestWithPayload(
            'user@example.com',
            'password123',
            $csrfToken
        );

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(Passport::class, $passport);
        $this->assertInstanceOf(CsrfTokenBadge::class, $passport->getBadge(CsrfTokenBadge::class));
    }

    public function testAuthenticateWithNumericUsername(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $request = $this->createRequestWithPayload(
            '123456',
            'password123',
            'csrf_token'
        );

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertEquals('123456', $userBadge->getUserIdentifier());
    }

    public function testOnAuthenticationSuccessReturnsRedirectResponse(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $authenticator = new AuthentificationAuthenticator($urlGenerator);

        $session = $this->createMock(SessionInterface::class);
        $session
            ->expects($this->once())
            ->method('get')
            ->willReturn('/home');

        $request = new Request();
        $request->setSession($session);

        $token = $this->createMock(TokenInterface::class);

        $response = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * Helper method to create a request with payload using REAL InputBag
     */
    private function createRequestWithPayload(
        string $motDePasse,
        string $password,
        string $csrfToken
    ): Request {
        // Créer une vraie instance d'InputBag (elle est final, donc on ne peut pas la mocker)
        $payload = new InputBag([
            'motDePasse' => $motDePasse,
            'password' => $password,
            '_csrf_token' => $csrfToken,
        ]);

        // Mock de la Request
        $request = $this->createMock(Request::class);

        $request
            ->expects($this->any())
            ->method('getPayload')
            ->willReturn($payload);

        // Mock de la session
        $session = $this->createMock(SessionInterface::class);
        $request
            ->expects($this->any())
            ->method('getSession')
            ->willReturn($session);

        return $request;
    }

    /**
     * Helper method to invoke protected methods
     */
    private function invokeProtectedMethod(
        object $object,
        string $methodName,
        array $parameters = []
    ) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}