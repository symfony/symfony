<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'translator' => [
        'fallbacks' => ['en', 'fr'],
    ],
]);
