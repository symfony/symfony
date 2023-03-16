<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'serializer' => true,
    'messenger' => [
        'failure_transport' => 'failed',
        'serializer' => [
            'default_serializer' => 'messenger.transport.symfony_serializer',
        ],
        'transports' => [
            'default' => 'amqp://localhost/%2f/messages',
            'customised' => [
                'dsn' => 'amqp://localhost/%2f/messages?exchange_name=exchange_name',
                'options' => ['queue' => ['name' => 'Queue']],
                'serializer' => 'messenger.transport.native_php_serializer',
                'retry_strategy' => [
                    'max_retries' => 10,
                    'delay' => 7,
                    'multiplier' => 3,
                    'max_delay' => 100,
                ],
                'rate_limiter' => 'customised_worker'
            ],
            'failed' => 'in-memory:///',
            'redis' => 'redis://127.0.0.1:6379/messages',
            'beanstalkd' => 'beanstalkd://127.0.0.1:11300',
            'schedule' => 'schedule://default',
        ],
    ],
]);
