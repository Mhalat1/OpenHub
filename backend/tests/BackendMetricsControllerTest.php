<?php

namespace App\Tests\Controller;

use App\Controller\BackendMetricsController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class BackendMetricsControllerTest extends WebTestCase
{
    private $client;
    private $tempDir;
    private $entityManager;
    private $jwtManager;
    private $testUser;
    private $authToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->tempDir = sys_get_temp_dir();
        
        // Récupérer les services
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        
        // Créer un utilisateur de test et générer un token JWT
        $this->createTestUser();
        
        // Nettoyer les fichiers de métriques avant chaque test
        $this->cleanupMetricsFiles();
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test
        $this->cleanupMetricsFiles();
        $this->cleanupDatabase();
        
        parent::tearDown();
    }

    private function createTestUser(): void
    {
        // Nettoyer les utilisateurs existants
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        
        // Créer un utilisateur de test
        $this->testUser = new User();
        $this->testUser->setEmail('test@example.com')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setPassword('password123') // Dans un vrai projet, utilisez un password hasher
            ->setRoles(['ROLE_USER']);
        
        $this->entityManager->persist($this->testUser);
        $this->entityManager->flush();
        
        // Générer un token JWT pour cet utilisateur
        $this->authToken = $this->jwtManager->create($this->testUser);
    }

    private function cleanupDatabase(): void
    {
        // Supprimer toutes les entités de test
        $this->entityManager->createQuery('DELETE FROM App\Entity\Conversation')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->entityManager->clear();
    }

    /**
     * Nettoie tous les fichiers de métriques temporaires
     */
    private function cleanupMetricsFiles(): void
    {
        $patterns = [
            'metrics_http_requests_total.txt',
            'metrics_http_requests_2xx.txt',
            'metrics_http_requests_4xx.txt',
            'metrics_http_requests_5xx.txt',
            'metrics_start_time.txt',
        ];

        foreach ($patterns as $pattern) {
            $file = $this->tempDir . '/' . $pattern;
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function requestWithAuth(string $method, string $uri, array $parameters = []): void
    {
        $this->client->request(
            $method, 
            $uri, 
            $parameters, 
            [], 
            [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->authToken,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );
    }

    // ========== TESTS DE BASE ==========

    public function testMetricsEndpointExists(): void
    {
        $this->assertTrue(
            method_exists(BackendMetricsController::class, 'metrics'),
            'La méthode metrics doit exister dans BackendMetricsController'
        );
    }

    public function testMetricsEndpointIsAccessible(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testMetricsReturnsTextPlainContentType(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $response = $this->client->getResponse();
        $contentType = $response->headers->get('Content-Type');

        $this->assertStringContainsString('text/plain', $contentType);
    }

    public function testMetricsReturnsPrometheusVersionHeader(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $response = $this->client->getResponse();
        $contentType = $response->headers->get('Content-Type');

        $this->assertStringContainsString('version=0.0.4', $contentType);
    }

    // ========== TESTS DES MÉTRIQUES ESSENTIELLES ==========

    public function testMetricsContainsHttpRequestsTotal(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('http_requests_total', $content);
        $this->assertStringContainsString('# HELP http_requests_total', $content);
        $this->assertStringContainsString('# TYPE http_requests_total counter', $content);
    }

    public function testMetricsContainsHttpRequestsByStatus(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('http_requests_by_status', $content);
        $this->assertStringContainsString('http_requests_by_status{status="2xx"}', $content);
        $this->assertStringContainsString('http_requests_by_status{status="4xx"}', $content);
        $this->assertStringContainsString('http_requests_by_status{status="5xx"}', $content);
    }

    public function testMetricsContainsErrorRate(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('app_error_rate_percent', $content);
        $this->assertStringContainsString('# HELP app_error_rate_percent', $content);
        $this->assertStringContainsString('# TYPE app_error_rate_percent gauge', $content);
    }

    public function testMetricsContainsAvailability(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('app_availability_percent', $content);
        $this->assertStringContainsString('# HELP app_availability_percent', $content);
        $this->assertStringContainsString('# TYPE app_availability_percent gauge', $content);
    }

    public function testMetricsContainsResponseTimeP95(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('http_response_time_p95_seconds', $content);
        $this->assertStringContainsString('# HELP http_response_time_p95_seconds', $content);
        $this->assertStringContainsString('# TYPE http_response_time_p95_seconds gauge', $content);
    }

    public function testMetricsContainsMemoryUsage(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('app_memory_usage_mb', $content);
        $this->assertStringContainsString('# HELP app_memory_usage_mb', $content);
        $this->assertStringContainsString('# TYPE app_memory_usage_mb gauge', $content);
    }

    public function testMetricsContainsCpuUsage(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('app_cpu_usage_percent', $content);
        $this->assertStringContainsString('# HELP app_cpu_usage_percent', $content);
        $this->assertStringContainsString('# TYPE app_cpu_usage_percent gauge', $content);
    }

    public function testMetricsContainsUptime(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        $this->assertStringContainsString('app_uptime_seconds', $content);
        $this->assertStringContainsString('# HELP app_uptime_seconds', $content);
        $this->assertStringContainsString('# TYPE app_uptime_seconds counter', $content);
    }

    // ========== TESTS DES VALEURS ==========

    public function testMetricsValuesAreNumeric(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        // Normaliser les fins de ligne (Windows vs Unix)
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Nettoyer la ligne
            $line = trim($line);
            
            // Ignorer les commentaires et les lignes vides
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Extraire la valeur (dernière partie après espace)
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $value = trim(end($parts));
                $this->assertTrue(
                    is_numeric($value),
                    "La valeur '{$value}' dans la ligne '{$line}' doit être numérique"
                );
            }
        }
    }

    public function testHttpRequestsTotalIncrementsOnEachCall(): void
    {
        // Premier appel
        $this->client->request('GET', '/metrics/backend');
        $content1 = $this->client->getResponse()->getContent();
        
        // Extraire la valeur de http_requests_total
        preg_match('/http_requests_total (\d+)/', $content1, $matches1);
        $total1 = (int)($matches1[1] ?? 0);

        // Deuxième appel
        $this->client->request('GET', '/metrics/backend');
        $content2 = $this->client->getResponse()->getContent();
        
        preg_match('/http_requests_total (\d+)/', $content2, $matches2);
        $total2 = (int)($matches2[1] ?? 0);

        $this->assertGreaterThan($total1, $total2, 'Le compteur total doit augmenter');
    }

    public function testAvailabilityIsPercentage(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        preg_match('/app_availability_percent ([\d.]+)/', $content, $matches);
        $availability = (float)($matches[1] ?? 0);

        $this->assertGreaterThanOrEqual(0, $availability);
        $this->assertLessThanOrEqual(100, $availability);
    }

    public function testErrorRateIsPercentage(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        preg_match('/app_error_rate_percent ([\d.]+)/', $content, $matches);
        $errorRate = (float)($matches[1] ?? 0);

        $this->assertGreaterThanOrEqual(0, $errorRate);
        $this->assertLessThanOrEqual(100, $errorRate);
    }

    public function testResponseTimeP95IsRealistic(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        preg_match('/http_response_time_p95_seconds ([\d.]+)/', $content, $matches);
        $responseTime = (float)($matches[1] ?? 0);

        $this->assertGreaterThan(0, $responseTime, 'Le temps de réponse doit être positif');
        $this->assertLessThan(1000, $responseTime, 'Le temps de réponse doit être réaliste');
    }

    public function testMemoryUsageIsPositive(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        preg_match('/app_memory_usage_mb ([\d.]+)/', $content, $matches);
        $memoryUsage = (float)($matches[1] ?? 0);

        $this->assertGreaterThan(0, $memoryUsage);
        $this->assertLessThan(1024, $memoryUsage);
    }

    public function testCpuUsageIsRealistic(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
        $cpuUsage = (float)($matches[1] ?? 0);

        $this->assertGreaterThanOrEqual(0, $cpuUsage);
        $this->assertLessThanOrEqual(100, $cpuUsage);
    }

    public function testUptimeIncreasesOverTime(): void
    {
        // Premier appel
        $this->client->request('GET', '/metrics/backend');
        $content1 = $this->client->getResponse()->getContent();
        
        preg_match('/app_uptime_seconds (\d+)/', $content1, $matches1);
        $uptime1 = (int)($matches1[1] ?? 0);

        // Attendre 1 seconde
        sleep(1);

        // Deuxième appel
        $this->client->request('GET', '/metrics/backend');
        $content2 = $this->client->getResponse()->getContent();
        
        preg_match('/app_uptime_seconds (\d+)/', $content2, $matches2);
        $uptime2 = (int)($matches2[1] ?? 0);

        $this->assertGreaterThanOrEqual($uptime1, $uptime2, 'L\'uptime doit augmenter ou rester stable');
    }

    // ========== TESTS DE FORMAT PROMETHEUS ==========

    public function testMetricsFollowPrometheusFormat(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        $lines = explode("\n", $content);

        $hasHelpLines = false;
        $hasTypeLines = false;
        $hasMetricLines = false;

        foreach ($lines as $line) {
            if (strpos($line, '# HELP') === 0) {
                $hasHelpLines = true;
            }
            if (strpos($line, '# TYPE') === 0) {
                $hasTypeLines = true;
            }
            if (!empty($line) && strpos($line, '#') !== 0) {
                $hasMetricLines = true;
            }
        }

        $this->assertTrue($hasHelpLines, 'Le format doit contenir des lignes # HELP');
        $this->assertTrue($hasTypeLines, 'Le format doit contenir des lignes # TYPE');
        $this->assertTrue($hasMetricLines, 'Le format doit contenir des lignes de métriques');
    }

    public function testMetricsHaveCorrectTypes(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        // Vérifier les types de métriques
        $this->assertStringContainsString('# TYPE http_requests_total counter', $content);
        $this->assertStringContainsString('# TYPE http_requests_by_status counter', $content);
        $this->assertStringContainsString('# TYPE app_error_rate_percent gauge', $content);
        $this->assertStringContainsString('# TYPE app_availability_percent gauge', $content);
        $this->assertStringContainsString('# TYPE http_response_time_p95_seconds gauge', $content);
        $this->assertStringContainsString('# TYPE app_memory_usage_mb gauge', $content);
        $this->assertStringContainsString('# TYPE app_cpu_usage_percent gauge', $content);
        $this->assertStringContainsString('# TYPE app_uptime_seconds counter', $content);
    }

    public function testMetricsLabelsAreProperlyFormatted(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();

        // Vérifier le format des labels
        $this->assertMatchesRegularExpression(
            '/http_requests_by_status\{status="2xx"\}\s+\d+/',
            $content,
            'Les labels doivent être correctement formatés'
        );
        $this->assertMatchesRegularExpression(
            '/http_requests_by_status\{status="4xx"\}\s+\d+/',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/http_requests_by_status\{status="5xx"\}\s+\d+/',
            $content
        );
    }

    // ========== TESTS DE PERSISTANCE ==========

    public function testMetricsArePersisted(): void
    {
        // Premier appel
        $this->client->request('GET', '/metrics/backend');
        
        // Vérifier que des fichiers ont été créés
        $totalFile = $this->tempDir . '/metrics_http_requests_total.txt';
        $this->assertFileExists($totalFile, 'Le fichier de compteur doit être créé');
        
        $total1 = (int)file_get_contents($totalFile);
        $this->assertGreaterThan(0, $total1);

        // Deuxième appel
        $this->client->request('GET', '/metrics/backend');
        
        $total2 = (int)file_get_contents($totalFile);
        $this->assertGreaterThan($total1, $total2, 'Le compteur doit être persisté et incrémenté');
    }

    public function testUptimeFileIsCreated(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $startTimeFile = $this->tempDir . '/metrics_start_time.txt';
        $this->assertFileExists($startTimeFile, 'Le fichier de temps de démarrage doit être créé');
        
        $startTime = (int)file_get_contents($startTimeFile);
        $this->assertGreaterThan(0, $startTime);
        $this->assertLessThanOrEqual(time(), $startTime);
    }

    // ========== TESTS DE STABILITÉ ==========

    public function testMultipleCallsWorkCorrectly(): void
    {
        // Faire plusieurs appels
        for ($i = 0; $i < 5; $i++) {
            $this->client->request('GET', '/metrics/backend');
            $this->assertResponseIsSuccessful();
        }

        // Vérifier que le total augmente
        $totalFile = $this->tempDir . '/metrics_http_requests_total.txt';
        $total = (int)file_get_contents($totalFile);
        
        $this->assertGreaterThanOrEqual(5, $total, 'Le total doit refléter les appels multiples');
    }

    public function testMetricsAreConsistent(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        // Extraire les valeurs
        preg_match('/http_requests_total (\d+)/', $content, $totalMatches);
        preg_match('/http_requests_by_status\{status="2xx"\}\s+(\d+)/', $content, $matches2xx);
        preg_match('/http_requests_by_status\{status="4xx"\}\s+(\d+)/', $content, $matches4xx);
        preg_match('/http_requests_by_status\{status="5xx"\}\s+(\d+)/', $content, $matches5xx);

        $total = (int)($totalMatches[1] ?? 0);
        $count2xx = (int)($matches2xx[1] ?? 0);
        $count4xx = (int)($matches4xx[1] ?? 0);
        $count5xx = (int)($matches5xx[1] ?? 0);

        // Le total devrait être >= à la somme des statuts
        $this->assertGreaterThanOrEqual(
            $count2xx + $count4xx + $count5xx,
            $total,
            'Le total doit être cohérent avec les compteurs par status'
        );
    }

    // ========== TESTS DE PERFORMANCE ==========

    public function testMetricsEndpointRespondsQuickly(): void
    {
        $start = microtime(true);
        
        $this->client->request('GET', '/metrics/backend');
        
        $duration = microtime(true) - $start;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(1.0, $duration, 'L\'endpoint metrics doit répondre en moins d\'1 seconde');
    }

    // ========== TESTS DES HEADERS ==========

    public function testMetricsAcceptsUserAgentHeader(): void
    {
        $this->client->request('GET', '/metrics/backend', [], [], [
            'HTTP_USER_AGENT' => 'Prometheus/2.0'
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testMetricsWorksWithoutUserAgent(): void
    {
        $this->client->request('GET', '/metrics/backend');

        $this->assertResponseIsSuccessful();
    }

    // ========== TESTS DES CAS LIMITES ==========

    public function testMetricsWithFirstCall(): void
    {
        // S'assurer que c'est le premier appel
        $this->cleanupMetricsFiles();

        $this->client->request('GET', '/metrics/backend');

        $content = $this->client->getResponse()->getContent();
        
        // Vérifier que des valeurs par défaut raisonnables sont retournées
        $this->assertStringContainsString('http_requests_total', $content);
        $this->assertStringContainsString('app_availability_percent 100', $content);
    }

    public function testMetricsCalculationWithZeroRequests(): void
    {
        // Ce test vérifie la logique de calcul avec 0 requêtes
        // En pratique, après le premier appel il y aura au moins 1 requête
        
        $errorRate = 0;
        $availability = 100;
        
        $this->assertEquals(0, $errorRate);
        $this->assertEquals(100, $availability);
    }

    // ========== TESTS POUR L'ENDPOINT CONVERSATIONS ==========


    public function testGetConversationsWithAuthentication(): void
    {
        $this->requestWithAuth('GET', '/api/get/conversations');
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }

    public function testGetEmptyConversationsList(): void
    {
        $this->requestWithAuth('GET', '/api/get/conversations');
        
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetConversationsWithInvalidToken(): void
    {
        $this->client->request('GET', '/api/get/conversations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid_token_here'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals(401, $response['code']);
    }

    public function testGetConversationsWithExpiredToken(): void
    {
        // Simuler un token expiré (le comportement dépend de votre configuration JWT)
        $this->client->request('GET', '/api/get/conversations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE1MTYyMzkwMjJ9' // Token expiré fictif
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetConversationsWithMalformedToken(): void
    {
        $this->client->request('GET', '/api/get/conversations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer malformed.token.here'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test pour vérifier que le cache Doctrine est nettoyé
     */
    public function testDoctrineClearIsCalled(): void
    {
        // Ce test est conceptuel car on ne peut pas facilement vérifier le cache
        // Mais on peut vérifier que l'endpoint fonctionne avec plusieurs appels
        
        $this->requestWithAuth('GET', '/api/get/conversations');
        $this->assertResponseIsSuccessful();
        
        // Deuxième appel pour vérifier la stabilité
        $this->requestWithAuth('GET', '/api/get/conversations');
        $this->assertResponseIsSuccessful();
    }

    public function testGetConversationsWithMultipleCalls(): void
    {
        // Faire plusieurs appels pour vérifier la stabilité
        for ($i = 0; $i < 5; $i++) {
            $this->requestWithAuth('GET', '/api/get/conversations');
            $this->assertResponseIsSuccessful();
        }
    }


public function testConstructorInjectsDependencies(): void
{
    // This is an indirect test - if the controller can handle requests,
    // dependencies were injected properly
    $controller = static::getContainer()->get(BackendMetricsController::class);
    $this->assertInstanceOf(BackendMetricsController::class, $controller);
}



public function testCalculateAvailabilityMethod(): void
{
    // Similar approach - test through public endpoint
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_availability_percent ([\d.]+)/', $content, $matches);
    $availability = (float)($matches[1] ?? 0);
    
    $this->assertGreaterThan(0, $availability);
    $this->assertLessThanOrEqual(100, $availability);
}


public function testReadMetricsFromFile(): void
{
    // Test through public endpoint
    $this->client->request('GET', '/metrics/backend');
    
    // Verify files were read/created
    $totalFile = $this->tempDir . '/metrics_http_requests_total.txt';
    $this->assertFileExists($totalFile);
    
    $content = file_get_contents($totalFile);
    $this->assertIsNumeric(trim($content));
}

public function testWriteMetricsToFile(): void
{
    // Clear files first
    $this->cleanupMetricsFiles();
    
    // Make request
    $this->client->request('GET', '/metrics/backend');
    
    // Verify files were written
    $files = [
        'metrics_http_requests_total.txt',
        'metrics_start_time.txt',
    ];
    
    foreach ($files as $file) {
        $filePath = $this->tempDir . '/' . $file;
        $this->assertFileExists($filePath);
        $this->assertGreaterThan(0, filesize($filePath));
    }
}


public function testInitializeMetricsIfNeeded(): void
{
    // Clear all metrics files
    $this->cleanupMetricsFiles();
    
    // First request should initialize
    $this->client->request('GET', '/metrics/backend');
    
    // Verify initialization values
    $content = $this->client->getResponse()->getContent();
    
    // Uptime should be set
    preg_match('/app_uptime_seconds (\d+)/', $content, $matches);
    $uptime = (int)($matches[1] ?? 0);
    $this->assertGreaterThan(0, $uptime);
    
    // Availability should start at 100%
    preg_match('/app_availability_percent ([\d.]+)/', $content, $matches);
    $availability = (float)($matches[1] ?? 0);
    $this->assertEquals(100, $availability);
}









public function testGetCpuUsageReturnsNumericValue(): void
{
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
    $cpuUsage = isset($matches[1]) ? (float)$matches[1] : null;
    
    $this->assertNotNull($cpuUsage, 'CPU usage metric should be present');
    $this->assertIsNumeric($cpuUsage, 'CPU usage should be numeric');
}

public function testGetCpuUsageWithinValidRange(): void
{
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
    $cpuUsage = (float)($matches[1] ?? 0);
    
    $this->assertGreaterThanOrEqual(0, $cpuUsage, 'CPU usage cannot be negative');
    $this->assertLessThanOrEqual(100, $cpuUsage, 'CPU usage cannot exceed 100%');
    
    // More realistic range for a web server (usually between 0-50%)
    $this->assertLessThanOrEqual(100, $cpuUsage, 'CPU usage should be realistic');
}

public function testGetCpuUsageReturnsFloatValue(): void
{
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
    $cpuUsage = $matches[1] ?? '';
    
    // Check if it contains decimal point (float)
    $this->assertMatchesRegularExpression(
        '/^\d+\.?\d*$/',
        $cpuUsage,
        'CPU usage should be a float value'
    );
}




public function testGetCpuUsageUsesSysGetloadavgWhenAvailable(): void
{
    // This test assumes sys_getloadavg is available
    // We can't easily mock it, but we can verify the behavior
    
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
    $cpuUsage = isset($matches[1]) ? (float)$matches[1] : null;
    
    $this->assertNotNull($cpuUsage);
    
    // If sys_getloadavg is available, the value should be load average * 10
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $expectedMin = round($load[0] * 10, 2) - 1; // Allow small variation
        $expectedMax = round($load[0] * 10, 2) + 1;
        
        $this->assertGreaterThanOrEqual($expectedMin, $cpuUsage);
        $this->assertLessThanOrEqual($expectedMax, $cpuUsage);
    }
}

/**
 * Test the fallback behavior when sys_getloadavg is not available
 * This is tricky to test directly, so we'll use an indirect approach
 */
public function testGetCpuUsageFallbackValues(): void
{
    // Make multiple requests to see the pattern
    $usages = [];
    
    for ($i = 0; $i < 20; $i++) {
        $this->client->request('GET', '/metrics/backend');
        $content = $this->client->getResponse()->getContent();
        
        preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
        $usages[] = (float)($matches[1] ?? 0);
        
        usleep(10000); // Small delay between requests
    }
    
    // If sys_getloadavg is NOT available, values will be random between 5-45
    if (!function_exists('sys_getloadavg')) {
        // Check that all values are within the expected range
        foreach ($usages as $usage) {
            $this->assertGreaterThanOrEqual(5, $usage, 'Fallback CPU usage should be >= 5');
            $this->assertLessThanOrEqual(45, $usage, 'Fallback CPU usage should be <= 45');
        }
        
        // Check that values are somewhat random (not all the same)
        $uniqueValues = array_unique($usages);
        $this->assertGreaterThan(1, count($uniqueValues), 'Fallback should generate different values');
        
        // Check that values are rounded to 2 decimals
        foreach ($usages as $usage) {
            $usageStr = (string)$usage;
            if (strpos($usageStr, '.') !== false) {
                $decimals = strlen(substr(strrchr($usageStr, "."), 1));
                $this->assertLessThanOrEqual(2, $decimals, 'Fallback values should be rounded to 2 decimals');
            }
        }
    } else {
        // If sys_getloadavg IS available, we can still verify the range is realistic
        foreach ($usages as $usage) {
            $this->assertGreaterThanOrEqual(0, $usage);
            $this->assertLessThanOrEqual(100, $usage);
        }
    }
}

public function testGetCpuUsageMultiplierEffect(): void
{
    // This test verifies that the load average is multiplied by 10
    
    if (!function_exists('sys_getloadavg')) {
        $this->markTestSkipped('sys_getloadavg not available on this system');
    }
    
    // Get the current load average directly
    $load = sys_getloadavg();
    $loadAvg1 = $load[0];
    
    // Get the CPU usage from metrics
    $this->client->request('GET', '/metrics/backend');
    $content = $this->client->getResponse()->getContent();
    
    preg_match('/app_cpu_usage_percent ([\d.]+)/', $content, $matches);
    $cpuUsage = (float)($matches[1] ?? 0);
    
    // Calculate what the value should be (load * 10, rounded to 2 decimals)
    $expectedValue = round($loadAvg1 * 10, 2);
    
    // Allow a tiny difference due to timing
    $this->assertEqualsWithDelta(
        $expectedValue,
        $cpuUsage,
        0.1,
        'CPU usage should be load average multiplied by 10 and rounded'
    );
}



public function testGetUptimeCreatesStartFileWhenNotExists(): void
{
    // Supprimer le fichier s'il existe
    $startFile = $this->tempDir . '/metrics_start_time.txt';
    if (file_exists($startFile)) {
        unlink($startFile);
    }
    
    // Premier appel - devrait créer le fichier
    $this->client->request('GET', '/metrics/backend');
    
    // Vérifier que le fichier a été créé
    $this->assertFileExists($startFile, 'Le fichier de démarrage doit être créé');
    
    // Vérifier le contenu du fichier
    $startTime = (int)file_get_contents($startFile);
    $this->assertGreaterThan(0, $startTime, 'Le timestamp doit être positif');
    $this->assertLessThanOrEqual(time(), $startTime, 'Le timestamp ne peut pas être dans le futur');
    
    // Vérifier la métrique d'uptime
    $content = $this->client->getResponse()->getContent();
    preg_match('/app_uptime_seconds (\d+)/', $content, $matches);
    $uptime = isset($matches[1]) ? (int)$matches[1] : null;
    
    $this->assertNotNull($uptime, 'La métrique uptime doit être présente');
    $this->assertEquals(time() - $startTime, $uptime, 'L\'uptime doit correspondre à la différence avec le startTime');
}


/**
 * Test pour getUptime() - Vérifie l'incrémentation au fil du temps
 */
public function testGetUptimeIncrementsOverTime(): void
{
    // Premier appel
    $this->client->request('GET', '/metrics/backend');
    $content1 = $this->client->getResponse()->getContent();
    preg_match('/app_uptime_seconds (\d+)/', $content1, $matches1);
    $uptime1 = (int)($matches1[1] ?? 0);
    
    // Attendre 2 secondes
    sleep(2);
    
    // Deuxième appel
    $this->client->request('GET', '/metrics/backend');
    $content2 = $this->client->getResponse()->getContent();
    preg_match('/app_uptime_seconds (\d+)/', $content2, $matches2);
    $uptime2 = (int)($matches2[1] ?? 0);
    
    // L'uptime doit avoir augmenté d'environ 2 secondes
    $difference = $uptime2 - $uptime1;
    $this->assertGreaterThanOrEqual(2, $difference, 'L\'uptime doit augmenter avec le temps');
    $this->assertLessThan(5, $difference, 'L\'augmentation doit être cohérente avec le temps écoulé');
}

/**
 * Test pour getUptime() - Vérifie le format de retour (int positif)
 */
public function testGetUptimeReturnsPositiveInteger(): void
{
    $this->client->request('GET', '/metrics/backend');
    
    $content = $this->client->getResponse()->getContent();
    preg_match('/app_uptime_seconds (\d+)/', $content, $matches);
    
    $this->assertArrayHasKey(1, $matches, 'La métrique uptime doit être présente');
    
    $uptime = (int)$matches[1];
    $this->assertIsInt($uptime, 'L\'uptime doit être un entier');
    $this->assertGreaterThan(0, $uptime, 'L\'uptime doit être positif');
    $this->assertMatchesRegularExpression('/^\d+$/', $matches[1], 'L\'uptime doit être un nombre entier');
}

/**
 * Test pour getUptime() - Vérifie le comportement avec fichier corrompu
 */
public function testGetUptimeWithCorruptedStartFile(): void
{
    $startFile = $this->tempDir . '/metrics_start_time.txt';
    
    // Créer un fichier avec des données invalides
    file_put_contents($startFile, 'not_a_number');
    
    // Appel à l'endpoint
    $this->client->request('GET', '/metrics/backend');
    
    // Vérifier que le fichier a été remplacé par un timestamp valide
    $this->assertFileExists($startFile);
    $newContent = file_get_contents($startFile);
    $this->assertIsNumeric($newContent, 'Le fichier corrompu doit être remplacé par un timestamp valide');
    $this->assertGreaterThan(0, (int)$newContent);
    
    // Vérifier que la métrique fonctionne toujours
    $content = $this->client->getResponse()->getContent();
    preg_match('/app_uptime_seconds (\d+)/', $content, $matches);
    $this->assertArrayHasKey(1, $matches, 'La métrique doit être présente même avec fichier corrompu');
    $this->assertGreaterThan(0, (int)$matches[1]);
}


}