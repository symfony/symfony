<?php

$container->loadFromExtension('type_info', [
    'aliases' => [
        'CustomAlias' => 'int',
    ],
    'resolve_refinements' => true,
]);
