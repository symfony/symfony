<?php

$container->loadFromExtension('framework', array(
    'form' => array(
        'enabled' => true,
        'csrf_protection' => true,
    ),
    'session' => array(
        'handler_id' => null,
    ),
));
