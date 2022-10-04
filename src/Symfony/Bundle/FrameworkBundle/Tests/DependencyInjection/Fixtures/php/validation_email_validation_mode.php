<?php

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'validation' => [
        'email_validation_mode' => 'html5',
    ],
]);
