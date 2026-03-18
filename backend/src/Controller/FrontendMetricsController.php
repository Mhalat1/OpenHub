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
        // Sous-dossier dédié, cohérent avec BackendMetricsController
        $this->tmpDir = sys_get_temp_dir() . '/openhub_metrics';

        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }
    }

    // React appelle cet endpoint avec { lcp, fid, cls, page_load_time, js_errors, page_views, bounce }
    #[Route('/metrics/frontend/collect', name: 'frontend_metrics_collect', methods: ['POST'])]
    public function collect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        // Métriques "dernière valeur connue" — on écrase à chaque fois
        // Source : librairie web-vitals (Google) côté React
        if (isset($data['lcp']))
            file_put_contents($this->tmpDir . '/fm_lcp.txt', $data['lcp'], LOCK_EX);

        if (isset($data['fid']))
            file_put_contents($this->tmpDir . '/fm_fid.txt', $data['fid'], LOCK_EX);

        if (isset($data['cls']))
            file_put_contents($this->tmpDir . '/fm_cls.txt', $data['cls'], LOCK_EX);

        // Source : window.performance (natif navigateur)
        if (isset($data['page_load_time']))
            file_put_contents($this->tmpDir . '/fm_plt.txt', $data['page_load_time'], LOCK_EX);

        // Source : window.onerror (natif navigateur) — on écrase, c'est un compteur courant
        if (isset($data['js_errors']))
            file_put_contents($this->tmpDir . '/fm_js_errors.txt', $data['js_errors'], LOCK_EX);

        // Page views — on accumule (Source : React Router, logique manuelle)
        if (isset($data['page_views'])) {
            $current = $this->readInt('fm_page_views.txt');
            file_put_contents($this->tmpDir . '/fm_page_views.txt', $current + (int)$data['page_views'], LOCK_EX);
        }

        // Bounce — on accumule bounces ET sessions séparément
        // Source : beforeunload + détection de clic (logique manuelle React)
        // bounce = 1 si le visiteur repart sans interagir, 0 sinon
        if (isset($data['bounce'])) {
            $bounces  = $this->readInt('fm_bounces.txt');
            $sessions = $this->readInt('fm_sessions.txt');
            file_put_contents($this->tmpDir . '/fm_bounces.txt',  $bounces  + (int)$data['bounce'], LOCK_EX);
            file_put_contents($this->tmpDir . '/fm_sessions.txt', $sessions + 1, LOCK_EX);
        }

        return new JsonResponse(['ok' => true]);
    }

    // Prometheus scrape cet endpoint (GET)
    #[Route('/metrics/frontend', name: 'frontend_metrics', methods: ['GET'])]
    public function frontendMetrics(): Response
    {
        $registry = new CollectorRegistry(new InMemory());

        // --- Lire toutes les valeurs depuis les fichiers ---
        $lcp      = $this->readFloat('fm_lcp.txt');
        $fid      = $this->readFloat('fm_fid.txt');
        $cls      = $this->readFloat('fm_cls.txt');
        $plt      = $this->readFloat('fm_plt.txt');
        $jsErrors = $this->readInt('fm_js_errors.txt');
        $pageViews = $this->readInt('fm_page_views.txt');
        $bounces  = $this->readInt('fm_bounces.txt');

        // Sessions minimum à 1 pour éviter la division par zéro
        $sessions = max(1, $this->readInt('fm_sessions.txt'));

        // Bounce rate : (bounces / sessions) × 100
        // Exemple : 4 bounces / 10 sessions = 40%
        $bounceRate = round(($bounces / $sessions) * 100, 1);

        // --- Enregistrer dans Prometheus ---
        // Core Web Vitals (source : librairie web-vitals Google)
        $registry->getOrRegisterGauge('frontend', 'lcp_seconds',        'Largest Contentful Paint in seconds')->set($lcp);
        $registry->getOrRegisterGauge('frontend', 'fid_seconds',        'First Input Delay in seconds')->set($fid);
        $registry->getOrRegisterGauge('frontend', 'cls_ratio',          'Cumulative Layout Shift score')->set($cls);

        // Performance (source : window.performance)
        $registry->getOrRegisterGauge('frontend', 'page_load_seconds',  'Page load time in seconds')->set($plt);

        // Erreurs JS (source : window.onerror)
        $registry->getOrRegisterGauge('frontend', 'js_errors_total',    'JavaScript errors count')->set($jsErrors);

        // Trafic (source : React Router + logique manuelle)
        $registry->getOrRegisterGauge('frontend', 'page_views_total',   'Total page views')->set($pageViews);
        $registry->getOrRegisterGauge('frontend', 'bounce_rate_percent', 'Bounce rate in percent')->set($bounceRate);

        // --- Rendu au format texte attendu par Prometheus ---
        $renderer = new RenderTextFormat();
        $output   = $renderer->render($registry->getMetricFamilySamples());

        return new Response($output, 200, [
            'Content-Type'  => RenderTextFormat::MIME_TYPE,
            'Cache-Control' => 'no-store',
        ]);
    }

    // Lit un entier depuis un fichier, retourne 0 si absent
    private function readInt(string $file): int
    {
        $path = $this->tmpDir . '/' . $file;
        return file_exists($path) ? (int)file_get_contents($path) : 0;
    }

    // Lit un float depuis un fichier, retourne 0.0 si absent
    private function readFloat(string $file): float
    {
        $path = $this->tmpDir . '/' . $file;
        return file_exists($path) ? (float)file_get_contents($path) : 0.0;
    }
}