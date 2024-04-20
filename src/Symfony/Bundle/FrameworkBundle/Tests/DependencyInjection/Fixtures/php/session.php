<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => null,
        'cookie_secure' => false,
        'cookie_samesite' => 'lax',
    ],
]);
