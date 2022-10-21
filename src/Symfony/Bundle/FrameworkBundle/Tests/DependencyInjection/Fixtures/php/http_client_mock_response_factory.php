<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'http_client' => [
        'default_options' => null,
        'mock_response_factory' => 'my_response_factory',
    ],
]);
