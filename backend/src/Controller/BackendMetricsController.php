<?php
namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BackendMetricsController
{
    private string $tmpDir;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    #[Route('/metrics/backend', name: 'backend_metrics')]
    public function metrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        // Parse les vrais logs Apache
        $logFile = '/var/log/apache2/access.log';
        $req2xx = 0; $req4xx = 0; $req5xx = 0;
        $responseTimes = [];

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // Dernières 10000 lignes
            $lines = array_slice($lines, -10000);
            foreach ($lines as $line) {
                // Parse status code
                if (preg_match('/" (\d{3}) /', $line, $m)) {
                    $status = (int)$m[1];
                    if ($status >= 500) $req5xx++;
                    elseif ($status >= 400) $req4xx++;
                    else $req2xx++;
                }
                // Parse response time si disponible
                if (preg_match('/(\d+)$/', $line, $m)) {
                    $responseTimes[] = (int)$m[1] / 1000000;
                }
            }
        }

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

        // P95 depuis les logs
        $p95 = 0;
        if (!empty($responseTimes)) {
            sort($responseTimes);
            $index = (int)ceil(0.95 * count($responseTimes)) - 1;
            $p95 = round($responseTimes[$index], 3);
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
}