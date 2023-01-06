<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => null,
        'cookie_secure' => 'auto',
    ],
]);
