<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Gestion des requêtes OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response();
            $this->addCorsHeaders($response, $request);
            $response->setStatusCode(200);
            $event->setResponse($response);
            return;
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->addCorsHeaders($event->getResponse(), $event->getRequest());
    }

    private function addCorsHeaders(Response $response, $request): void
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:8000',
            'https://www.mh-logiciel.fr',
            'https://openhub-frontend.onrender.com',
            'https://openhub-backend.onrender.com',


        ];

        // Autorise l'origine si elle est dans la liste
        if (in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE, PATCH');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}