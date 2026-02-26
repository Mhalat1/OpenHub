<?php
namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class BackendMetricsController
{
    private string $tmpDir;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    // React ou ton frontend appelle cet endpoint pour incrémenter les compteurs
    #[Route('/metrics/backend/collect', name: 'backend_metrics_collect', methods: ['POST'])]
    public function collect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? 200;
        $duration = $data['duration'] ?? 0;

        if ($status >= 500) {
            $this->increment('bm_5xx.txt');
        } elseif ($status >= 400) {
            $this->increment('bm_4xx.txt');
        } else {
            $this->increment('bm_2xx.txt');
        }

        // Stocke le temps de réponse pour P95
        $path = $this->tmpDir . '/bm_times.txt';
        $times = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        $times[] = (float)$duration;
        if (count($times) > 1000) $times = array_slice($times, -1000);
        file_put_contents($path, json_encode($times));

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/metrics/backend', name: 'backend_metrics')]
    public function metrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        $req2xx = (int)@file_get_contents($this->tmpDir . '/bm_2xx.txt');
        $req4xx = (int)@file_get_contents($this->tmpDir . '/bm_4xx.txt');
        $req5xx = (int)@file_get_contents($this->tmpDir . '/bm_5xx.txt');
        $totalRequests = $req2xx + $req4xx + $req5xx;

        // Total requêtes
        $registry->getOrRegisterGauge('app', 'requests_total', 'Total requests')
            ->set($totalRequests);

        // Débit par status
        $registry->getOrRegisterGauge('app', 'requests_2xx', 'Successful requests')
            ->set($req2xx);
        $registry->getOrRegisterGauge('app', 'requests_4xx', 'Client errors')
            ->set($req4xx);
        $registry->getOrRegisterGauge('app', 'requests_5xx', 'Server errors')
            ->set($req5xx);

        // Taux d'erreur
        $errorRate = $totalRequests > 0
            ? round((($req4xx + $req5xx) / $totalRequests) * 100, 2)
            : 0;
        $registry->getOrRegisterGauge('app', 'error_rate_percent', 'Error rate %')
            ->set($errorRate);

        // Disponibilité
        $availability = $totalRequests > 0
            ? round((($totalRequests - $req5xx) / $totalRequests) * 100, 2)
            : 100;
        $registry->getOrRegisterGauge('app', 'availability_percent', 'Availability %')
            ->set($availability);

        // P95
        $p95 = 0;
        $path = $this->tmpDir . '/bm_times.txt';
        if (file_exists($path)) {
            $times = json_decode(file_get_contents($path), true);
            if (!empty($times)) {
                sort($times);
                $index = (int)ceil(0.95 * count($times)) - 1;
                $p95 = round($times[$index], 3);
            }
        }
        $registry->getOrRegisterGauge('app', 'response_time_p95_seconds', 'P95 response time')
            ->set($p95);

        // CPU réel
        $load = sys_getloadavg();
        $registry->getOrRegisterGauge('app', 'cpu_load_1m', 'CPU load 1m')
            ->set(round($load[0], 2));

        // Mémoire réelle
        $registry->getOrRegisterGauge('app', 'memory_usage_mb', 'Memory usage MB')
            ->set(round(memory_get_usage(true) / 1024 / 1024, 2));
        $registry->getOrRegisterGauge('app', 'memory_peak_mb', 'Memory peak MB')
            ->set(round(memory_get_peak_usage(true) / 1024 / 1024, 2));

        // Uptime réel
        $uptime = 0;
        if (file_exists('/proc/uptime')) {
            $uptime = (int)explode(' ', file_get_contents('/proc/uptime'))[0];
        }
        $registry->getOrRegisterGauge('app', 'uptime_seconds', 'Uptime seconds')
            ->set($uptime);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }

    private function increment(string $file): void
    {
        $path = $this->tmpDir . '/' . $file;
        file_put_contents($path, (int)@file_get_contents($path) + 1);
    }
}