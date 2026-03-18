<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Service\PapertrailService;

/**
 * Tests d'intégration réels pour PapertrailController.
 * Vrai kernel Symfony, vrai PapertrailService — aucun mock.
 */
class PapertrailControllerTest extends WebTestCase
{
    // =========================================================================
    // GET /api/test/papertrail
    // =========================================================================

    public function testPapertrailEndpointReturns200(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testPapertrailResponseIsJson(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testPapertrailResponseHasExpectedKeys(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success',        $data);
        $this->assertArrayHasKey('message',        $data);
        $this->assertArrayHasKey('log',            $data);
        $this->assertArrayHasKey('papertrail_url', $data);
    }

    public function testPapertrailResponseSuccessIsTrue(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($data['success'], 'Le champ success doit être true si Papertrail est joignable');
    }

    public function testPapertrailMessageFieldIsCorrect(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Log envoyé à Papertrail', $data['message']);
    }

    public function testPapertrailLogFieldContainsExpectedPrefix(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Le champ 'log' doit commencer par "Test depuis open-hub - "
        $this->assertStringStartsWith(
            'Test depuis open-hub - ',
            $data['log'],
            'Le champ log doit contenir le message envoyé à Papertrail'
        );
    }

    public function testPapertrailLogFieldContainsTimestamp(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Le timestamp doit être au format Y-m-d H:i:s
        $this->assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
            $data['log'],
            'Le champ log doit contenir un timestamp au format Y-m-d H:i:s'
        );
    }

    public function testPapertrailLogTimestampIsRecent(): void
    {
        $before = time();

        $client = static::createClient();
        $client->request('GET', '/api/test/papertrail');

        $after = time();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Extraire le timestamp du champ log "Test depuis open-hub - YYYY-MM-DD HH:MM:SS"
        preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $data['log'], $matches);
        $this->assertNotEmpty($matches, 'Un timestamp doit être présent dans le log');

        $logTime = strtotime($matches[1]);
        $this->assertGreaterThanOrEqual($before, $logTime, 'Le timestamp doit être >= au début du test');
        $this->assertLessThanOrEqual($after,  $logTime, 'Le timestamp doit être <= à la fin du test');
    }

    public function testPapertrailUrlFieldIsNotEmpty(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty(
            $data['papertrail_url'],
            'Le champ papertrail_url ne doit pas être vide'
        );
    }

    public function testPapertrailUrlFieldIsDefinedInEnv(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Si la variable d'env est définie, l'URL ne doit pas être le fallback
        $this->assertNotSame(
            'non défini',
            $data['papertrail_url'],
            'PAPERTRAIL_URL doit être défini dans .env.test'
        );
    }

    public function testPapertrailDoesNotAcceptPostMethod(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/test/papertrail');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testPapertrailDoesNotAcceptPutMethod(): void
    {
        $client = static::createClient();

        $client->request('PUT', '/api/test/papertrail');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testPapertrailDoesNotAcceptDeleteMethod(): void
    {
        $client = static::createClient();

        $client->request('DELETE', '/api/test/papertrail');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testPapertrailCanBeCalledMultipleTimesWithoutError(): void
    {
        $client = static::createClient();

        // Appels successifs — le service ne doit pas planter sur des appels répétés
        for ($i = 0; $i < 3; $i++) {
            $client->request('GET', '/api/test/papertrail');
            $this->assertResponseStatusCodeSame(
                Response::HTTP_OK,
                "L'appel #{$i} doit retourner 200"
            );
        }
    }

    public function testPapertrailEachCallHasUniqueTimestamp(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/test/papertrail');
        $data1 = json_decode($client->getResponse()->getContent(), true);

        sleep(1); // Attendre 1 seconde pour garantir un timestamp différent

        $client->request('GET', '/api/test/papertrail');
        $data2 = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotSame(
            $data1['log'],
            $data2['log'],
            'Deux appels espacés d\'1s doivent produire des logs différents'
        );
    }










    public function testPapertrailHandlesServiceException(): void
    {
        $client = static::createClient();

        // Replace the real service with a mock that throws an exception
        $mockService = $this->createMock(PapertrailService::class);
        $mockService->method('info')
            ->willThrowException(new \Exception('Simulated Papertrail failure'));

        // Override the service in the container
        static::getContainer()->set(PapertrailService::class, $mockService);

        $client->request('GET', '/api/test/papertrail');

        // Assert error response
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('error', $data);
        $this->assertFalse($data['success']);
        $this->assertEquals('Simulated Papertrail failure', $data['error']);
    }
}
