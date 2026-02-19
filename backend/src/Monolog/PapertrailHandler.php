<?php

namespace App\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class PapertrailHandler extends AbstractProcessingHandler
{
    public function __construct(
        private string $url,
        private string $token,
        int|Level $level = Level::Error,
    ) {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        $context = curl_init();

        curl_setopt_array($context, [
            CURLOPT_URL            => $this->url,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/octet-stream',
                "Authorization: Bearer {$this->token}",
            ],
            CURLOPT_POSTFIELDS     => $record->formatted ?? $record->message,
        ]);

        curl_exec($context);
        curl_close($context);
    }
}