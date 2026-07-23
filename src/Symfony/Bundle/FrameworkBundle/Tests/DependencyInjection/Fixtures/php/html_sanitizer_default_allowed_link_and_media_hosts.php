<?php

$container->loadFromExtension('framework', [
    'html_sanitizer' => [
        'sanitizers' => [
            'custom_default' => null,
        ],
    ],
]);
