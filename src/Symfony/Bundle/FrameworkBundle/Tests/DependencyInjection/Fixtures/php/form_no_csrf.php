<?php

$container->loadFromExtension('framework', [
    'form' => [
        'csrf_protection' => [
            'enabled' => false,
        ],
    ],
]);
