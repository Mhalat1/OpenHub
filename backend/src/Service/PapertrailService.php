<?php
// src/Service/PapertrailService.php

namespace App\Service;

class PapertrailService
{
    private string $url;
    private string $token;
    private string $traceId;

    public function __construct(string $url, string $token)
    {
        $this->url     = $url;
        $this->token   = $token;
        $this->traceId = $this->generateTraceId();
    }

    // ─── API publique ────────────────────────────────────────────────────────

    public function debug(string $message, array $context = []): void
    {
        $this->send('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->send('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->send('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->send('ERROR', $message, $context);
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function generateTraceId(): string
    {
        return sprintf(
            '%s-%s',
            date('His'),
            substr(bin2hex(random_bytes(6)), 0, 8)
        );
    }

    private function send(string $level, string $message, array $context = []): void
    {
        try {
            // Injecte automatiquement trace_id et timestamp dans le contexte
            $context['trace_id'] = $this->traceId;
            $context['ts']       = (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.u\Z');

            $logLine = sprintf(
                "<22>%s %s openhub [%s] %s %s\n",
                date('M d H:i:s'),
                gethostname() ?: 'unknown',
                $level,
                $message,
                json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_POST,          true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,    [
                'Content-Type: application/octet-stream',
                'Authorization: Bearer ' . $this->token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS,    $logLine);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT,        2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);

        } catch (\Exception $e) {
            error_log('PapertrailService error: ' . $e->getMessage());
        }
    }
}