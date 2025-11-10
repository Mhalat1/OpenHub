<?php
// src/Controller/DefaultController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'OK',
            'message' => 'OpenHub Backend API is running',
            'timestamp' => time(),
            'endpoints' => [
                'register' => '/api/userCreate',
                'login' => '/api/login_check',
                'health' => '/health'
            ]
        ]);
    }

    #[Route('/health', name: 'app_health')]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
            'timestamp' => time()
        ]);
    }
}