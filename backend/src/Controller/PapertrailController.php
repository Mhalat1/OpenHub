<?php

namespace App\Controller;

use App\Service\PapertrailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PapertrailController extends AbstractController
{
    public function __construct(
        private PapertrailService $papertrailLogger,
    ) {}

    #[Route('/api/test/papertrail', name: 'test_papertrail', methods: ['GET'])]
    public function testPapertrail(): JsonResponse
    {
        try {
            $testMessage = "Test depuis open-hub - " . date('Y-m-d H:i:s');

            $this->papertrailLogger->info($testMessage, [
                'source'    => 'browser_test',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success'         => true,
                'message'         => 'Log envoyé à Papertrail',
                'log'             => $testMessage,
                'papertrail_url'  => $_ENV['PAPERTRAIL_URL'] ?? 'non défini'
            ]);

        } catch (\Exception $e) {
            $this->papertrailLogger->error('Papertrail test failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}