<?php

$container->loadFromExtension('framework', [
    'http_client' => [
        'default_options' => null,
        'mock_response_factory' => 'my_response_factory',
    ],
]);
