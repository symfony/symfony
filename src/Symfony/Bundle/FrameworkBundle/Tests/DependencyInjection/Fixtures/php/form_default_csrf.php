<?php

$container->loadFromExtension('framework', [
    'form' => [
        'legacy_error_messages' => false,
    ],
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
        'handler_id' => null,
    ],
]);
