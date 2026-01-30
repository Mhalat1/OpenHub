<?php
// src/Controller/MetricsController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BackendMetricsController extends AbstractController
{
    #[Route('/metrics/backend', name: 'backend_metrics')]
    public function metrics(Request $request): Response
    {
        $userAgent = $request->headers->get('User-Agent', '');
        
        // =========================================
        // TOP 10 MÉTRIQUES ESSENTIELLES
        // =========================================
        
        // 1. TOTAL REQUESTS
        $totalRequests = $this->incrementCounter('http_requests_total');
        
        // 2-4. REQUESTS PAR STATUS CODE
        $requests2xx = $this->getCounter('http_requests_2xx');
        $requests4xx = $this->getCounter('http_requests_4xx');
        $requests5xx = $this->getCounter('http_requests_5xx');
        
        // Simuler quelques erreurs aléatoires
        if (rand(1, 100) <= 2) {
            $this->incrementCounter('http_requests_4xx');
            $requests4xx++;
        } elseif (rand(1, 100) <= 1) {
            $this->incrementCounter('http_requests_5xx');
            $requests5xx++;
        } else {
            $this->incrementCounter('http_requests_2xx');
            $requests2xx++;
        }
        
        // 5. TAUX D'ERREUR (%)
        $errorRate = $totalRequests > 0 
            ? round((($requests4xx + $requests5xx) / $totalRequests) * 100, 2) 
            : 0;
        
        // 6. DISPONIBILITÉ (%)
        $availability = $totalRequests > 0 
            ? round((($totalRequests - $requests5xx) / $totalRequests) * 100, 4) 
            : 100;
        
        // 7. TEMPS DE RÉPONSE P95
        $responseTimeP95 = $this->getResponseTimePercentile(95);
        
        // 8. MÉMOIRE UTILISÉE (MB)
        $memoryUsedMB = round(memory_get_usage(true) / 1024 / 1024, 2);
        
        // 9. CPU USAGE (%)
        $cpuUsage = $this->getCpuUsage();
        
        // 10. UPTIME (secondes)
        $uptime = $this->getUptime();
        
        // Format Prometheus
        $metrics = <<<METRICS
# HELP http_requests_total Total HTTP requests
# TYPE http_requests_total counter
http_requests_total $totalRequests

# HELP http_requests_by_status HTTP requests by status code
# TYPE http_requests_by_status counter
http_requests_by_status{status="2xx"} $requests2xx
http_requests_by_status{status="4xx"} $requests4xx
http_requests_by_status{status="5xx"} $requests5xx

# HELP app_error_rate_percent Percentage of failed requests
# TYPE app_error_rate_percent gauge
app_error_rate_percent $errorRate

# HELP app_availability_percent Service availability percentage
# TYPE app_availability_percent gauge
app_availability_percent $availability

# HELP http_response_time_p95_seconds 95th percentile response time
# TYPE http_response_time_p95_seconds gauge
http_response_time_p95_seconds $responseTimeP95

# HELP app_memory_usage_mb Memory usage in megabytes
# TYPE app_memory_usage_mb gauge
app_memory_usage_mb $memoryUsedMB

# HELP app_cpu_usage_percent CPU usage percentage
# TYPE app_cpu_usage_percent gauge
app_cpu_usage_percent $cpuUsage

# HELP app_uptime_seconds Application uptime in seconds
# TYPE app_uptime_seconds counter
app_uptime_seconds $uptime

METRICS;
        
        return new Response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4'
        ]);
    }
    
    // =========================================
    // HELPER METHODS
    // =========================================
    
    private function getCounter(string $key): int
    {
        $file = sys_get_temp_dir() . '/metrics_' . $key . '.txt';
        return file_exists($file) ? (int) file_get_contents($file) : 0;
    }
    
    private function incrementCounter(string $key, int $increment = 1): int
    {
        $value = $this->getCounter($key) + $increment;
        $file = sys_get_temp_dir() . '/metrics_' . $key . '.txt';
        file_put_contents($file, $value);
        return $value;
    }
    
    private function getResponseTimePercentile(int $percentile): float
    {
        // P95 : 300-600ms
        return round(mt_rand(300, 600) / 1000, 3);
    }
    
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 10, 2);
        }
        return round(mt_rand(5, 45), 2);
    }
    
    private function getUptime(): int
    {
        $startFile = sys_get_temp_dir() . '/metrics_start_time.txt';
        
        if (!file_exists($startFile)) {
            $startTime = time();
            file_put_contents($startFile, $startTime);
        } else {
            $startTime = (int) file_get_contents($startFile);
        }
        
        return time() - $startTime;
    }
}