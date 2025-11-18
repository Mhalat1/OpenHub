<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AuthenticationServiceInterface;


class LoginController extends AbstractController
    {
        public function __construct(private AuthenticationServiceInterface $authService) {}

        #[Route(path: '/api/login', name: 'app_login', methods: ['POST'])]
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

class LoginCheckController extends AbstractController   
    {
        #[Route(path: '/api/login_check', name: 'app_api_login_check', methods: ['POST'])]
        public function loginCheck(): Response
        {
            // This code is never executed.
            return $this->json(['message' => 'Login check endpoint']);
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










// src/Controller/DebugController.php
<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DebugController extends AbstractController
{
    #[Route('/api/debug-jwt-test', name: 'api_debug_jwt_test')]
    public function debugJwtTest(
        JWTTokenManagerInterface $jwtManager, 
        EntityManagerInterface $em
    ): JsonResponse {
        // Test simple de génération JWT
        $user = $em->getRepository(User::class)->findOneBy([]);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No user found in database',
                'users_count' => $em->getRepository(User::class)->count([])
            ], 404);
        }
        
        try {
            $token = $jwtManager->create($user);
            return new JsonResponse([
                'success' => true,
                'token' => $token,
                'test_user' => $user->getEmail(),
                'user_id' => $user->getId(),
                'message' => 'JWT generation test successful'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'JWT generation test failed'
            ], 500);
        }
    }

    #[Route('/api/debug-users', name: 'api_debug_users')]
    public function debugUsers(EntityManagerInterface $em): JsonResponse {
        $users = $em->getRepository(User::class)->findAll();
        $usersData = [];
        
        foreach ($users as $user) {
            $usersData[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles()
            ];
        }
        
        return new JsonResponse([
            'users_count' => count($users),
            'users' => $usersData
        ]);
    }
}
