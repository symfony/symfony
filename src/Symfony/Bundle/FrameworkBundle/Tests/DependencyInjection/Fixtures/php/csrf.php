<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => array(
        'enabled' => false,
    ),
    'form' => array(
        'enabled' => true,
        'csrf_protection' => array(
            'enabled' => true,
        ),
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
