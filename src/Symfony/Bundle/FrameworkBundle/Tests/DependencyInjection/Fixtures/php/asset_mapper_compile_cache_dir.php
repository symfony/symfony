<?php

$container->loadFromExtension('framework', [
    'asset_mapper' => [
        'paths' => ['assets/'],
        'compile_cache_dir' => '%kernel.build_dir%/asset_mapper',
    ],
    'assets' => false,
]);
