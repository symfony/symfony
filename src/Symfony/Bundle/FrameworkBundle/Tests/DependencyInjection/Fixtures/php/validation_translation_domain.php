<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'validation' => [
        'translation_domain' => 'messages',
    ],
]);
