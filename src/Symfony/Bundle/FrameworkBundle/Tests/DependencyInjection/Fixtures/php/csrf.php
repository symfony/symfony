<?php

$container->loadFromExtension('framework', array(
    'csrf_protection' => true,
    'form' => true,
    'session' => array(
        'handler_id' => null,
    ),
));
