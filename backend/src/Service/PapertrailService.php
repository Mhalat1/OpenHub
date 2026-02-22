<?php
// src/Service/PapertrailService.php

namespace App\Service;

class PapertrailService
{
    private string $url;
    private string $token;

    public function debug(string $message, array $context = []): void
{
    $this->send('DEBUG', $message, $context);
}


    public function __construct(string $url, string $token)
    {
        $this->url = $url;
        $this->token = $token;
        
        error_log('>>> PAPERTRAIL SERVICE: Constructeur appelé');
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
            
            error_log(">>> PAPERTRAIL SERVICE: Log sent successfully");
            
        } catch (\Exception $e) {
            error_log('>>> PAPERTRAIL SERVICE ERROR: ' . $e->getMessage());
        }
    }
}