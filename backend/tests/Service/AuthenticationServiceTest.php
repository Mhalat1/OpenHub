<?php

namespace App\Tests\Service;

use App\Service\AuthenticationService;
use App\Service\AuthenticationServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthenticationServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationService = new AuthenticationService($authenticationUtils);

        $this->assertInstanceOf(
            AuthenticationServiceInterface::class,
            $authenticationService
        );
    }

    public function testGetLastUsernameReturnsUsername(): void
    {
        $expectedUsername = 'john.doe@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($expectedUsername);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($expectedUsername, $result);
    }

    public function testGetLastUsernameReturnsEmptyString(): void
    {
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn('');

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals('', $result);
    }

    public function testGetLastErrorReturnsAuthenticationException(): void
    {
        $expectedException = new AuthenticationException('Invalid credentials');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($expectedException);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('Invalid credentials', $result->getMessage());
        $this->assertSame($expectedException, $result);
    }

    public function testGetLastErrorReturnsNull(): void
    {
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn(null);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertNull($result);
    }

    public function testGetLastErrorWithBadCredentialsMessage(): void
    {
        $exception = new AuthenticationException('Bad credentials');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('Bad credentials', $result->getMessage());
    }

    public function testGetLastErrorWithAccountLockedMessage(): void
    {
        $exception = new AuthenticationException('Account is locked');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('Account is locked', $result->getMessage());
    }

    public function testGetLastErrorWithAccountDisabledMessage(): void
    {
        $exception = new AuthenticationException('Account is disabled');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('Account is disabled', $result->getMessage());
    }

    public function testGetLastUsernameWithEmail(): void
    {
        $username = 'user1@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testGetLastUsernameWithAnotherEmail(): void
    {
        $username = 'user2@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testServiceDelegatesCorrectlyToAuthenticationUtils(): void
    {
        $username = 'test@example.com';
        $exception = new AuthenticationException('Test error');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);

        $usernameResult = $authenticationService->getLastUsername();
        $errorResult = $authenticationService->getLastError();

        $this->assertEquals($username, $usernameResult);
        $this->assertSame($exception, $errorResult);
    }

    public function testConstructorAcceptsAuthenticationUtils(): void
    {
        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationService = new AuthenticationService($authenticationUtils);

        $this->assertInstanceOf(AuthenticationService::class, $authenticationService);
        $this->assertInstanceOf(AuthenticationServiceInterface::class, $authenticationService);
    }

    public function testGetLastUsernameWithSpecialCharactersPlus(): void
    {
        $username = 'user+tag@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testGetLastUsernameWithSpecialCharactersDot(): void
    {
        $username = 'user.name@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testGetLastUsernameWithSpecialCharactersUnderscore(): void
    {
        $username = 'user_name@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testGetLastErrorWithEmptyMessage(): void
    {
        $exception = new AuthenticationException('');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('', $result->getMessage());
    }

    public function testGetLastUsernameWithNumericCharacters(): void
    {
        $username = 'user123@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturn($username);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastUsername();

        $this->assertEquals($username, $result);
    }

    public function testGetLastErrorWithLongMessage(): void
    {
        $longMessage = 'Account locked due to too many failed login attempts. Please try again later or reset your password.';
        $exception = new AuthenticationException($longMessage);

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals($longMessage, $result->getMessage());
    }

    public function testMultipleCalls(): void
    {
        $username1 = 'first@example.com';
        $username2 = 'second@example.com';

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastUsername')
            ->willReturnOnConsecutiveCalls($username1, $username2);

        $authenticationService = new AuthenticationService($authenticationUtils);

        $firstCall = $authenticationService->getLastUsername();
        $secondCall = $authenticationService->getLastUsername();

        $this->assertEquals($username1, $firstCall);
        $this->assertEquals($username2, $secondCall);
    }

    public function testGetLastErrorWithInvalidCsrfToken(): void
    {
        $exception = new AuthenticationException('Invalid CSRF token');

        $authenticationUtils = $this->createMock(AuthenticationUtils::class);
        $authenticationUtils
            ->method('getLastAuthenticationError')
            ->willReturn($exception);

        $authenticationService = new AuthenticationService($authenticationUtils);
        $result = $authenticationService->getLastError();

        $this->assertInstanceOf(AuthenticationException::class, $result);
        $this->assertEquals('Invalid CSRF token', $result->getMessage());
    }
}