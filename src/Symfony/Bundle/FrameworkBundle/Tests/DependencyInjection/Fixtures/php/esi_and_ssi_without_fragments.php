<?php

$container->loadFromExtension('framework', [
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
