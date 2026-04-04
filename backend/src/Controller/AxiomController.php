<?php

namespace App\Controller;

use App\Service\AxiomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AxiomController extends AbstractController
{
    public function __construct(
        private AxiomService $AxiomLogger,
    ) {}

    #[Route('/api/test/Axiom', name: 'test_Axiom', methods: ['GET'])]
    public function testAxiom(): JsonResponse
    {
        try {
            $testMessage = "Test depuis open-hub - " . date('Y-m-d H:i:s');

            $this->AxiomLogger->info($testMessage, [
                'source'    => 'browser_test',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success'         => true,
                'message'         => 'Log envoyé à Axiom',
                'log'             => $testMessage,
                'Axiom_url'  => $_ENV['Axiom_URL'] ?? 'non défini'
            ]);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Axiom test failed', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}