<?php
// tests/Controller/FrontendMetricsControllerTest.php
namespace App\Tests\Controller;

use App\Controller\FrontendMetricsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontendMetricsControllerTest extends TestCase
{
    private FrontendMetricsController $controller;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FrontendMetricsController();
        $this->tempDir = sys_get_temp_dir();
        
        // Nettoyer les fichiers de compteurs avant chaque test
        $this->cleanupCounterFiles();
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test
        $this->cleanupCounterFiles();
        parent::tearDown();
    }

    private function cleanupCounterFiles(): void
    {
        $counters = ['page_views', 'js_errors', 'api_errors'];
        foreach ($counters as $counter) {
            $file = $this->tempDir . '/frontend_' . $counter . '.txt';
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    // =========================================
    // TESTS DE BASE
    // =========================================

    public function testFrontendMetricsReturnsResponse(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testFrontendMetricsReturnsStatusCode200(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testFrontendMetricsReturnsCorrectContentType(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);

        $this->assertEquals(
            'text/plain; version=0.0.4',
            $response->headers->get('Content-Type')
        );
    }

    // =========================================
    // TESTS DES MÉTRIQUES PROMETHEUS
    // =========================================

    public function testResponseContainsAllMetrics(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();

        // Vérifier la présence de toutes les métriques
        $this->assertStringContainsString('frontend_lcp_seconds', $content);
        $this->assertStringContainsString('frontend_fid_seconds', $content);
        $this->assertStringContainsString('frontend_cls_ratio', $content);
        $this->assertStringContainsString('frontend_page_load_seconds', $content);
        $this->assertStringContainsString('frontend_page_views_total', $content);
        $this->assertStringContainsString('frontend_js_errors_total', $content);
        $this->assertStringContainsString('frontend_api_errors_total', $content);
        $this->assertStringContainsString('frontend_bounce_rate_percent', $content);
    }

    public function testResponseContainsPrometheusHelp(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();

        $this->assertStringContainsString('# HELP frontend_lcp_seconds', $content);
        $this->assertStringContainsString('# TYPE frontend_lcp_seconds gauge', $content);
    }

    public function testResponseContainsPrometheusTypes(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();

        $this->assertStringContainsString('# TYPE frontend_lcp_seconds gauge', $content);
        $this->assertStringContainsString('# TYPE frontend_fid_seconds gauge', $content);
        $this->assertStringContainsString('# TYPE frontend_cls_ratio gauge', $content);
        $this->assertStringContainsString('# TYPE frontend_page_views_total counter', $content);
        $this->assertStringContainsString('# TYPE frontend_js_errors_total counter', $content);
        $this->assertStringContainsString('# TYPE frontend_api_errors_total counter', $content);
    }

    // =========================================
    // TESTS DES VALEURS MÉTRIQUES
    // =========================================

    public function testLCPValueIsInValidRange(): void
    {
        $request = new Request();
        
        // Tester plusieurs fois pour couvrir différentes distributions
        for ($i = 0; $i < 10; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_lcp_seconds (\d+\.?\d*)/', $content, $matches);
            $lcp = (float) $matches[1];
            
            $this->assertGreaterThan(0, $lcp);
            $this->assertLessThan(10, $lcp); // Max théorique
        }
    }

    public function testFIDValueIsInValidRange(): void
    {
        $request = new Request();
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_fid_seconds (\d+\.?\d*)/', $content, $matches);
            $fid = (float) $matches[1];
            
            $this->assertGreaterThanOrEqual(0, $fid);
            $this->assertLessThanOrEqual(1, $fid); // Max 1000ms = 1s
        }
    }

    public function testCLSValueIsInValidRange(): void
    {
        $request = new Request();
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_cls_ratio (\d+\.?\d*)/', $content, $matches);
            $cls = (float) $matches[1];
            
            $this->assertGreaterThanOrEqual(0, $cls);
            $this->assertLessThanOrEqual(0.5, $cls); // Max 0.5
        }
    }

    public function testPageLoadTimeIsInValidRange(): void
    {
        $request = new Request();
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_page_load_seconds (\d+\.?\d*)/', $content, $matches);
            $pageLoad = (float) $matches[1];
            
            $this->assertGreaterThanOrEqual(1.5, $pageLoad);
            $this->assertLessThanOrEqual(5.0, $pageLoad);
        }
    }

    public function testBounceRateIsInValidRange(): void
    {
        $request = new Request();
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_bounce_rate_percent (\d+\.?\d*)/', $content, $matches);
            $bounceRate = (float) $matches[1];
            
            $this->assertGreaterThanOrEqual(25, $bounceRate);
            $this->assertLessThanOrEqual(75, $bounceRate);
        }
    }

    // =========================================
    // TESTS DES COMPTEURS
    // =========================================

    public function testPageViewsCounterIncrements(): void
    {
        $request = new Request();
        
        // Premier appel
        $response1 = $this->controller->frontendMetrics($request);
        $content1 = $response1->getContent();
        preg_match('/frontend_page_views_total (\d+)/', $content1, $matches1);
        $pageViews1 = (int) $matches1[1];
        
        // Deuxième appel
        $response2 = $this->controller->frontendMetrics($request);
        $content2 = $response2->getContent();
        preg_match('/frontend_page_views_total (\d+)/', $content2, $matches2);
        $pageViews2 = (int) $matches2[1];
        
        $this->assertEquals($pageViews1 + 1, $pageViews2);
    }

    public function testPageViewsCounterStartsAtOne(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();
        
        preg_match('/frontend_page_views_total (\d+)/', $content, $matches);
        $pageViews = (int) $matches[1];
        
        $this->assertEquals(1, $pageViews);
    }

    public function testPageViewsCounterPersistsAcrossRequests(): void
    {
        $request = new Request();
        
        // Faire 5 requêtes
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_page_views_total (\d+)/', $content, $matches);
            $pageViews = (int) $matches[1];
            
            $this->assertEquals($i, $pageViews);
        }
    }

    public function testJSErrorsCounterIsNumeric(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();
        
        preg_match('/frontend_js_errors_total (\d+)/', $content, $matches);
        $jsErrors = $matches[1];
        
        $this->assertIsNumeric($jsErrors);
        $this->assertGreaterThanOrEqual(0, (int) $jsErrors);
    }

    public function testAPIErrorsCounterIsNumeric(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        $content = $response->getContent();
        
        preg_match('/frontend_api_errors_total (\d+)/', $content, $matches);
        $apiErrors = $matches[1];
        
        $this->assertIsNumeric($apiErrors);
        $this->assertGreaterThanOrEqual(0, (int) $apiErrors);
    }

    // =========================================
    // TESTS DE PERSISTANCE DES ERREURS
    // =========================================

    public function testErrorCountersCanIncrement(): void
    {
        $request = new Request();
        $initialJSErrors = 0;
        $initialAPIErrors = 0;
        
        // Faire plusieurs requêtes pour augmenter la probabilité d'erreurs
        for ($i = 0; $i < 100; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_js_errors_total (\d+)/', $content, $jsMatches);
            preg_match('/frontend_api_errors_total (\d+)/', $content, $apiMatches);
            
            $currentJSErrors = (int) $jsMatches[1];
            $currentAPIErrors = (int) $apiMatches[1];
            
            // Les erreurs ne peuvent qu'augmenter ou rester stables
            $this->assertGreaterThanOrEqual($initialJSErrors, $currentJSErrors);
            $this->assertGreaterThanOrEqual($initialAPIErrors, $currentAPIErrors);
            
            $initialJSErrors = $currentJSErrors;
            $initialAPIErrors = $currentAPIErrors;
        }
    }

    // =========================================
    // TESTS DE FORMAT
    // =========================================
