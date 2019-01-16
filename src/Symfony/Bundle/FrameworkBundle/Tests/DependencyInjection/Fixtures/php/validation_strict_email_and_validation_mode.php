<?php

$container->loadFromExtension('framework', [
    'validation' => [
        'strict_email' => true,
        'email_validation_mode' => 'strict',
    ],
]);
