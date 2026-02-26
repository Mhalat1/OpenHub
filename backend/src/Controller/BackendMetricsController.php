<?php
namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BackendMetricsController
{
    #[Route('/metrics/backend', name: 'backend_metrics')]
    public function metrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        // Mémoire réelle PHP
        $registry->getOrRegisterGauge('app', 'memory_usage_mb', 'Memory usage')
            ->set(round(memory_get_usage(true) / 1024 / 1024, 2));

        $registry->getOrRegisterGauge('app', 'memory_peak_mb', 'Memory peak')
            ->set(round(memory_get_peak_usage(true) / 1024 / 1024, 2));

        // CPU réel
        $load = sys_getloadavg();
        $registry->getOrRegisterGauge('app', 'cpu_load_1m', 'CPU load 1 minute')
            ->set(round($load[0], 2));

        // Uptime réel
        $uptime = 0;
        if (file_exists('/proc/uptime')) {
            $uptime = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
        }
        $registry->getOrRegisterGauge('app', 'uptime_seconds', 'System uptime')
            ->set($uptime);

        // PHP version info
        $registry->getOrRegisterGauge('app', 'php_info', 'PHP version', ['version'])
            ->set(1, [PHP_VERSION]);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }
}