<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'php_errors' => [
        'log' => 8,
    ],
]);
