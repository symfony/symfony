<?php

$container->loadFromExtension('framework', [
    'serializer' => [
        'enabled' => false,
    ],
    'messenger' => [
        'serializer' => false,
    ],
]);
