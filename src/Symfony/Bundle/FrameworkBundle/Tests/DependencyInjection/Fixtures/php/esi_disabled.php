<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'esi' => [
        'enabled' => false,
    ],
]);
