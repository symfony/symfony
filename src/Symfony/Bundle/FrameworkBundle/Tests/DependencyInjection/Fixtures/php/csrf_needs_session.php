<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'csrf_protection' => [
        'enabled' => true,
    ],
]);
