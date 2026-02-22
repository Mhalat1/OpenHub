<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;

class PapertrailController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/test/papertrail', name: 'test_papertrail', methods: ['GET'])]
    public function testPapertrail(): JsonResponse
    {
        try {
            // Message de test simple
            $testMessage = "Test depuis OpenHub - " . date('Y-m-d H:i:s');
            
            // Envoyer le log sans informations utilisateur
            $this->logger->info($testMessage, [
                'source' => 'browser_test',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Log envoyé à Papertrail',
                'log' => $testMessage,
                'papertrail_url' => $_ENV['PAPERTRAIL_URL'] ?? 'non défini'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}