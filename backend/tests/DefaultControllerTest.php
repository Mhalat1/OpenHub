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

    private function get(string $uri): array
    {
        $this->client->request('GET', $uri);
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    // INDEX (/)

    public function testIndexResponse(): void
    {
        $data = $this->get('/');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertEquals('OK', $data['status']);
        $this->assertEquals('open-hub Backend API is running', $data['message']);
        $this->assertIsInt($data['timestamp']);
        $this->assertIsArray($data['endpoints']);
        $this->assertCount(4, $data);
    }

    public function testIndexEndpoints(): void
    {
        $data = $this->get('/');
        $this->assertEquals('/api/register', $data['endpoints']['register']);
        $this->assertEquals('/api/login_check', $data['endpoints']['login']);
        $this->assertEquals('/health', $data['endpoints']['health']);
    }

    public function testIndexTimestampIsValid(): void
    {
        $before = time();
        $data = $this->get('/');
        $after = time();
        $this->assertGreaterThanOrEqual($before, $data['timestamp']);
        $this->assertLessThanOrEqual($after, $data['timestamp']);
    }

    public function testIndexPostMethodReturns405Or200(): void
    {
        $this->client->request('POST', '/');
        $this->assertTrue(in_array($this->client->getResponse()->getStatusCode(), [405, 200]));
    }

    // HEALTH (/health)

    public function testHealthResponse(): void
    {
        $data = $this->get('/health');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertEquals('healthy', $data['status']);
        $this->assertIsInt($data['timestamp']);
        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey('endpoints', $data);
    }

    public function testHealthTimestampIsValid(): void
    {
        $before = time();
        $data = $this->get('/health');
        $after = time();
        $this->assertGreaterThanOrEqual($before, $data['timestamp']);
        $this->assertLessThanOrEqual($after, $data['timestamp']);
    }

    // COMPARAISON & ROUTES

    public function testIndexVsHealth(): void
    {
        $index = $this->get('/');
        $health = $this->get('/health');
        $this->assertEquals('OK', $index['status']);
        $this->assertEquals('healthy', $health['status']);
        $this->assertGreaterThan(count($health), count($index));
    }

    public function testNonExistentRouteReturns404(): void
    {
        $this->client->request('GET', '/non-existent-route-12345');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // PERFORMANCE

    public function testIndexRespondsQuickly(): void
    {
        $start = microtime(true);
        $this->get('/');
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    public function testHealthRespondsQuickly(): void
    {
        $start = microtime(true);
        $this->get('/health');
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    // STABILITÉ

    public function testIndexCanBeCalledMultipleTimes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $data = $this->get('/');
            $this->assertResponseIsSuccessful();
            $this->assertEquals('OK', $data['status']);
        }
    }

    public function testHealthCanBeCalledMultipleTimes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $data = $this->get('/health');
            $this->assertResponseIsSuccessful();
            $this->assertEquals('healthy', $data['status']);
        }
    }

    // TIMESTAMPS

    public function testTimestampsAreReasonable(): void
    {
        $year = (int)date('Y');
        $min = strtotime("$year-01-01");
        $max = strtotime('+1 day');

        foreach (['/', '/health'] as $uri) {
            $data = $this->get($uri);
            $this->assertGreaterThan($min, $data['timestamp']);
            $this->assertLessThan($max, $data['timestamp']);
        }
    }
}