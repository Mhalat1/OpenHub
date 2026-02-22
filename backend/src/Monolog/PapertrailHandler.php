<?php

namespace App\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;
use Monolog\Logger;

class PapertrailHandler extends AbstractProcessingHandler
{
    private string $url;
    private string $token;

    public function __construct(string $url, string $token, Level|int $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->url = $url;
        $this->token = $token;
        
        error_log('>>> PAPERTRAIL HANDLER: Constructeur appelé');
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    protected function write(LogRecord $record): void
    {
        error_log('>>> PAPERTRAIL HANDLER: write() called');
        
        try {
            $logLine = sprintf(
                "<22>%s %s openhub: %s %s\n",
                $record->datetime->format('M d H:i:s'),
                gethostname(),
                $record->message,
                json_encode($record->context)
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