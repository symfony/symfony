<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'csrf_protection' => [
        'enabled' => true,
    ],
]);
