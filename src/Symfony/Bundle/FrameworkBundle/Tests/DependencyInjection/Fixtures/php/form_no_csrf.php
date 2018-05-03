<?php

$container->loadFromExtension('framework', [
    'form' => [
        'csrf_protection' => [
            'enabled' => false,
        ],
        'legacy_error_messages' => false,
    ],
]);
