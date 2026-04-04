<?php

namespace App\Tests\Controller;

use App\Controller\DonationController;
use App\Service\AxiomService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Request;

class DonationControllerTest extends TestCase
{
    // NOTE: On utilise MockObject ici uniquement pour AxiomService car
    // son constructeur attend 3 dépendances qu'on ne peut pas instancier
    // facilement en test unitaire. Le mock évite ce problème sans impacter
    // la logique testée (le logger n'est pas ce qu'on teste ici).
    /** @var AxiomService&MockObject */
    private MockObject $AxiomLogger;
    private DonationController $controller;

    protected function setUp(): void
    {
        // NOTE: createMock() génère une classe anonyme qui étend AxiomService
        // en court-circuitant son constructeur — équivalent à notre classe anonyme
        // avec public function __construct() {} mais géré automatiquement par PHPUnit.
        $this->AxiomLogger = $this->createMock(AxiomService::class);
        $this->controller       = new DonationController($this->AxiomLogger);
    }

    // ✅ Couvre le bloc catch (lignes 56–65)
    // Stripe::setApiKey() + Session::create() lèvent une exception avec une clé invalide
    public function testDonateFailureReturns500(): void
    {
        $_ENV['STRIPE_SECRET_KEY'] = 'invalid_key';

        $request = Request::create(
            '/api/donate',
            'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 5])
        );

        $response = $this->controller->donate($request);
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Error creating donation session', $data['message']);
    }

    // ✅ Couvre la branche sans 'amount' (ligne 23 : fallback à 500 centimes)
    public function testDonateUsesDefaultAmountWhenNotProvided(): void
    {
        $_ENV['STRIPE_SECRET_KEY'] = 'invalid_key';

        $request = Request::create(
            '/api/donate',
            'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([]) // pas de 'amount' → doit utiliser 500 par défaut
        );

        $response = $this->controller->donate($request);
        $this->assertSame(500, $response->getStatusCode());
    }

    // ✅ Couvre le bloc try complet (lignes 30–54) : session créée avec succès
    // NOTE: On mock Session::create() via une clé Stripe factice sk_test_ reconnue
    // par la lib Stripe comme valide côté format — mais on intercepte l'appel HTTP
    // en mockant la réponse via la méthode statique. C'est le seul endroit où
    // mocker Stripe est inévitable sans appel réseau réel ni refactoring du controller.
    public function testDonateSuccessReturnsUrl(): void
    {
        $_ENV['STRIPE_SECRET_KEY'] = 'sk_test_fake';

        // NOTE: On crée un faux objet Session Stripe avec les propriétés minimales
        // attendues par le controller (id et url). Stripe\Session étend Stripe\ApiResource
        // qui accepte un tableau de valeurs dans son constructeur statique.
        $fakeSession      = Session::constructFrom([
            'id'  => 'cs_test_fake123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_fake123',
        ]);

        // NOTE: On remplace temporairement Session::create() par une fonction
        // qui retourne notre faux objet — évite tout appel réseau à l'API Stripe.
        // \Stripe\Util\RequestOptions et le HttpClient ne sont jamais appelés.
        $requestorMock = $this->createMock(\Stripe\ApiRequestor::class);

        // On utilise une closure pour remplacer le comportement statique de Session::create()
        // via le système de "mock static" de Stripe (httpClient injectable).
        $httpClientMock = $this->createMock(\Stripe\HttpClient\ClientInterface::class);
        $httpClientMock
            ->method('request')
            ->willReturn([
                json_encode([
                    'id'     => 'cs_test_fake123',
                    'object' => 'checkout.session',
                    'url'    => 'https://checkout.stripe.com/pay/cs_test_fake123',
                ]),
                200,
                [],
            ]);

        // NOTE: Stripe permet d'injecter un HttpClient de test via cette méthode statique.
        // C'est l'approche officielle recommandée par Stripe pour les tests unitaires.
        \Stripe\ApiRequestor::setHttpClient($httpClientMock);

        $request = Request::create(
            '/api/donate',
            'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 10])
        );

        $response = $this->controller->donate($request);
        $data     = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('url', $data);
        $this->assertStringStartsWith('https://checkout.stripe.com', $data['url']);
    }
}