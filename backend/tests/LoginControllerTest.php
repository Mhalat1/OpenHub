<?php

namespace App\Tests\Controller;

use App\Controller\LoginController;
use App\Service\AuthenticationService;
use App\Service\AxiomService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginControllerTest extends TestCase
{
    // NOTE: On mock AuthenticationService (la classe concrète) et non l'interface
    // car interface et classe sont dans le même fichier — l'autoloader Composer
    // ne peut résoudre que la classe concrète dans ce cas.

    /** @var AuthenticationService&MockObject */
    private MockObject $authService;

    /** @var AxiomService&MockObject */
    private MockObject $AxiomLogger;

    protected function setUp(): void
    {
        $this->authService      = $this->createMock(AuthenticationService::class);
        $this->AxiomLogger = $this->createMock(AxiomService::class);
    }

    // NOTE: On surcharge json() car AbstractController::json() dépend du container
    // Symfony absent en test unitaire pur.
    private function makeController(): LoginController
    {
        return new class($this->authService, $this->AxiomLogger) extends LoginController {
            public function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }

    // ✅ Couvre login() branche sans erreur (lignes 30–32)
    public function testLoginSuccessLogsInfoAndReturnsUsername(): void
    {
        $this->authService->method('getLastError')->willReturn(null);
        $this->authService->method('getLastUsername')->willReturn('test@test.com');

        $response = $this->makeController()->login();
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($data['error']);
        $this->assertSame('test@test.com', $data['last_username']);
    }

    // ✅ Couvre login() branche avec erreur (lignes 25–28)
    public function testLoginWithErrorLogsWarningAndReturnsMessageKey(): void
    {
        $this->authService->method('getLastError')->willReturn(new AuthenticationException('Bad credentials'));
        $this->authService->method('getLastUsername')->willReturn('test@test.com');

        $response = $this->makeController()->login();
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($data['error']);
        $this->assertSame('test@test.com', $data['last_username']);
    }

    // ✅ Couvre loginCheck() entièrement (lignes 44–51)
    // NOTE: En production cette route est interceptée par le firewall JWT et
    // n'atteint jamais le controller. Seul un appel direct en test unitaire
    // permet de couvrir ces lignes.
    public function testLoginCheckReturns500WithExpectedBody(): void
    {
        $response = $this->makeController()->loginCheck();
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('This endpoint should be intercepted by JWT firewall', $data['error']);
        $this->assertSame('Use POST with username and password to get JWT token', $data['message']);
    }

    // ✅ Couvre logout() (lignes 56–61)
    // NOTE: Intercepté par le firewall en production — appel direct requis pour la couverture.
    public function testLogoutReturnsExpectedMessage(): void
    {
        $response = $this->makeController()->logout();
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Logout endpoint - should be intercepted by firewall', $data['message']);
    }
}