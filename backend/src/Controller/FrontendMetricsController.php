<?php
// src/Controller/FrontendMetricsController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontendMetricsController extends AbstractController
{
    #[Route('/metrics/frontend', name: 'frontend_metrics')]
    public function frontendMetrics(Request $request): Response
    {
        // =========================================
        // TOP 8 MÉTRIQUES FRONTEND ESSENTIELLES
        // =========================================
        
        // 1. LCP - Largest Contentful Paint (Core Web Vital)
        $lcp = $this->getRealisticLCP();
        
        // 2. FID - First Input Delay (Core Web Vital)
        $fid = $this->getRealisticFID();
        
        // 3. CLS - Cumulative Layout Shift (Core Web Vital)
        $cls = $this->getRealisticCLS();
        
        // 4. Page Load Time
        $pageLoadTime = $this->getPageLoadTime();
        
        // 5. Page Views
        $pageViews = $this->incrementCounter('page_views');
        
        // 6. JavaScript Errors
        $jsErrors = $this->getJSErrors();
        
        // 7. API Errors
        $apiErrors = $this->getAPIErrors();
        
        // 8. Bounce Rate
        $bounceRate = $this->getBounceRate();
        
        // Format Prometheus
        $metrics = <<<METRICS
# HELP frontend_lcp_seconds Largest Contentful Paint (objectif: < 2.5s)
# TYPE frontend_lcp_seconds gauge
frontend_lcp_seconds $lcp

# HELP frontend_fid_seconds First Input Delay (objectif: < 0.1s)
# TYPE frontend_fid_seconds gauge
frontend_fid_seconds $fid

# HELP frontend_cls_ratio Cumulative Layout Shift (objectif: < 0.1)
# TYPE frontend_cls_ratio gauge
frontend_cls_ratio $cls

# HELP frontend_page_load_seconds Page load time
# TYPE frontend_page_load_seconds gauge
frontend_page_load_seconds $pageLoadTime

# HELP frontend_page_views_total Total page views
# TYPE frontend_page_views_total counter
frontend_page_views_total $pageViews

# HELP frontend_js_errors_total JavaScript errors total
# TYPE frontend_js_errors_total counter
frontend_js_errors_total $jsErrors

# HELP frontend_api_errors_total API errors total
# TYPE frontend_api_errors_total counter
frontend_api_errors_total $apiErrors

# HELP frontend_bounce_rate_percent Bounce rate percentage
# TYPE frontend_bounce_rate_percent gauge
frontend_bounce_rate_percent $bounceRate

METRICS;
        
        return new Response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4'
        ]);
    }
    
    // =========================================
    // HELPER METHODS
    // =========================================
    
    private function getRealisticLCP(): float
    {
        // 80% < 2.5s, 15% 2.5-4s, 5% > 4s
        $rand = mt_rand(1, 100);
        
        if ($rand <= 80) {
            return round(mt_rand(800, 2500) / 1000, 3);
        } elseif ($rand <= 95) {
            return round(mt_rand(2500, 4000) / 1000, 3);
        } else {
            return round(mt_rand(4000, 8000) / 1000, 3);
        }
    }
    
    private function getRealisticFID(): float
    {
        // 90% < 100ms, 8% 100-300ms, 2% > 300ms
        $rand = mt_rand(1, 100);
        
        if ($rand <= 90) {
            return round(mt_rand(10, 100) / 1000, 3);
        } elseif ($rand <= 98) {
            return round(mt_rand(100, 300) / 1000, 3);
        } else {
            return round(mt_rand(300, 1000) / 1000, 3);
        }
    }
    
    private function getRealisticCLS(): float
    {
        // 85% < 0.1, 10% 0.1-0.25, 5% > 0.25
        $rand = mt_rand(1, 100);
        
        if ($rand <= 85) {
            return round(mt_rand(1, 10) / 100, 3);
        } elseif ($rand <= 95) {
            return round(mt_rand(10, 25) / 100, 3);
        } else {
            return round(mt_rand(25, 50) / 100, 3);
        }
    }
    
    private function getPageLoadTime(): float
    {
        return round(mt_rand(1500, 5000) / 1000, 3);
    }
    
    private function getBounceRate(): float
    {
        return round(mt_rand(25, 75), 1);
    }
    
    private function getCounter(string $key): int
    {
        $file = sys_get_temp_dir() . '/frontend_' . $key . '.txt';
        return file_exists($file) ? (int) file_get_contents($file) : 0;
    }
    
    private function incrementCounter(string $key): int
    {
        $value = $this->getCounter($key) + 1;
        $file = sys_get_temp_dir() . '/frontend_' . $key . '.txt';
        file_put_contents($file, $value);
        return $value;
    }
    
    private function getJSErrors(): int
    {
        $current = $this->getCounter('js_errors');
        if (mt_rand(1, 100) <= 5) { // 5% de chance d'erreur
            return $this->incrementCounter('js_errors');
        }
        return $current;
    }
    
    private function getAPIErrors(): int
    {
        $current = $this->getCounter('api_errors');
        if (mt_rand(1, 100) <= 2) { // 2% de chance d'erreur
            return $this->incrementCounter('api_errors');
        }
        return $current;
    }
}