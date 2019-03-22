<?php

$container->loadFromExtension('framework', [
    'templating' => [
        'cache' => '/path/to/cache',
        'engines' => ['php', 'twig'],
        'loader' => ['loader.foo', 'loader.bar'],
        'form' => [
            'resources' => ['theme1', 'theme2'],
        ],
        'hinclude_default_template' => 'global_hinclude_template',
    ],
    'assets' => null,
]);
