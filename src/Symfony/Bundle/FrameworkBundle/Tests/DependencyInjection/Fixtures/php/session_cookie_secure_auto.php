<?php

$container->loadFromExtension('framework', [
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => null,
        'cookie_secure' => 'auto',
    ],
]);
