<?php

namespace App\Service;

class TraceIdGenerator
{
    private string $traceId;

    public function __construct()
    {
        $this->traceId = $this->generate();
    }

    public function get(): string
    {
        return $this->traceId;
    }

    private function generate(): string
    {
        // Format : timestamp-random pour lisibilité dans Papertrail
        return sprintf(
            '%s-%s',
            date('His'),                    // ex: 143052
            substr(bin2hex(random_bytes(6)), 0, 8) // ex: a3f2b1c4
        );
    }
}