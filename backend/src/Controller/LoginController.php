<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AuthenticationServiceInterface;


class LoginController extends AbstractController
    {
        public function __construct(private AuthenticationServiceInterface $authService) {}

        #[Route(path: '/api/login', name: 'app_api_login', methods: ['POST'])]
        public function login(): Response
        {
            $error = $this->authService->getLastError();
            $lastUsername = $this->authService->getLastUsername();

            return $this->json([
                'last_username' => $lastUsername,
                'error' => $error ? $error->getMessageKey() : null,
            ]);
        }
}

class LogoutController extends AbstractController
    {    
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