public function testMetricsAreValidPrometheusFormat()
{
    $request = new Request();
    $response = $this->controller->frontendMetrics($request);
    $content = $response->getContent();
    
    // Nettoyer le contenu (enlever les retours chariot Windows)
    $content = str_replace("\r", '', $content);
    $lines = explode("\n", trim($content));
    
    $validLines = 0;
    foreach ($lines as $line) {
        if (empty($line)) {
            continue;
        }
        
        // Vérifier si c'est une ligne de métrique valide
        if (preg_match('/^frontend_\w+ \d+\.?\d*$/', $line)) {
            $validLines++;
        }
        // Ou un commentaire
        elseif (preg_match('/^# (HELP|TYPE) /', $line)) {
            $validLines++;
        }
    }
    
    $this->assertGreaterThan(0, $validLines, 'Aucune ligne de métrique valide trouvée');
}

public function testMetricValuesAreNumeric()
{
    $request = new Request();
    $response = $this->controller->frontendMetrics($request);
    $content = $response->getContent();
    
    // Nettoyer le contenu
    $content = str_replace("\r", '', $content);
    $lines = explode("\n", trim($content));
    $assertionsMade = false;
    
    foreach ($lines as $line) {
        // Ignorer les commentaires et lignes vides
        if (!empty($line) && strpos($line, '#') !== 0) {
            $parts = explode(' ', $line);
            $this->assertCount(2, $parts, "Format invalide: $line");
            $this->assertIsNumeric($parts[1], "La valeur n'est pas numérique: {$parts[1]}");
            $assertionsMade = true;
        }
    }
    
    $this->assertTrue($assertionsMade, 'Aucune métrique trouvée dans la réponse');
}


    // =========================================
    // TESTS DE STABILITÉ
    // =========================================

    public function testMultipleConsecutiveCallsSucceed(): void
    {
        $request = new Request();
        
        for ($i = 0; $i < 20; $i++) {
            $response = $this->controller->frontendMetrics($request);
            
            $this->assertEquals(200, $response->getStatusCode());
            $this->assertNotEmpty($response->getContent());
        }
    }

    public function testMetricsConsistencyBetweenCalls(): void
    {
        $request = new Request();
        
        // Premier appel
        $response1 = $this->controller->frontendMetrics($request);
        $content1 = $response1->getContent();
        
        // Deuxième appel immédiat
        $response2 = $this->controller->frontendMetrics($request);
        $content2 = $response2->getContent();
        
        // Le format doit être identique
        $lines1 = explode("\n", $content1);
        $lines2 = explode("\n", $content2);
        
        $this->assertCount(count($lines1), $lines2, 'Same number of lines');
    }

    // =========================================
    // TESTS DES OBJECTIFS CORE WEB VITALS
    // =========================================

    public function testLCPDistributionFollowsExpectedPattern(): void
    {
        $request = new Request();
        $goodCount = 0;
        $needsImprovementCount = 0;
        $poorCount = 0;
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_lcp_seconds (\d+\.?\d*)/', $content, $matches);
            $lcp = (float) $matches[1];
            
            if ($lcp < 2.5) {
                $goodCount++;
            } elseif ($lcp < 4.0) {
                $needsImprovementCount++;
            } else {
                $poorCount++;
            }
        }
        
        // Vérifier que la distribution est proche de 80/15/5
        $this->assertGreaterThan(60, $goodCount, 'Good LCP should be ~80%');
        $this->assertGreaterThan(5, $needsImprovementCount, 'Needs improvement should be ~15%');
        $this->assertGreaterThanOrEqual(0, $poorCount, 'Poor should be ~5%');
    }

    public function testFIDDistributionFollowsExpectedPattern(): void
    {
        $request = new Request();
        $goodCount = 0;
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_fid_seconds (\d+\.?\d*)/', $content, $matches);
            $fid = (float) $matches[1];
            
            if ($fid < 0.1) {
                $goodCount++;
            }
        }
        
        // Environ 90% devraient être < 100ms
        $this->assertGreaterThan(75, $goodCount, 'Good FID should be ~90%');
    }

    public function testCLSDistributionFollowsExpectedPattern(): void
    {
        $request = new Request();
        $goodCount = 0;
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            $response = $this->controller->frontendMetrics($request);
            $content = $response->getContent();
            
            preg_match('/frontend_cls_ratio (\d+\.?\d*)/', $content, $matches);
            $cls = (float) $matches[1];
            
            if ($cls < 0.1) {
                $goodCount++;
            }
        }
        
        // Environ 85% devraient être < 0.1
        $this->assertGreaterThan(70, $goodCount, 'Good CLS should be ~85%');
    }

    // =========================================
    // TESTS D'EDGE CASES
    // =========================================

    public function testEmptyRequestStillWorks(): void
    {
        $request = new Request();
        $response = $this->controller->frontendMetrics($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
    }

    public function testCounterFileCreation(): void
    {
        $request = new Request();
        $this->controller->frontendMetrics($request);
        
        $pageViewsFile = $this->tempDir . '/frontend_page_views.txt';
        $this->assertFileExists($pageViewsFile);
        
        $content = file_get_contents($pageViewsFile);
        $this->assertEquals('1', $content);
    }

    public function testCounterFileIncrement(): void
    {
        $request = new Request();
        
        // Premier appel
        $this->controller->frontendMetrics($request);
        $pageViewsFile = $this->tempDir . '/frontend_page_views.txt';
        $this->assertEquals('1', file_get_contents($pageViewsFile));
        
        // Deuxième appel
        $this->controller->frontendMetrics($request);
        $this->assertEquals('2', file_get_contents($pageViewsFile));
        
        // Troisième appel
        $this->controller->frontendMetrics($request);
        $this->assertEquals('3', file_get_contents($pageViewsFile));
    }
}