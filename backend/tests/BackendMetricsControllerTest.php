<?php

namespace App\Tests\Controller;

use App\Controller\BackendMetricsController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class BackendMetricsControllerTest extends WebTestCase
{
    private $client;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->tmpDir = sys_get_temp_dir();
        $this->cleanupMetricsFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupMetricsFiles();
        parent::tearDown();
    }

    private function cleanupMetricsFiles(): void
    {
        foreach (['bm_2xx.txt', 'bm_4xx.txt', 'bm_5xx.txt', 'bm_times.txt'] as $file) {
            $path = $this->tmpDir . '/' . $file;
            if (file_exists($path)) unlink($path);
        }
    }

    // ========== TESTS COLLECT ==========

    public function testCollectEndpointExists(): void
    {
        $this->assertTrue(method_exists(BackendMetricsController::class, 'collect'));
    }

    public function testCollectIncrement2xx(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 200, 'duration' => 0.1]));

        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_2xx.txt'));
    }

    public function testCollectIncrement4xx(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 404, 'duration' => 0.05]));

        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_4xx.txt'));
    }

    public function testCollectIncrement5xx(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 500, 'duration' => 0.2]));

        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_5xx.txt'));
    }

    public function testCollectReturnsOkJson(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 200, 'duration' => 0.1]));

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(['ok' => true], $response);
    }

    public function testCollectStoresDuration(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 200, 'duration' => 0.42]));

        $times = json_decode(file_get_contents($this->tmpDir . '/bm_times.txt'), true);
        $this->assertContains(0.42, $times);
    }

    public function testCollectLimitsHistoryTo1000(): void
    {
        file_put_contents($this->tmpDir . '/bm_times.txt', json_encode(array_fill(0, 1000, 0.1)));

        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['status' => 200, 'duration' => 0.99]));

        $stored = json_decode(file_get_contents($this->tmpDir . '/bm_times.txt'), true);
        $this->assertCount(1000, $stored);
        $this->assertEquals(0.99, end($stored));
    }

    public function testCollectDefaultsTo2xxWhenNoStatus(): void
    {
        $this->client->request('POST', '/metrics/backend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['duration' => 0.1]));

        $this->assertEquals(1, (int)@file_get_contents($this->tmpDir . '/bm_2xx.txt'));
    }

    public function testCollectMultipleRequests(): void
    {
        foreach ([200, 200, 404, 500] as $status) {
            $this->client->request('POST', '/metrics/backend/collect', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['status' => $status, 'duration' => 0.1]));
        }

        $this->assertEquals(2, (int)file_get_contents($this->tmpDir . '/bm_2xx.txt'));
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_4xx.txt'));
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/bm_5xx.txt'));
    }

    // ========== TESTS METRICS ==========

    public function testMetricsEndpointExists(): void
    {
        $this->assertTrue(method_exists(BackendMetricsController::class, 'metrics'));
    }

    public function testMetricsEndpointIsAccessible(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testMetricsReturnsTextPlain(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('text/plain', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testMetricsReturnsPrometheusVersion(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('version=0.0.4', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testMetricsContainsTotalRequests(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_requests_total', $this->client->getResponse()->getContent());
    }

    public function testMetricsContains2xx(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_requests_2xx', $this->client->getResponse()->getContent());
    }

    public function testMetricsContains4xx(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_requests_4xx', $this->client->getResponse()->getContent());
    }

    public function testMetricsContains5xx(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_requests_5xx', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsErrorRate(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_error_rate_percent', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsAvailability(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_availability_percent', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsP95(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_response_time_p95_seconds', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsMemory(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_memory_usage_mb', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsCpu(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_cpu_load_1m', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsUptime(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $this->assertStringContainsString('app_uptime_seconds', $this->client->getResponse()->getContent());
    }

    public function testMetricsValuesAreNumeric(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = str_replace("\r\n", "\n", $this->client->getResponse()->getContent());

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $this->assertIsNumeric(trim(end($parts)), "Valeur non numérique dans : {$line}");
            }
        }
    }

    public function testMetricsFollowPrometheusFormat(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        $hasHelp = $hasType = $hasMetric = false;
        foreach (explode("\n", $content) as $line) {
            if (str_starts_with($line, '# HELP')) $hasHelp = true;
            if (str_starts_with($line, '# TYPE')) $hasType = true;
            if (!empty(trim($line)) && !str_starts_with($line, '#')) $hasMetric = true;
        }

        $this->assertTrue($hasHelp);
        $this->assertTrue($hasType);
        $this->assertTrue($hasMetric);
    }

    public function testAllMetricsAreGaugeType(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        foreach ([
            'app_requests_total',
            'app_requests_2xx',
            'app_requests_4xx',
            'app_requests_5xx',
            'app_error_rate_percent',
            'app_availability_percent',
            'app_response_time_p95_seconds',
            'app_memory_usage_mb',
            'app_cpu_load_1m',
            'app_uptime_seconds',
        ] as $metric) {
            $this->assertStringContainsString(
                "# TYPE {$metric} gauge",
                $content,
                "{$metric} doit être de type gauge"
            );
        }
    }

    public function testAvailabilityDefaultsTo100(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_availability_percent +([\d.]+)/', $content, $matches);
        $this->assertEquals(100.0, (float)($matches[1] ?? -1));
    }

    public function testErrorRateDefaultsTo0(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_error_rate_percent +([\d.]+)/', $content, $matches);
        $this->assertEquals(0.0, (float)($matches[1] ?? -1));
    }

    public function testMemoryUsageIsPositive(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_memory_usage_mb +([\d.]+)/', $content, $matches);
        $this->assertGreaterThan(0, (float)($matches[1] ?? 0));
    }

    public function testP95IsZeroWithNoData(): void
    {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_response_time_p95_seconds +([\d.]+)/', $content, $matches);
        $this->assertEquals(0.0, (float)($matches[1] ?? -1));
    }

    public function testMetricsReflectsCollectedData(): void
    {
        foreach ([200, 200, 200, 500] as $status) {
            $this->client->request('POST', '/metrics/backend/collect', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['status' => $status, 'duration' => 0.1]));
        }

        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_requests_2xx +([\d.]+)/', $content, $m2xx);
        preg_match('/app_requests_5xx +([\d.]+)/', $content, $m5xx);
        preg_match('/app_requests_total +([\d.]+)/', $content, $mTotal);

        $this->assertEquals(3, (int)($m2xx[1] ?? -1));
        $this->assertEquals(1, (int)($m5xx[1] ?? -1));
        $this->assertEquals(4, (int)($mTotal[1] ?? -1));
    }

    public function testErrorRateCalculation(): void
    {
        foreach ([200, 200, 200, 500] as $status) {
            $this->client->request('POST', '/metrics/backend/collect', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['status' => $status, 'duration' => 0.1]));
        }

        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_error_rate_percent +([\d.]+)/', $content, $matches);
        $this->assertEquals(25.0, (float)($matches[1] ?? -1));
    }

    public function testAvailabilityCalculation(): void
    {
        foreach ([200, 200, 200, 500] as $status) {
            $this->client->request('POST', '/metrics/backend/collect', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['status' => $status, 'duration' => 0.1]));
        }

        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_availability_percent +([\d.]+)/', $content, $matches);
        $this->assertEquals(75.0, (float)($matches[1] ?? -1));
    }

    public function testP95Calculation(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->client->request('POST', '/metrics/backend/collect', [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['status' => 200, 'duration' => (float)$i]));
        }

        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/app_response_time_p95_seconds +([\d.]+)/', $content, $matches);
        $this->assertEquals(19.0, (float)($matches[1] ?? 0));
    }

    public function testMetricsEndpointRespondsQuickly(): void
    {
        $start = microtime(true);
        $this->client->request('GET', '/metrics/backend');
        $this->assertResponseIsSuccessful();
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    public function testMultipleCallsAreStable(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('GET', '/metrics/backend');
            $this->assertResponseIsSuccessful();
        }
    }
}