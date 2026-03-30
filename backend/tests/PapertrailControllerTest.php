<?php

namespace App\Tests\Controller;

use App\Controller\PapertrailController;
use App\Service\PapertrailService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class PapertrailControllerTest extends TestCase
{
    private PapertrailService&MockObject $papertrail;
    private PapertrailController $controller;

    protected function setUp(): void
    {
        $this->papertrail = $this->createMock(PapertrailService::class);
        $this->controller = new PapertrailController($this->papertrail);
        $this->controller->setContainer(new class implements PsrContainerInterface {
            public function get(string $id): mixed { return null; }
            public function has(string $id): bool  { return false; }
        });
    }

    public function testPapertrailEndpointReturns200(): void
    {
        $response = $this->controller->testPapertrail();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testPapertrailResponseIsJson(): void
    {
        $response = $this->controller->testPapertrail();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testPapertrailResponseHasExpectedKeys(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertArrayHasKey('success',        $data);
        $this->assertArrayHasKey('message',        $data);
        $this->assertArrayHasKey('log',            $data);
        $this->assertArrayHasKey('papertrail_url', $data);
    }

    public function testPapertrailResponseSuccessIsTrue(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testPapertrailMessageFieldIsCorrect(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertSame('Log envoyé à Papertrail', $data['message']);
    }

    public function testPapertrailLogFieldContainsExpectedPrefix(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertStringStartsWith('Test depuis open-hub - ', $data['log']);
    }

    public function testPapertrailLogFieldContainsTimestamp(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            $data['log']
        );
    }

    public function testPapertrailLogTimestampIsRecent(): void
    {
        $before = time();
        $data   = json_decode($this->controller->testPapertrail()->getContent(), true);
        $after  = time();

        preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $data['log'], $matches);
        $this->assertNotEmpty($matches, 'Un timestamp doit être présent dans le log');

        $logTime = strtotime($matches[1]);
        $this->assertGreaterThanOrEqual($before, $logTime);
        $this->assertLessThanOrEqual($after, $logTime);
    }

    public function testPapertrailUrlFieldIsNotEmpty(): void
    {
        $data = json_decode($this->controller->testPapertrail()->getContent(), true);
        $this->assertNotEmpty($data['papertrail_url']);
    }

    public function testPapertrailInfoIsCalledOnce(): void
    {
        $this->papertrail->expects($this->once())->method('info');
        $this->controller->testPapertrail();
    }

    public function testPapertrailCanBeCalledMultipleTimesWithoutError(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->controller->testPapertrail();
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), "Appel #{$i} doit retourner 200");
        }
    }

    public function testPapertrailEachCallHasUniqueTimestamp(): void
    {
        $data1 = json_decode($this->controller->testPapertrail()->getContent(), true);
        sleep(1);
        $data2 = json_decode($this->controller->testPapertrail()->getContent(), true);

        $this->assertNotSame($data1['log'], $data2['log']);
    }

    public function testPapertrailHandlesServiceException(): void
    {
        $this->papertrail->method('info')
            ->willThrowException(new \Exception('Simulated Papertrail failure'));

        $response = $this->controller->testPapertrail();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Simulated Papertrail failure', $data['error']);
    }
}