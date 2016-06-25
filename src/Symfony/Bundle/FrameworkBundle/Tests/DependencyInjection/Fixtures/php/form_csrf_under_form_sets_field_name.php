<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => array(
        'enabled' => true,
        'field_name' => '_custom',
    ),
    'form' => array(
        'enabled' => true,
        'csrf_protection' => array(
            'field_name' => '_custom_form',
        ),
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
