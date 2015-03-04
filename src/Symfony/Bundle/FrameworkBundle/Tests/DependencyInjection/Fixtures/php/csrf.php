<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => true,
    'form' => array(
        'enabled' => true,
        'csrf_protection' => true,
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
