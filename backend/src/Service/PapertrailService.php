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

    private function send(string $level, string $message, array $context = []): void
    {
        try {
            // Priorité : 
            // 1. trace_id passé explicitement dans $context
            // 2. trace_id existant du générateur (déjà initialisé)
            // 3. nouveau trace_id
            $traceId = $context['trace_id'] ?? $this->traceIdGenerator->get();
            
            // Construction du payload POUR AXIOM (format plat)
            $logEntry = [
                'level' => $level,
                'message' => $message,
                'trace_id' => $traceId,
                'service' => 'open-hub',
                'source' => 'symfony-api',
                'environment' => $_ENV['APP_ENV'] ?? 'dev',
                'hostname' => gethostname(),
                '_time' => (new \DateTime())->format('Y-m-d\TH:i:s.u\Z'),
            ];
            
            // Ajouter les champs supplémentaires du contexte (à plat)
            foreach ($context as $key => $value) {
                // Éviter d'écraser trace_id et les champs obligatoires
                if (!in_array($key, ['trace_id', 'level', 'message', 'service'])) {
                    $logEntry[$key] = $value;
                }
            }
            
            // Axiom attend un TABLEAU d'objets
            $payload = json_encode([$logEntry]);
            
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
            
            if ($httpCode >= 400) {
                error_log(">>> Axiom error: HTTP {$httpCode} - {$response}");
            }
            
        } catch (\Exception $e) {
            error_log('>>> AXIOM SERVICE ERROR: ' . $e->getMessage());
        }
    }
    
    // Les méthodes publiques restent identiques
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
}