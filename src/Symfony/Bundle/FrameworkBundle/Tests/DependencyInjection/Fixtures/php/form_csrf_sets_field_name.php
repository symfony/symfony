<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => array(
        'enabled' => true,
        'field_name' => '_custom',
    ),
    'form' => array(
        'enabled' => true,
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
