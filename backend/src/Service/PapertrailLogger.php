<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class PapertrailLogger implements LoggerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
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
                json_encode($context)
            );

            $this->httpClient->request('POST', $this->url, [
                'headers' => [
                    'Content-Type' => 'application/octet-stream',
                    'Authorization' => 'Bearer ' . $this->token,
                ],
                'body' => $logLine,
                'timeout' => 1,
                'max_redirects' => 0,
            ]);
        } catch (\Exception $e) {
            // Silently fail - log to error log as fallback
            error_log('Papertrail error: ' . $e->getMessage());
        }
    }
}