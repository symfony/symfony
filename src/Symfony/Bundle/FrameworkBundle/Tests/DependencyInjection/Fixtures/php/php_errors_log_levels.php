<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'php_errors' => [
        'log' => [
            \E_NOTICE => \Psr\Log\LogLevel::ERROR,
            \E_WARNING => \Psr\Log\LogLevel::ERROR,
        ],
    ],
]);
