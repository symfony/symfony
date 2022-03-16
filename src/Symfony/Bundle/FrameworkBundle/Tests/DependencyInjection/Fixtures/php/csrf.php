<?php

$container->loadFromExtension('framework', [
    'csrf_protection' => true,
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => null,
    ],
]);
