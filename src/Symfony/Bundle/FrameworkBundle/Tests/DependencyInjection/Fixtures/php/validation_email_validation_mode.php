<?php

$container->loadFromExtension('framework', [
    'validation' => [
        'email_validation_mode' => 'html5-allow-no-tld',
        'phone_number_validation_mode' => 'strict',
    ],
]);
