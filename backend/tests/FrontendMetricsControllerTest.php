<?php

namespace App\Tests\Controller;

use App\Controller\FrontendMetricsController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FrontendMetricsControllerTest extends WebTestCase
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
        foreach ([
            'fm_lcp.txt', 'fm_fid.txt', 'fm_cls.txt', 'fm_plt.txt',
            'fm_js_errors.txt', 'fm_page_views.txt', 'fm_bounces.txt', 'fm_sessions.txt'
        ] as $file) {
            $path = $this->tmpDir . '/' . $file;
            if (file_exists($path)) unlink($path);
        }
    }

    private function collect(array $data): void
    {
        $this->client->request('POST', '/metrics/frontend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($data));
    }

    // ========== TESTS COLLECT ==========

    public function testCollectEndpointExists(): void
    {
        $this->assertTrue(method_exists(FrontendMetricsController::class, 'collect'));
    }

    public function testCollectReturnsOkJson(): void
    {
        $this->collect(['lcp' => 1.2]);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(['ok' => true], $response);
    }

    public function testCollectStoresLcp(): void
    {
        $this->collect(['lcp' => 2.45]);
        $this->assertEquals('2.45', file_get_contents($this->tmpDir . '/fm_lcp.txt'));
    }

    public function testCollectStoresFid(): void
    {
        $this->collect(['fid' => 0.08]);
        $this->assertEquals('0.08', file_get_contents($this->tmpDir . '/fm_fid.txt'));
    }

    public function testCollectStoresCls(): void
    {
        $this->collect(['cls' => 0.12]);
        $this->assertEquals('0.12', file_get_contents($this->tmpDir . '/fm_cls.txt'));
    }

    public function testCollectStoresPageLoadTime(): void
    {
        $this->collect(['page_load_time' => 3.7]);
        $this->assertEquals('3.7', file_get_contents($this->tmpDir . '/fm_plt.txt'));
    }

    public function testCollectStoresJsErrors(): void
    {
        $this->collect(['js_errors' => 5]);
        $this->assertEquals('5', file_get_contents($this->tmpDir . '/fm_js_errors.txt'));
    }

    public function testCollectIncrementsPageViews(): void
    {
        $this->collect(['page_views' => 3]);
        $this->collect(['page_views' => 2]);
        $this->assertEquals(5, (int)file_get_contents($this->tmpDir . '/fm_page_views.txt'));
    }

    public function testCollectIncrementsBounceAndSessions(): void
    {
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 1]);
        $this->assertEquals(2, (int)file_get_contents($this->tmpDir . '/fm_bounces.txt'));
        $this->assertEquals(2, (int)file_get_contents($this->tmpDir . '/fm_sessions.txt'));
    }

    public function testCollectIgnoresMissingFields(): void
    {
        $this->collect(['lcp' => 1.5]);
        $this->assertResponseIsSuccessful();
        $this->assertFileDoesNotExist($this->tmpDir . '/fm_fid.txt');
        $this->assertFileDoesNotExist($this->tmpDir . '/fm_cls.txt');
    }

    public function testCollectAllFieldsAtOnce(): void
    {
        $this->collect([
            'lcp' => 1.8,
            'fid' => 0.05,
            'cls' => 0.08,
            'page_load_time' => 2.1,
            'js_errors' => 2,
            'page_views' => 10,
            'bounce' => 1,
        ]);

        $this->assertEquals('1.8', file_get_contents($this->tmpDir . '/fm_lcp.txt'));
        $this->assertEquals('0.05', file_get_contents($this->tmpDir . '/fm_fid.txt'));
        $this->assertEquals('0.08', file_get_contents($this->tmpDir . '/fm_cls.txt'));
        $this->assertEquals('2.1', file_get_contents($this->tmpDir . '/fm_plt.txt'));
        $this->assertEquals('2', file_get_contents($this->tmpDir . '/fm_js_errors.txt'));
        $this->assertEquals(10, (int)file_get_contents($this->tmpDir . '/fm_page_views.txt'));
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/fm_bounces.txt'));
        $this->assertEquals(1, (int)file_get_contents($this->tmpDir . '/fm_sessions.txt'));
    }

    // ========== TESTS METRICS ==========

    public function testMetricsEndpointIsAccessible(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testMetricsReturnsTextPlain(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('text/plain', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testMetricsReturnsPrometheusVersion(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('version=0.0.4', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testMetricsContainsLcp(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_lcp_seconds', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsFid(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_fid_seconds', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsCls(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_cls_ratio', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsPageLoad(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_page_load_seconds', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsJsErrors(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_js_errors_total', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsPageViews(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_page_views_total', $this->client->getResponse()->getContent());
    }

    public function testMetricsContainsBounceRate(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertStringContainsString('frontend_bounce_rate_percent', $this->client->getResponse()->getContent());
    }

    public function testAllMetricsAreGaugeType(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        foreach ([
            'frontend_lcp_seconds',
            'frontend_fid_seconds',
            'frontend_cls_ratio',
            'frontend_page_load_seconds',
            'frontend_js_errors_total',
            'frontend_page_views_total',
            'frontend_bounce_rate_percent',
        ] as $metric) {
            $this->assertStringContainsString(
                "# TYPE {$metric} gauge",
                $content,
                "{$metric} doit être de type gauge"
            );
        }
    }

    public function testMetricsValuesAreNumeric(): void
    {
        $this->client->request('GET', '/metrics/frontend');
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
        $this->client->request('GET', '/metrics/frontend');
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

    // ========== TESTS VALEURS RÉELLES ==========

    public function testLcpValueIsReflectedInMetrics(): void
    {
        $this->collect(['lcp' => 2.5]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_lcp_seconds\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(2.5, (float)($matches[1] ?? -1));
    }

    public function testFidValueIsReflectedInMetrics(): void
    {
        $this->collect(['fid' => 0.1]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_fid_seconds\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(0.1, (float)($matches[1] ?? -1));
    }

    public function testClsValueIsReflectedInMetrics(): void
    {
        $this->collect(['cls' => 0.25]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_cls_ratio\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(0.25, (float)($matches[1] ?? -1));
    }

    public function testPageLoadValueIsReflectedInMetrics(): void
    {
        $this->collect(['page_load_time' => 4.2]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_page_load_seconds\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(4.2, (float)($matches[1] ?? -1));
    }

    public function testJsErrorsValueIsReflectedInMetrics(): void
    {
        $this->collect(['js_errors' => 7]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_js_errors_total\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(7, (int)($matches[1] ?? -1));
    }

    public function testPageViewsAccumulate(): void
    {
        $this->collect(['page_views' => 5]);
        $this->collect(['page_views' => 8]);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_page_views_total\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(13, (int)($matches[1] ?? -1));
    }

    public function testBounceRateCalculation(): void
    {
        // 2 bounces sur 4 sessions → 50%
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 0]); // pas de bounce mais pas traité car non isset(bounce)
        // simulate 2 sessions non-bounce
        file_put_contents($this->tmpDir . '/fm_bounces.txt', 2);
        file_put_contents($this->tmpDir . '/fm_sessions.txt', 4);

        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_bounce_rate_percent\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(50.0, (float)($matches[1] ?? -1));
    }

    public function testBounceRateDefaultsToZeroWhenNoSessions(): void
    {
        // Pas de fichiers → sessions = 1 (défaut dans le controller)
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        preg_match('/frontend_bounce_rate_percent\s+([\d.]+)/', $content, $matches);
        $this->assertEquals(0.0, (float)($matches[1] ?? -1));
    }

    public function testMetricsDefaultToZeroWithNoData(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();

        foreach ([
            'frontend_lcp_seconds',
            'frontend_fid_seconds',
            'frontend_cls_ratio',
            'frontend_page_load_seconds',
            'frontend_js_errors_total',
            'frontend_page_views_total',
        ] as $metric) {
            preg_match("/{$metric}\s+([\d.]+)/", $content, $matches);
            $this->assertEquals(0.0, (float)($matches[1] ?? -1), "{$metric} devrait valoir 0 par défaut");
        }
    }

    public function testMetricsEndpointRespondsQuickly(): void
    {
        $start = microtime(true);
        $this->client->request('GET', '/metrics/frontend');
        $this->assertResponseIsSuccessful();
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    public function testMultipleCallsAreStable(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('GET', '/metrics/frontend');
            $this->assertResponseIsSuccessful();
        }
    }
}