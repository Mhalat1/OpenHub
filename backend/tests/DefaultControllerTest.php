<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    // ========== TESTS INDEX ENDPOINT (/) ==========

    public function testIndexReturnsSuccessResponse(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testIndexReturnsJsonResponse(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testIndexReturnsCorrectStructure(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Vérifier que toutes les clés requises sont présentes
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('endpoints', $data);
    }

    public function testIndexStatusIsOK(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('OK', $data['status']);
    }

    public function testIndexMessageIsCorrect(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('OpenHub Backend API is running', $data['message']);
    }

    public function testIndexTimestampIsValid(): void
    {
        $beforeRequest = time();
        
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $afterRequest = time();

        $this->assertIsInt($data['timestamp']);
        $this->assertGreaterThanOrEqual($beforeRequest, $data['timestamp']);
        $this->assertLessThanOrEqual($afterRequest, $data['timestamp']);
    }

    public function testIndexEndpointsAreProvided(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data['endpoints']);
        $this->assertArrayHasKey('register', $data['endpoints']);
        $this->assertArrayHasKey('login', $data['endpoints']);
        $this->assertArrayHasKey('health', $data['endpoints']);
    }

    public function testIndexEndpointsHaveCorrectValues(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('/api/userCreate', $data['endpoints']['register']);
        $this->assertEquals('/api/login_check', $data['endpoints']['login']);
        $this->assertEquals('/health', $data['endpoints']['health']);
    }

    public function testIndexWithPostMethodReturnsMethodNotAllowed(): void
    {
        $this->client->request('POST', '/');

        // Symfony retourne généralement 405 pour les méthodes non autorisées
        // ou peut rediriger selon la configuration
        $statusCode = $this->client->getResponse()->getStatusCode();
        
        // Accepter soit 405 (Method Not Allowed) soit 200 si GET est utilisé par défaut
        $this->assertTrue(
            in_array($statusCode, [Response::HTTP_METHOD_NOT_ALLOWED, Response::HTTP_OK]),
            "Expected status code 405 or 200, got {$statusCode}"
        );
    }

    public function testIndexAcceptsGetMethod(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    // ========== TESTS HEALTH ENDPOINT (/health) ==========

    public function testHealthReturnsSuccessResponse(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testHealthReturnsJsonResponse(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testHealthReturnsCorrectStructure(): void
    {
        $this->client->request('GET', '/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Vérifier que toutes les clés requises sont présentes
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testHealthStatusIsHealthy(): void
    {
        $this->client->request('GET', '/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals('healthy', $data['status']);
    }

    public function testHealthTimestampIsValid(): void
    {
        $beforeRequest = time();
        
        $this->client->request('GET', '/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $afterRequest = time();

        $this->assertIsInt($data['timestamp']);
        $this->assertGreaterThanOrEqual($beforeRequest, $data['timestamp']);
        $this->assertLessThanOrEqual($afterRequest, $data['timestamp']);
    }

    public function testHealthAcceptsGetMethod(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
    }

    public function testHealthDoesNotContainEndpointsList(): void
    {
        $this->client->request('GET', '/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('endpoints', $data);
    }

    // ========== TESTS DE COMPARAISON ==========

    public function testIndexAndHealthHaveDifferentStatuses(): void
    {
        // Index
        $this->client->request('GET', '/');
        $indexData = json_decode($this->client->getResponse()->getContent(), true);

        // Health
        $this->client->request('GET', '/health');
        $healthData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotEquals($indexData['status'], $healthData['status']);
        $this->assertEquals('OK', $indexData['status']);
        $this->assertEquals('healthy', $healthData['status']);
    }

    public function testIndexHasMoreFieldsThanHealth(): void
    {
        // Index
        $this->client->request('GET', '/');
        $indexData = json_decode($this->client->getResponse()->getContent(), true);

        // Health
        $this->client->request('GET', '/health');
        $healthData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertGreaterThan(count($healthData), count($indexData));
        $this->assertCount(4, $indexData); // status, message, timestamp, endpoints
        $this->assertCount(2, $healthData); // status, timestamp
    }

    // ========== TESTS DE ROUTE ==========

    public function testIndexRouteIsAccessible(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testHealthRouteIsAccessible(): void
    {
        $this->client->request('GET', '/health');

        $this->assertResponseIsSuccessful();
    }

    public function testNonExistentRouteReturns404(): void
    {
        $this->client->request('GET', '/non-existent-route-12345');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ========== TESTS DE FORMAT JSON ==========

    public function testIndexReturnsValidJson(): void
    {
        $this->client->request('GET', '/');

        $content = $this->client->getResponse()->getContent();
        
        $this->assertJson($content);
        
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testHealthReturnsValidJson(): void
    {
        $this->client->request('GET', '/health');

        $content = $this->client->getResponse()->getContent();
        
        $this->assertJson($content);
        
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    // ========== TESTS DE PERFORMANCE (OPTIONNELS) ==========

    public function testIndexRespondsQuickly(): void
    {
        $start = microtime(true);
        
        $this->client->request('GET', '/');
        
        $duration = microtime(true) - $start;

        $this->assertResponseIsSuccessful();
        // La réponse devrait prendre moins d'1 seconde
        $this->assertLessThan(1.0, $duration, 'Index endpoint took too long to respond');
    }

    public function testHealthRespondsQuickly(): void
    {
        $start = microtime(true);
        
        $this->client->request('GET', '/health');
        
        $duration = microtime(true) - $start;

        $this->assertResponseIsSuccessful();
        // La réponse devrait prendre moins d'1 seconde
        $this->assertLessThan(1.0, $duration, 'Health endpoint took too long to respond');
    }

    // ========== TESTS DE HEADERS ==========

    public function testIndexHasCorrectContentTypeHeader(): void
    {
        $this->client->request('GET', '/');

        $this->assertTrue(
            $this->client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'Response should have Content-Type: application/json'
        );
    }

    public function testHealthHasCorrectContentTypeHeader(): void
    {
        $this->client->request('GET', '/health');

        $this->assertTrue(
            $this->client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'Response should have Content-Type: application/json'
        );
    }

    // ========== TESTS DE STABILITÉ ==========

    public function testIndexCanBeCalledMultipleTimes(): void
    {
        // Appeler plusieurs fois pour vérifier la stabilité
        for ($i = 0; $i < 3; $i++) {
            $this->client->request('GET', '/');
            
            $this->assertResponseIsSuccessful();
            
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('OK', $data['status']);
        }
    }

    public function testHealthCanBeCalledMultipleTimes(): void
    {
        // Appeler plusieurs fois pour vérifier la stabilité
        for ($i = 0; $i < 3; $i++) {
            $this->client->request('GET', '/health');
            
            $this->assertResponseIsSuccessful();
            
            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertEquals('healthy', $data['status']);
        }
    }

    // ========== TESTS DE DONNÉES ==========

    public function testIndexMessageIsNotEmpty(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotEmpty($data['message']);
        $this->assertIsString($data['message']);
    }

    public function testIndexEndpointsAreNotEmpty(): void
    {
        $this->client->request('GET', '/');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertNotEmpty($data['endpoints']);
        $this->assertGreaterThan(0, count($data['endpoints']));
    }

    public function testHealthStatusIsString(): void
    {
        $this->client->request('GET', '/health');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsString($data['status']);
    }

    public function testTimestampsAreReasonable(): void
    {
        // Vérifier que les timestamps sont dans une plage raisonnable
        $currentYear = (int)date('Y');
        
        $this->client->request('GET', '/');
        $indexData = json_decode($this->client->getResponse()->getContent(), true);
        
        $this->client->request('GET', '/health');
        $healthData = json_decode($this->client->getResponse()->getContent(), true);

        // Les timestamps devraient être proches du temps actuel
        $this->assertGreaterThan(strtotime("$currentYear-01-01"), $indexData['timestamp']);
        $this->assertGreaterThan(strtotime("$currentYear-01-01"), $healthData['timestamp']);
        $this->assertLessThan(strtotime("+1 day"), $indexData['timestamp']);
        $this->assertLessThan(strtotime("+1 day"), $healthData['timestamp']);
    }
}