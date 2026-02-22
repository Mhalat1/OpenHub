<?php

namespace App\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Logger;

class PapertrailHandler extends AbstractProcessingHandler
{
    private string $url;
    private string $token;

    public function __construct(string $url, string $token, $level = Logger::DEBUG, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->url = $url;
        $this->token = $token;
    }

    /**
     * @param array|LogRecord $record
     */
    protected function write($record): void
    {
        try {
            // Compatible avec Monolog 2 et 3
            if ($record instanceof LogRecord) {
                // Monolog 3
                $datetime = $record->datetime;
                $message = $record->message;
                $context = $record->context;
            } else {
                // Monolog 2 (array)
                $datetime = $record['datetime'];
                $message = $record['message'];
                $context = $record['context'] ?? [];
            }

            $logLine = sprintf(
                "<22>%s %s openhub: %s %s\n",
                $datetime->format('M d H:i:s'),
                gethostname(),
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
            error_log('Papertrail error: ' . $e->getMessage());
        }
    }
}