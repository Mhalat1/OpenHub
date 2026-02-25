<?php
// src/Service/PapertrailService.php

namespace App\Service;

class PapertrailService
{
    private string $url;
    private string $token;
    private TraceIdGenerator $traceIdGenerator;  // ← AJOUT

    public function __construct(
        string $url, 
        string $token,
        TraceIdGenerator $traceIdGenerator        // ← AJOUT
    ) {
        $this->url = $url;
        $this->token = $token;
        $this->traceIdGenerator = $traceIdGenerator;  // ← AJOUT
    }

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

    private function send(string $level, string $message, array $context = []): void
    {
        try {
            // Ajouter automatiquement le trace_id au contexte
            $context['trace_id'] = $this->traceIdGenerator->get();  // ← AJOUT

            $logLine = sprintf(
                "<22>%s %s openhub: [%s] %s %s\n",
                date('M d H:i:s'),
                gethostname(),
                $level,
                $message,
                json_encode($context)
            );

            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/octet-stream',
                'Authorization: Bearer ' . $this->token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $logLine);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            curl_close($ch);
            
        } catch (\Exception $e) {
            error_log('>>> PAPERTRAIL SERVICE ERROR: ' . $e->getMessage());
        }
    }
}