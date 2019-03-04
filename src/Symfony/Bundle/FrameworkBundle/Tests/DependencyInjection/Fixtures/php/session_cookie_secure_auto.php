<?php

$container->loadFromExtension('framework', [
    'session' => [
        'handler_id' => null,
        'cookie_secure' => 'auto',
    ],
]);
