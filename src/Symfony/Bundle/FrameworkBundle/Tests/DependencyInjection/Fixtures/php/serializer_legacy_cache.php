<?php

$container->loadFromExtension('framework', [
    'serializer' => [
        'enabled' => true,
        'cache' => 'foo',
    ],
]);
