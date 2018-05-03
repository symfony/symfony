<?php

$container->loadFromExtension('framework', [
    'csrf_protection' => true,
    'form' => [
        'legacy_error_messages' => false,
    ],
    'session' => [
        'handler_id' => null,
    ],
]);
