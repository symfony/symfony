<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'webhook' => ['enabled' => true],
    'http_client' => ['enabled' => true],
    'serializer' => ['enabled' => true],
]);
