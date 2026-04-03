<?php
// src/Service/PapertrailService.php

namespace App\Service;

class PapertrailService
{
    private string $url;
    private string $token;
    private TraceIdGenerator $traceIdGenerator;

    public function __construct(
        string $url, 
        string $token,
        TraceIdGenerator $traceIdGenerator
    ) {
        $this->url = $url;
        $this->token = $token;
        $this->traceIdGenerator = $traceIdGenerator;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->send('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->send('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->send('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->send('error', $message, $context);
    }

    private function send(string $level, string $message, array $context = []): void
    {
        try {
            // Ajouter automatiquement le trace_id au contexte
            $context['trace_id'] = $this->traceIdGenerator->get();

            // Format pour AXIOM (légèrement différent)
            $payload = json_encode([
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'service' => 'open-hub',
                'environment' => $_ENV['APP_ENV'] ?? 'dev',
                'hostname' => gethostname(),
                '_time' => date('c') // Ajout du timestamp ISO 8601 pour Axiom
            ]);

            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Log en cas d'erreur (optionnel)
            if ($httpCode >= 400) {
                error_log(">>> Axiom error: HTTP {$httpCode} - {$response}");
            }
            
        } catch (\Exception $e) {
            error_log('>>> AXIOM SERVICE ERROR: ' . $e->getMessage());
        }
    }
}