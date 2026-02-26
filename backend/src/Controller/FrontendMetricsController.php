<?php
namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class FrontendMetricsController
{
    private string $tmpDir;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    // Reçoit les métriques depuis React
    #[Route('/metrics/frontend/collect', name: 'frontend_metrics_collect', methods: ['POST'])]
    public function collect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['lcp']))
            file_put_contents($this->tmpDir . '/fm_lcp.txt', $data['lcp']);
        if (isset($data['fid']))
            file_put_contents($this->tmpDir . '/fm_fid.txt', $data['fid']);
        if (isset($data['cls']))
            file_put_contents($this->tmpDir . '/fm_cls.txt', $data['cls']);
        if (isset($data['page_load_time']))
            file_put_contents($this->tmpDir . '/fm_plt.txt', $data['page_load_time']);
        if (isset($data['js_errors']))
            file_put_contents($this->tmpDir . '/fm_js_errors.txt', $data['js_errors']);
        if (isset($data['page_views'])) {
            $current = (int)@file_get_contents($this->tmpDir . '/fm_page_views.txt');
            file_put_contents($this->tmpDir . '/fm_page_views.txt', $current + $data['page_views']);
        }
        if (isset($data['bounce'])) {
            $bounces = (int)@file_get_contents($this->tmpDir . '/fm_bounces.txt');
            $total = (int)@file_get_contents($this->tmpDir . '/fm_sessions.txt');
            file_put_contents($this->tmpDir . '/fm_bounces.txt', $bounces + $data['bounce']);
            file_put_contents($this->tmpDir . '/fm_sessions.txt', $total + 1);
        }

        return new JsonResponse(['ok' => true]);
    }

    // Expose les métriques pour Prometheus
    #[Route('/metrics/frontend', name: 'frontend_metrics')]
    public function frontendMetrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        $lcp = (float)@file_get_contents($this->tmpDir . '/fm_lcp.txt') ?: 0;
        $fid = (float)@file_get_contents($this->tmpDir . '/fm_fid.txt') ?: 0;
        $cls = (float)@file_get_contents($this->tmpDir . '/fm_cls.txt') ?: 0;
        $plt = (float)@file_get_contents($this->tmpDir . '/fm_plt.txt') ?: 0;
        $jsErrors = (int)@file_get_contents($this->tmpDir . '/fm_js_errors.txt') ?: 0;
        $pageViews = (int)@file_get_contents($this->tmpDir . '/fm_page_views.txt') ?: 0;
        $bounces = (int)@file_get_contents($this->tmpDir . '/fm_bounces.txt') ?: 0;
        $sessions = (int)@file_get_contents($this->tmpDir . '/fm_sessions.txt') ?: 1;
        $bounceRate = round(($bounces / $sessions) * 100, 1);

        $registry->getOrRegisterGauge('frontend', 'lcp_seconds', 'Largest Contentful Paint')
            ->set($lcp);
        $registry->getOrRegisterGauge('frontend', 'fid_seconds', 'First Input Delay')
            ->set($fid);
        $registry->getOrRegisterGauge('frontend', 'cls_ratio', 'Cumulative Layout Shift')
            ->set($cls);
        $registry->getOrRegisterGauge('frontend', 'page_load_seconds', 'Page load time')
            ->set($plt);
        $registry->getOrRegisterGauge('frontend', 'js_errors_total', 'JavaScript errors')
            ->set($jsErrors);
        $registry->getOrRegisterGauge('frontend', 'page_views_total', 'Page views')
            ->set($pageViews);
        $registry->getOrRegisterGauge('frontend', 'bounce_rate_percent', 'Bounce rate %')
            ->set($bounceRate);

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }
}