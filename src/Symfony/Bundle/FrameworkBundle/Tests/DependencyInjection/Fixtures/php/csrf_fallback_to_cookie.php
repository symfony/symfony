<?php

$container->loadFromExtension('framework', [
    'session' => false,
    'csrf_protection' => [
        'enabled' => true,
    ],
]);
