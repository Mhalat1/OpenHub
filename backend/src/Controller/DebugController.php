<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DebugController extends AbstractController
{
    #[Route('/debug/jwt', name: 'debug_jwt')]
    public function debugJwt(): JsonResponse
    {
        $privateKeyPath = $this->getParameter('kernel.project_dir') . '/config/jwt/private.pem';
        $publicKeyPath = $this->getParameter('kernel.project_dir') . '/config/jwt/public.pem';
        
        return new JsonResponse([
            'private_key_exists' => file_exists($privateKeyPath),
            'public_key_exists' => file_exists($publicKeyPath),
            'private_key_readable' => is_readable($privateKeyPath),
            'public_key_readable' => is_readable($publicKeyPath),
            'private_key_path' => $privateKeyPath,
            'public_key_path' => $publicKeyPath,
            'env_jwt_secret' => $_ENV['JWT_SECRET_KEY'] ?? 'NOT SET',
            'env_jwt_public' => $_ENV['JWT_PUBLIC_KEY'] ?? 'NOT SET',
            'env_jwt_passphrase' => isset($_ENV['JWT_PASSPHRASE']) ? '***SET***' : 'NOT SET',
        ]);
    }
}