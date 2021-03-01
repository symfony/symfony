<?php

// To be removed in Symfony 6.0
$container->loadFromExtension('framework', [
    'session' => [
        'handler_id' => null,
        'cookie_secure' => 'auto',
    ],
]);
