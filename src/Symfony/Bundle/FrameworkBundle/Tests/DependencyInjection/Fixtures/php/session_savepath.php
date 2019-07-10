<?php

$container->loadFromExtension('framework', [
    'session' => [
        'handler_id' => null,
        'save_path' => '/some/path',
    ],
]);
