<?php

namespace App\Tests\Controller;

use App\Controller\AxiomController;
use App\Service\AxiomService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class AxiomControllerTest extends TestCase
{
    private AxiomService&MockObject $Axiom;
    private AxiomController $controller;

    protected function setUp(): void
    {
        $this->Axiom = $this->createMock(AxiomService::class);
        $this->controller = new AxiomController($this->Axiom);
        $this->controller->setContainer(new class implements PsrContainerInterface {
            public function get(string $id): mixed { return null; }
            public function has(string $id): bool  { return false; }
        });
    }

    public function testAxiomEndpointReturns200(): void
    {
        $response = $this->controller->testAxiom();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAxiomResponseIsJson(): void
    {
        $response = $this->controller->testAxiom();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testAxiomResponseHasExpectedKeys(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertArrayHasKey('success',        $data);
        $this->assertArrayHasKey('message',        $data);
        $this->assertArrayHasKey('log',            $data);
        $this->assertArrayHasKey('Axiom_url', $data);
    }

    public function testAxiomResponseSuccessIsTrue(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testAxiomMessageFieldIsCorrect(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertSame('Log envoyé à Axiom', $data['message']);
    }

    public function testAxiomLogFieldContainsExpectedPrefix(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertStringStartsWith('Test depuis open-hub - ', $data['log']);
    }

    public function testAxiomLogFieldContainsTimestamp(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            $data['log']
        );
    }

    public function testAxiomLogTimestampIsRecent(): void
    {
        $before = time();
        $data   = json_decode($this->controller->testAxiom()->getContent(), true);
        $after  = time();

        preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $data['log'], $matches);
        $this->assertNotEmpty($matches, 'Un timestamp doit être présent dans le log');

        $logTime = strtotime($matches[1]);
        $this->assertGreaterThanOrEqual($before, $logTime);
        $this->assertLessThanOrEqual($after, $logTime);
    }

    public function testAxiomUrlFieldIsNotEmpty(): void
    {
        $data = json_decode($this->controller->testAxiom()->getContent(), true);
        $this->assertNotEmpty($data['Axiom_url']);
    }

    public function testAxiomInfoIsCalledOnce(): void
    {
        $this->Axiom->expects($this->once())->method('info');
        $this->controller->testAxiom();
    }

    public function testAxiomCanBeCalledMultipleTimesWithoutError(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->controller->testAxiom();
            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), "Appel #{$i} doit retourner 200");
        }
    }

    public function testAxiomEachCallHasUniqueTimestamp(): void
    {
        $data1 = json_decode($this->controller->testAxiom()->getContent(), true);
        sleep(1);
        $data2 = json_decode($this->controller->testAxiom()->getContent(), true);

        $this->assertNotSame($data1['log'], $data2['log']);
    }

    public function testAxiomHandlesServiceException(): void
    {
        $this->Axiom->method('info')
            ->willThrowException(new \Exception('Simulated Axiom failure'));

        $response = $this->controller->testAxiom();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Simulated Axiom failure', $data['error']);
    }
}