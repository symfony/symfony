<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'static_method' => ['loadFoo', 'loadBar'],
    ],
]);
