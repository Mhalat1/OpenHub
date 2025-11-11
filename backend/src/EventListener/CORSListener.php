<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

class CORSListener
{
    public function onKernelResponse(ResponseEvent $event)
    {
        // Cette couche est maintenant redondante mais gardons-la pour sécurité
        $request = $event->getRequest();
        $response = $event->getResponse();

        $allowedOrigins = [
            'https://openhub-front.onrender.com',
            'http://127.0.0.1:5173', 
            'http://localhost:5173'
        ];
        
        $origin = $request->headers->get('Origin');
        
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }
}