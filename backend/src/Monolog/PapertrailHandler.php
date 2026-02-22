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

    protected function write(LogRecord $record): void
    {
        // 1. TOUJOURS logger dans error_log pour voir si on passe ici
        error_log('>>> PAPERTRAIL HANDLER: write() called');
        
        try {
            $logLine = sprintf(
                "<22>%s %s openhub: %s %s\n",
                $record->datetime->format('M d H:i:s'),
                gethostname(),
                $record->message,
                json_encode($record->context)
            );

            error_log('>>> PAPERTRAIL HANDLER: Log line prepared: ' . substr($logLine, 0, 100));

            // Initialisation curl
            $ch = curl_init($this->url);
            if ($ch === false) {
                error_log('>>> PAPERTRAIL HANDLER: curl_init failed');
                return;
            }

            error_log('>>> PAPERTRAIL HANDLER: curl initialized for URL: ' . $this->url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/octet-stream',
                'Authorization: Bearer ' . $this->token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $logLine);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout plus long pour debug
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // TRÈS IMPORTANT pour debug
            
            error_log('>>> PAPERTRAIL HANDLER: curl options set, executing...');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            error_log('>>> PAPERTRAIL HANDLER: curl completed. HTTP code: ' . $httpCode);
            if ($error) {
                error_log('>>> PAPERTRAIL HANDLER: curl error: ' . $error);
            }
            if ($response) {
                error_log('>>> PAPERTRAIL HANDLER: response: ' . substr($response, 0, 200));
            }
            
        } catch (\Exception $e) {
            error_log('>>> PAPERTRAIL HANDLER: EXCEPTION: ' . $e->getMessage());
            error_log('>>> PAPERTRAIL HANDLER: Exception trace: ' . $e->getTraceAsString());
        }
        
        error_log('>>> PAPERTRAIL HANDLER: write() finished');
    }
}