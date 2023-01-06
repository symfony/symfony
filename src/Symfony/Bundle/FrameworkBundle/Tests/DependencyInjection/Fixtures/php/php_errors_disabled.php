<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'php_errors' => [
        'log' => false,
        'throw' => false,
    ],
]);
