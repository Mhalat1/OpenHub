<?php

namespace App\Service;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

class PrometheusRegistry
{
    private static ?CollectorRegistry $registry = null;

    public function get(): CollectorRegistry
    {
        if (self::$registry === null) {
            $adapter = new Redis([
                'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
                'timeout' => 0.1,
                'read_timeout' => 10,
                'persistent_connections' => false
            ]);
            
            self::$registry = new CollectorRegistry($adapter);
        }

        return self::$registry;
    }
}