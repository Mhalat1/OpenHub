<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class PapertrailLogger implements LoggerInterface
{
    public function __construct(
        private string $url,
        private string $token
    ) {}

    public function log($level, $message, array $context = []): void
    {
        $this->send($level, $message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('ALERT', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('NOTICE', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    private function send(string $level, string $message, array $context = []): void
    {
        try {
            // Format compatible avec l'exemple curl
            $logLine = sprintf(
                "[%s] %s %s %s\n",
                date('Y-m-d H:i:s'),
                $level,
                $message,
                !empty($context) ? json_encode($context) : ''
            );

            // Utiliser file_get_contents avec un contexte HTTP
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/octet-stream',
                        'Authorization: Bearer ' . $this->token,
                    ],
                    'content' => $logLine,
                    'timeout' => 1,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];

            $streamContext = stream_context_create($options);
            
            // @ pour ignorer les warnings
            @file_get_contents($this->url, false, $streamContext);
            
        } catch (\Exception $e) {
            // Fallback silencieux
            error_log('Papertrail error: ' . $e->getMessage());
        }
    }
}