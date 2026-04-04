<?php

namespace App\Controller;

use App\Service\AxiomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AuthenticationServiceInterface;

class LoginController extends AbstractController
{
    public function __construct(
        private AuthenticationServiceInterface $authService,
        private AxiomService $AxiomLogger,
    ) {}

    #[Route(path: '/api/login', name: 'app_login', methods: ['POST'])]
    public function login(): Response
    {
        $error        = $this->authService->getLastError();
        $lastUsername = $this->authService->getLastUsername();

        if ($error) {
            $this->AxiomLogger->warning('Login failed', [
                'username' => $lastUsername,
                'error'    => $error->getMessageKey(),
            ]);
        } else {
            $this->AxiomLogger->info('Login successful', [
                'username' => $lastUsername,
            ]);
        }

        return $this->json([
            'last_username' => $lastUsername,
            'error'         => $error ? $error->getMessageKey() : null,
        ]);
    }

    #[Route(path: '/api/login_check', name: 'app_api_login_check', methods: ['POST'])]
    public function loginCheck(): Response
    {
        $this->AxiomLogger->error('JWT firewall bypass detected - login_check reached controller', [
            'endpoint' => '/api/login_check',
        ]);

        return $this->json([
            'error'   => 'This endpoint should be intercepted by JWT firewall',
            'message' => 'Use POST with username and password to get JWT token'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    #[Route(path: '/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): Response
    {
        $this->AxiomLogger->warning('Logout endpoint reached controller - should be intercepted by firewall');

        return $this->json([
            'message' => 'Logout endpoint - should be intercepted by firewall'
        ]);
    }
}