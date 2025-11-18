// src/Controller/DebugAuthController.php
<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class DebugAuthController extends AbstractController
{
    #[Route('/api/debug-login', name: 'api_debug_login', methods: ['POST'])]
    public function debugLogin(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        LoggerInterface $logger
    ): JsonResponse {
        
        // Log de début
        $logger->info('🔍 DEBUG LOGIN: Starting authentication');
        
        $data = json_decode($request->getContent(), true);
        $logger->info('📥 DEBUG LOGIN: Request data', ['data' => $data]);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $logger->error('❌ DEBUG LOGIN: Missing email or password');
            return new JsonResponse(['error' => 'Email and password required'], 400);
        }
        
        // Chercher l'utilisateur
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $logger->info('👤 DEBUG LOGIN: User lookup', [
            'email' => $email,
            'user_found' => $user ? $user->getId() : 'NOT_FOUND'
        ]);
        
        if (!$user) {
            $logger->error('❌ DEBUG LOGIN: User not found', ['email' => $email]);
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Vérifier le mot de passe
        $isPasswordValid = $passwordHasher->isPasswordValid($user, $password);
        $logger->info('🔐 DEBUG LOGIN: Password validation', [
            'is_valid' => $isPasswordValid,
            'user_id' => $user->getId()
        ]);
        
        if (!$isPasswordValid) {
            $logger->error('❌ DEBUG LOGIN: Invalid password', ['email' => $email]);
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Générer le token JWT
        try {
            $token = $jwtManager->create($user);
            $logger->info('✅ DEBUG LOGIN: JWT token generated', [
                'token_length' => strlen($token),
                'user_id' => $user->getId()
            ]);
            
            return new JsonResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles()
                ],
                'debug' => 'Token generated successfully via debug endpoint'
            ]);
            
        } catch (\Exception $e) {
            $logger->error('💥 DEBUG LOGIN: JWT generation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId()
            ]);
            
            return new JsonResponse([
                'error' => 'JWT generation failed',
                'debug_error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/debug-jwt-test', name: 'api_debug_jwt_test')]
    public function debugJwtTest(JWTTokenManagerInterface $jwtManager, EntityManagerInterface $em): JsonResponse
    {
        // Test simple de génération JWT avec le premier utilisateur
        $user = $em->getRepository(User::class)->findOneBy([]);
        
        if (!$user) {
            return new JsonResponse(['error' => 'No user found in database'], 404);
        }
        
        try {
            $token = $jwtManager->create($user);
            return new JsonResponse([
                'success' => true,
                'token' => $token,
                'test_user' => $user->getEmail(),
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
}