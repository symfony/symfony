<?php

$container->loadFromExtension('framework', array(
    'serializer' => array(
        'enabled' => false,
    ),
    'messenger' => array(
        'serializer' => array(
            'enabled' => true,
        ),
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
