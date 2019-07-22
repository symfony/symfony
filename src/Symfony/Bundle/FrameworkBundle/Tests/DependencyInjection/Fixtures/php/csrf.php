<?php

$container->loadFromExtension('framework', [
    'csrf_protection' => true,
    'form' => true,
    'session' => [
        'handler_id' => null,
    ],
]);
