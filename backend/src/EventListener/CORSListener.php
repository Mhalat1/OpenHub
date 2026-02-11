<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Response;

class CORSListener
{
    private array $allowedOrigins = [
        'https://openhub-front.onrender.com',
        'http://127.0.0.1:5173', 
        'http://localhost:5173'
    ];

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $method = $request->getRealMethod();
        $origin = $request->headers->get('Origin');
        $path = $request->getPathInfo();

        // Only handle API paths
        if (str_starts_with($path, '/api/')) {
            // Handle preflight OPTIONS request
            if ($method === 'OPTIONS') {
                $response = new Response();
                $response->setStatusCode(204);
                
                if (in_array($origin, $this->allowedOrigins)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                    $response->headers->set('Access-Control-Max-Age', '3600');
                }
                
                $event->setResponse($response);
                return;
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $origin = $request->headers->get('Origin');
        $path = $request->getPathInfo();
        
        // Only handle API paths
        if (str_starts_with($path, '/api/') && in_array($origin, $this->allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}