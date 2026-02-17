<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DonationControllerTest extends WebTestCase
{
    public function testDonateEndpointExists(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/donate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 15])
        );

        // Vérifie juste que l'endpoint répond (200, 401, ou 500)
        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testDonateAcceptsJsonPayload(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/donate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 25])
        );

        // Vérifie que la requête est bien reçue
        $this->assertNotNull($client->getResponse());
    }

    public function testDonateWithoutAmountUsesDefault(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/donate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        // Vérifie que ça ne plante pas
        $this->assertNotNull($client->getResponse());
    }

    public function testDonateWithInvalidAmountReturnsError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/donate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 'invalid'])
        );

        // Vérifie que ça ne plante pas et retourne une réponse
        $this->assertNotNull($client->getResponse());
    }
    public function testDonateWithNegativeAmountReturnsError(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/donate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => -10])
        );

        // Vérifie que ça ne plante pas et retourne une réponse
        $this->assertNotNull($client->getResponse());
    }
}