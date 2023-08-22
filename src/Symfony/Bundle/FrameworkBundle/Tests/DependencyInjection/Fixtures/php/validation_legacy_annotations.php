<?php

$container->loadFromExtension('framework', [
    'annotations' => [
        'enabled' => true,
    ],
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'secret' => 's3cr3t',
    'validation' => [
        'enabled' => true,
        'enable_attributes' => true,
        'email_validation_mode' => 'html5',
    ],
]);

$container->setAlias('validator.alias', 'validator')->setPublic(true);
