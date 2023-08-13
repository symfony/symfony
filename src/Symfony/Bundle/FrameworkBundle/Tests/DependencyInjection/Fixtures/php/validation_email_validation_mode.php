<?php

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'validation' => [
        'email_validation_mode' => 'html5',
    ],
]);
