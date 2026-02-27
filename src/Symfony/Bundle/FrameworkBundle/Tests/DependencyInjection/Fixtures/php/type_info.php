<?php

$container->loadFromExtension('framework', [
    'type_info' => [
        'enabled' => true,
        'aliases' => [
            'CustomAlias' => 'int',
        ],
    ],
]);
