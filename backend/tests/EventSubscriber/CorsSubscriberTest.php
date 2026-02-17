<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\CorsSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriberTest extends TestCase
{
    private CorsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new CorsSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CorsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertEquals(['onKernelRequest', 9999], $events[KernelEvents::REQUEST]);
        $this->assertEquals(['onKernelResponse', 9999], $events[KernelEvents::RESPONSE]);
    }

    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'OPTIONS');
        
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST
        );

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelRequestHandlesOptionsRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost:5173');
        
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, OPTIONS, PUT, DELETE, PATCH', $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testOnKernelRequestDoesNotHandleNonOptionsRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $this->subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelResponseIgnoresSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $response = new Response();
        
        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        // Les headers CORS ne doivent pas être ajoutés pour les sub-requests
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testOnKernelResponseAddsCorsHeaders(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $request->headers->set('Origin', 'http://localhost:5173');
        $response = new Response();
        
        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, OPTIONS, PUT, DELETE, PATCH', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, Authorization, X-Requested-With, Accept, Origin', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Max-Age'));
    }

    #[DataProvider('allowedOriginsProvider')]
    public function testAddCorsHeadersWithAllowedOrigins(string $origin): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $request->headers->set('Origin', $origin);
        $response = new Response();
        
        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        $this->assertEquals($origin, $response->headers->get('Access-Control-Allow-Origin'));
    }

    public static function allowedOriginsProvider(): array
    {
        return [
            ['http://localhost:5173'],
            ['http://127.0.0.1:5173'],
            ['http://localhost:8000'],
            ['https://www.mh-logiciel.fr'],
            ['https://openhub-frontend.onrender.com'],
            ['https://openhub-backend.onrender.com'],
        ];
    }

    public function testAddCorsHeadersWithUnallowedOrigin(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $request->headers->set('Origin', 'https://malicious-site.com');
        $response = new Response();
        
        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        // Les autres headers CORS sont toujours ajoutés
        $this->assertEquals('GET, POST, OPTIONS, PUT, DELETE, PATCH', $response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testAddCorsHeadersWithoutOrigin(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $response = new Response();
        
        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, OPTIONS, PUT, DELETE, PATCH', $response->headers->get('Access-Control-Allow-Methods'));
    }
}