<?php

$container->loadFromExtension('framework', [
    'request' => [
        'serializer' => 'app.serializer',
    ],
]);
