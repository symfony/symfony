<?php

$container->loadFromExtension('framework', array(
    'form' => array(
        'enabled' => true,
        'field_name' => '_custom',
        'csrf_protection' => array(
            'enabled' => true,
        ),
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
