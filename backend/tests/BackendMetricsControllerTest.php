<?php

namespace App\Tests\Controller;

use App\Controller\BackendMetricsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Prometheus\RenderTextFormat;

class BackendMetricsControllerTest extends TestCase
{
    private string $tmpDir;
    private BackendMetricsController $controller;

    protected function setUp(): void
    {
        $this->controller = new BackendMetricsController();
        $this->tmpDir = sys_get_temp_dir();
        
        // Nettoyer les fichiers temporaires avant chaque test
        $this->cleanupTempFiles();
    }

    protected function tearDown(): void
    {
        // Nettoyer après les tests
        $this->cleanupTempFiles();
    }

    private function cleanupTempFiles(): void
    {
        $files = [
            'bm_2xx.txt',
            'bm_4xx.txt',
            'bm_5xx.txt',
            'bm_times.txt'
        ];

        foreach ($files as $file) {
            $path = $this->tmpDir . '/' . $file;
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Test la méthode collect avec différents statuts
     */
    public function testCollectWithVariousStatuses(): void
    {
        // Test avec statut 200 (succès)
        $request = new Request(
            content: json_encode(['status' => 200, 'duration' => 0.5])
        );
        $response = $this->controller->collect($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['ok']);
        
        // Vérifier que le fichier 2xx a été incrémenté
        $this->assertFileExists($this->tmpDir . '/bm_2xx.txt');
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_2xx.txt'));

        // Test avec statut 400 (client error)
        $request = new Request(
            content: json_encode(['status' => 404, 'duration' => 0.3])
        );
        $response = $this->controller->collect($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Vérifier que le fichier 4xx a été incrémenté
        $this->assertFileExists($this->tmpDir . '/bm_4xx.txt');
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_4xx.txt'));

        // Test avec statut 500 (server error)
        $request = new Request(
            content: json_encode(['status' => 500, 'duration' => 0.8])
        );
        $response = $this->controller->collect($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Vérifier que le fichier 5xx a été incrémenté
        $this->assertFileExists($this->tmpDir . '/bm_5xx.txt');
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_5xx.txt'));

        // Vérifier que les temps ont été enregistrés
        $this->assertFileExists($this->tmpDir . '/bm_times.txt');
        $times = json_decode(file_get_contents($this->tmpDir . '/bm_times.txt'), true);
        $this->assertCount(3, $times);
        $this->assertEquals(0.5, $times[0]);
        $this->assertEquals(0.3, $times[1]);
        $this->assertEquals(0.8, $times[2]);
    }

    /**
     * Test collect sans données (valeurs par défaut)
     */
    public function testCollectWithDefaultValues(): void
    {
        $request = new Request(content: '{}');
        $response = $this->controller->collect($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Vérifier que le fichier 2xx a été incrémenté (status par défaut = 200)
        $this->assertFileExists($this->tmpDir . '/bm_2xx.txt');
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_2xx.txt'));
        
        // Vérifier que le temps 0 a été enregistré
        $times = json_decode(file_get_contents($this->tmpDir . '/bm_times.txt'), true);
        $this->assertEquals(0, $times[0]);
    }

    /**
     * Test collect avec données invalides
     */
    public function testCollectWithInvalidData(): void
    {
        $request = new Request(content: 'not json');
        $response = $this->controller->collect($request);
        
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        // Vérifier que les valeurs par défaut ont été utilisées
        $this->assertFileExists($this->tmpDir . '/bm_2xx.txt');
    }

    /**
     * Test la méthode metrics
     */
    public function testMetrics(): void
    {
        // D'abord, collecter quelques données
        $this->collectSampleData();

        // Appeler metrics
        $response = $this->controller->metrics();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(
            RenderTextFormat::MIME_TYPE,
            $response->headers->get('Content-Type')
        );
        
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
        
        // Vérifier que les métriques Prometheus sont présentes
        $this->assertStringContainsString('app_requests_total', $content);
        $this->assertStringContainsString('app_requests_2xx', $content);
        $this->assertStringContainsString('app_requests_4xx', $content);
        $this->assertStringContainsString('app_requests_5xx', $content);
        $this->assertStringContainsString('app_error_rate_percent', $content);
        $this->assertStringContainsString('app_availability_percent', $content);
        $this->assertStringContainsString('app_response_time_p95_seconds', $content);
        $this->assertStringContainsString('app_cpu_load_1m', $content);
        $this->assertStringContainsString('app_memory_usage_mb', $content);
        $this->assertStringContainsString('app_memory_peak_mb', $content);
        $this->assertStringContainsString('app_uptime_seconds', $content);
    }

    /**
     * Test metrics avec aucun fichier existant
     */
    public function testMetricsWithNoData(): void
    {
        // S'assurer qu'aucun fichier n'existe
        $this->cleanupTempFiles();
        
        $response = $this->controller->metrics();
        
        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        
        // Vérifier que les métriques sont à 0 par défaut
        $this->assertStringContainsString('app_requests_total 0', $content);
        $this->assertStringContainsString('app_requests_2xx 0', $content);
        $this->assertStringContainsString('app_requests_4xx 0', $content);
        $this->assertStringContainsString('app_requests_5xx 0', $content);
        $this->assertStringContainsString('app_error_rate_percent 0', $content);
        $this->assertStringContainsString('app_availability_percent 100', $content);
        $this->assertStringContainsString('app_response_time_p95_seconds 0', $content);
    }

    /**
     * Test le calcul du P95 avec différentes valeurs
     */
    public function testP95Calculation(): void
    {
        // Simuler plusieurs temps de réponse
        $times = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0, 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 2.0];
        file_put_contents($this->tmpDir . '/bm_times.txt', json_encode($times));
        
        // Ajouter aussi des requêtes pour les compteurs
        for ($i = 0; $i < 20; $i++) {
            $this->incrementFile('bm_2xx.txt');
        }
        
        $response = $this->controller->metrics();
        $content = $response->getContent();
        
        // Pour 20 valeurs, le P95 devrait être à la position 19 (index 18) : 1.9
        // Mais arrondi à 3 décimales
        $this->assertStringContainsString('app_response_time_p95_seconds 1.9', $content);
    }

    /**
     * Test avec un grand nombre de temps (limite à 1000)
     */
    public function testTimesLimit(): void
    {
        // Créer 1100 temps
        $times = array_fill(0, 1100, 0.5);
        file_put_contents($this->tmpDir . '/bm_times.txt', json_encode($times));
        
        // Ajouter une nouvelle mesure via collect
        $request = new Request(
            content: json_encode(['status' => 200, 'duration' => 1.0])
        );
        $this->controller->collect($request);
        
        // Vérifier que le fichier ne contient que 1000 éléments (les plus récents)
        $newTimes = json_decode(file_get_contents($this->tmpDir . '/bm_times.txt'), true);
        $this->assertCount(1000, $newTimes);
        $this->assertEquals(1.0, $newTimes[999]); // Le dernier élément doit être 1.0
    }

    /**
     * Test les métriques CPU et mémoire
     */
    public function testSystemMetrics(): void
    {
        $response = $this->controller->metrics();
        $content = $response->getContent();
        
        // Vérifier que CPU et mémoire sont des nombres valides
        $this->assertMatchesRegularExpression('/app_cpu_load_1m \d+\.?\d*/', $content);
        $this->assertMatchesRegularExpression('/app_memory_usage_mb \d+\.?\d*/', $content);
        $this->assertMatchesRegularExpression('/app_memory_peak_mb \d+\.?\d*/', $content);
    }

    /**
     * Test la méthode privée increment via collect
     */
    public function testIncrementMethod(): void
    {
        // Faire plusieurs appels pour incrémenter les compteurs
        for ($i = 0; $i < 5; $i++) {
            $request = new Request(
                content: json_encode(['status' => 200])
            );
            $this->controller->collect($request);
        }
        
        for ($i = 0; $i < 3; $i++) {
            $request = new Request(
                content: json_encode(['status' => 404])
            );
            $this->controller->collect($request);
        }
        
        for ($i = 0; $i < 2; $i++) {
            $request = new Request(
                content: json_encode(['status' => 500])
            );
            $this->controller->collect($request);
        }
        
        // Vérifier les compteurs
        $this->assertEquals(5, (int)file_get_contents($this->tmpDir . '/bm_2xx.txt'));
        $this->assertEquals(3, (int)file_get_contents($this->tmpDir . '/bm_4xx.txt'));
        $this->assertEquals(2, (int)file_get_contents($this->tmpDir . '/bm_5xx.txt'));
        
        // Vérifier le calcul des métriques
        $response = $this->controller->metrics();
        $content = $response->getContent();
        
        // Total = 5+3+2 = 10
        $this->assertStringContainsString('app_requests_total 10', $content);
        // Error rate = (3+2)/10 * 100 = 50%
        $this->assertStringContainsString('app_error_rate_percent 50', $content);
        // Availability = (10-2)/10 * 100 = 80%
        $this->assertStringContainsString('app_availability_percent 80', $content);
    }

    private function collectSampleData(): void
    {
        // Simuler 10 requêtes 2xx
        for ($i = 0; $i < 10; $i++) {
            $this->incrementFile('bm_2xx.txt');
        }
        
        // Simuler 3 requêtes 4xx
        for ($i = 0; $i < 3; $i++) {
            $this->incrementFile('bm_4xx.txt');
        }
        
        // Simuler 2 requêtes 5xx
        for ($i = 0; $i < 2; $i++) {
            $this->incrementFile('bm_5xx.txt');
        }
        
        // Simuler des temps
        $times = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1.0];
        file_put_contents($this->tmpDir . '/bm_times.txt', json_encode($times));
    }

    private function incrementFile(string $file): void
    {
        $path = $this->tmpDir . '/' . $file;
        $current = (int)@file_get_contents($path);
        file_put_contents($path, $current + 1);
    }
}