<?php

namespace App\EventListener;

use App\Service\PrometheusRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: -100)]
class RequestMetricsListener
{
    public function __construct(
        private PrometheusRegistry $registry
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // ⛔ ignorer endpoints internes
        if ($path === '/metrics' || $path === '/health') {
            return;
        }

        $counter = $this->registry->get()->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'path']
        );

        $counter->inc([
            $request->getMethod(),
            $path,
        ]);
    }
}
