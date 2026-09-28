<?php

$container->loadFromExtension('framework', [
    'request' => [
        'serializer' => 'serializer.request',
    ],
    'response' => [
        'serializer' => 'serializer.response',
    ],
]);
