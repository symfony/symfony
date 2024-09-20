<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'fragments' => [
        'enabled' => true,
        'hinclude_default_template' => 'global_hinclude_template',
    ],
]);
