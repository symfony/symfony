<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'php_errors' => [
        'log' => true,
        'throw' => true,
    ],
]);
