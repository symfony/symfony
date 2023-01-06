<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'http_client' => [
        'default_options' => [
            'retry_failed' => [
                'retry_strategy' => null,
                'http_codes' => [429, 500 => ['GET', 'HEAD']],
                'max_retries' => 2,
                'delay' => 100,
                'multiplier' => 2,
                'max_delay' => 0,
                'jitter' => 0.3,
            ],
        ],
        'scoped_clients' => [
            'foo' => [
                'base_uri' => 'http://example.com',
                'retry_failed' => ['multiplier' => 4],
            ],
        ],
    ],
]);
