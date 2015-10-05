<?php

$container->loadFromExtension('framework', array(
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
