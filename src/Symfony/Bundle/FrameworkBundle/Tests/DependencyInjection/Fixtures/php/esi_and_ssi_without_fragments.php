<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'fragments' => [
        'enabled' => false,
    ],
    'esi' => [
        'enabled' => true,
    ],
    'ssi' => [
        'enabled' => true,
    ],
]);
