<?php

$container->loadFromExtension('framework', [
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'enable_annotations' => true,
    ],
]);
