<?php
namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FrontendMetricsController
{
    #[Route('/metrics/frontend', name: 'frontend_metrics')]
    public function frontendMetrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        $registry->getOrRegisterGauge('frontend', 'lcp_seconds', 'Largest Contentful Paint')
            ->set(round(mt_rand(800, 2500) / 1000, 3));

        $registry->getOrRegisterGauge('frontend', 'fid_seconds', 'First Input Delay')
            ->set(round(mt_rand(10, 100) / 1000, 3));

        $registry->getOrRegisterGauge('frontend', 'cls_ratio', 'Cumulative Layout Shift')
            ->set(round(mt_rand(1, 10) / 100, 3));

        $registry->getOrRegisterGauge('frontend', 'page_load_seconds', 'Page load time')
            ->set(round(mt_rand(1500, 5000) / 1000, 3));

        $registry->getOrRegisterGauge('frontend', 'bounce_rate_percent', 'Bounce rate')
            ->set(round(mt_rand(25, 75), 1));

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        return new Response($result, 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE
        ]);
    }
}