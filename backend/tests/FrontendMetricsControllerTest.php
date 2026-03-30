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
        // Nettoyer les fichiers avant chaque test
        $this->tmpDir = sys_get_temp_dir() . '/openhub_metrics';
        $this->cleanupMetricsFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanupMetricsFiles();
        parent::tearDown();
    }

    private function cleanupMetricsFiles(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        
        foreach ([
            'fm_lcp.txt', 'fm_fid.txt', 'fm_cls.txt', 'fm_plt.txt',
            'fm_js_errors.txt', 'fm_page_views.txt', 'fm_bounces.txt', 'fm_sessions.txt'
        ] as $file) {
            $path = $this->tmpDir . '/' . $file;
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function collect(array $data): void
    {
        $this->client->request('POST', '/metrics/frontend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($data));
    }

    private function getMetricValue(string $metricName): ?float
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();
        
        preg_match('/' . preg_quote($metricName, '/') . '\s+([\d.eE+-]+)/', $content, $matches);
        return isset($matches[1]) ? (float)$matches[1] : null;
    }

    // ========== TESTS COLLECT ==========

    public function testCollectEndpointExists(): void
    {
        $this->assertTrue(method_exists(FrontendMetricsController::class, 'collect'));
    }

    public function testCollectReturnsOkJson(): void
    {
        $this->collect(['lcp' => 1.2]);
        
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(['ok' => true], $content);
    }

    public function testCollectStoresLcp(): void
    {
        $this->collect(['lcp' => 2.45]);
        
        $this->assertEquals(2.45, $this->getMetricValue('frontend_lcp_seconds'));
    }

    public function testCollectStoresFid(): void
    {
        $this->collect(['fid' => 0.08]);
        
        $this->assertEquals(0.08, $this->getMetricValue('frontend_fid_seconds'));
    }

    public function testCollectStoresCls(): void
    {
        $this->collect(['cls' => 0.12]);
        
        $this->assertEquals(0.12, $this->getMetricValue('frontend_cls_ratio'));
    }

    public function testCollectStoresPageLoadTime(): void
    {
        $this->collect(['page_load_time' => 3.7]);
        
        $this->assertEquals(3.7, $this->getMetricValue('frontend_page_load_seconds'));
    }

    public function testCollectStoresJsErrors(): void
    {
        $this->collect(['js_errors' => 5]);
        
        $this->assertEquals(5, $this->getMetricValue('frontend_js_errors_total'));
    }

    public function testCollectIncrementsPageViews(): void
    {
        $this->collect(['page_views' => 3]);
        $this->collect(['page_views' => 2]);
        
        $this->assertEquals(5, $this->getMetricValue('frontend_page_views_total'));
    }

    public function testCollectIncrementsBounceAndSessions(): void
    {
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 1]);
        
        // 2 bounces, 2 sessions = 100% bounce rate
        $this->assertEquals(100.0, $this->getMetricValue('frontend_bounce_rate_percent'));
    }

    public function testCollectIgnoresMissingFields(): void
    {
        $this->collect(['lcp' => 1.5]);
        
        $this->assertEquals(1.5, $this->getMetricValue('frontend_lcp_seconds'));
        $this->assertEquals(0.0, $this->getMetricValue('frontend_fid_seconds'));
        $this->assertEquals(0.0, $this->getMetricValue('frontend_cls_ratio'));
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

        $this->assertEquals(1.8, $this->getMetricValue('frontend_lcp_seconds'));
        $this->assertEquals(0.05, $this->getMetricValue('frontend_fid_seconds'));
        $this->assertEquals(0.08, $this->getMetricValue('frontend_cls_ratio'));
        $this->assertEquals(2.1, $this->getMetricValue('frontend_page_load_seconds'));
        $this->assertEquals(2, $this->getMetricValue('frontend_js_errors_total'));
        $this->assertEquals(10, $this->getMetricValue('frontend_page_views_total'));
        $this->assertEquals(100.0, $this->getMetricValue('frontend_bounce_rate_percent'));
    }

    // ========== TESTS METRICS ENDPOINT ==========

    public function testMetricsEndpointIsAccessible(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $this->assertResponseIsSuccessful();
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testMetricsReturnsTextPlain(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $contentType = $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('text/plain', $contentType);
    }

    public function testMetricsContainsAllExpectedMetrics(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();
        
        $expectedMetrics = [
            'frontend_lcp_seconds',
            'frontend_fid_seconds',
            'frontend_cls_ratio',
            'frontend_page_load_seconds',
            'frontend_js_errors_total',
            'frontend_page_views_total',
            'frontend_bounce_rate_percent'
        ];
        
        foreach ($expectedMetrics as $metric) {
            $this->assertStringContainsString($metric, $content, "Metric {$metric} not found");
        }
    }

    public function testMetricsFollowPrometheusFormat(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();
        
        $this->assertStringContainsString('# HELP', $content);
        $this->assertStringContainsString('# TYPE', $content);
        
        // Vérifier qu'il y a au moins une métrique avec une valeur
        preg_match('/^[a-z_]+ [\d.eE+-]+$/m', $content, $matches);
        $this->assertNotEmpty($matches);
    }

    public function testMetricsReturnZeroWhenNoData(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();
        
        // Extraire toutes les métriques et vérifier qu'elles sont à 0
        preg_match_all('/^([a-z_]+)\s+([\d.eE+-]+)$/m', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $value = (float)$match[2];
            $this->assertEquals(0.0, $value, "{$match[1]} should be 0, got {$value}");
        }
    }

    public function testMetricsReflectStoredValues(): void
    {
        // Stocker des valeurs
        $this->collect(['lcp' => 3.14, 'fid' => 0.22, 'page_views' => 42, 'js_errors' => 7]);
        
        $this->assertEquals(3.14, $this->getMetricValue('frontend_lcp_seconds'));
        $this->assertEquals(0.22, $this->getMetricValue('frontend_fid_seconds'));
        $this->assertEquals(42, $this->getMetricValue('frontend_page_views_total'));
        $this->assertEquals(7, $this->getMetricValue('frontend_js_errors_total'));
    }

    public function testBounceRateWithNoSessions(): void
    {
        // Pas de sessions, sessions min = 1, bounce rate = 0%
        $this->assertEquals(0.0, $this->getMetricValue('frontend_bounce_rate_percent'));
    }

    public function testBounceRateWithOnlyBounces(): void
    {
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 1]);
        $this->collect(['bounce' => 1]);
        
        // 3 bounces, 3 sessions = 100%
        $this->assertEquals(100.0, $this->getMetricValue('frontend_bounce_rate_percent'));
    }

    public function testAllMetricsAreGaugeType(): void
    {
        $this->client->request('GET', '/metrics/frontend');
        $content = $this->client->getResponse()->getContent();
        
        $metrics = [
            'frontend_lcp_seconds',
            'frontend_fid_seconds',
            'frontend_cls_ratio',
            'frontend_page_load_seconds',
            'frontend_js_errors_total',
            'frontend_page_views_total',
            'frontend_bounce_rate_percent'
        ];
        
        foreach ($metrics as $metric) {
            $this->assertStringContainsString("# TYPE {$metric} gauge", $content, "{$metric} should be gauge");
        }
    }

    public function testMetricsEndpointRespondsQuickly(): void
    {
        $start = microtime(true);
        $this->client->request('GET', '/metrics/frontend');
        $duration = microtime(true) - $start;
        
        $this->assertResponseIsSuccessful();
        $this->assertLessThan(1.0, $duration, "Metrics endpoint took too long: {$duration}s");
    }

    public function testMultipleCallsAreStable(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('GET', '/metrics/frontend');
            $this->assertResponseIsSuccessful();
        }
    }

    public function testCollectEndpointIsPostOnly(): void
    {
        $this->client->request('GET', '/metrics/frontend/collect');
        $this->assertNotEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testMetricsEndpointIsGetOnly(): void
    {
        $this->client->request('POST', '/metrics/frontend');
        $this->assertNotEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    public function testCollectHandlesInvalidJson(): void
    {
        $this->client->request('POST', '/metrics/frontend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');
        
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }

    public function testCollectHandlesNonArrayData(): void
    {
        $this->client->request('POST', '/metrics/frontend/collect', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '"string"');
        
        $this->assertEquals(400, $this->client->getResponse()->getStatusCode());
    }
}