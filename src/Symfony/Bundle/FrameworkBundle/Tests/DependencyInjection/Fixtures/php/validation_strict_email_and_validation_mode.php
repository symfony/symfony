<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'strict_email' => true,
        'email_validation_mode' => 'strict',
    ),
));
