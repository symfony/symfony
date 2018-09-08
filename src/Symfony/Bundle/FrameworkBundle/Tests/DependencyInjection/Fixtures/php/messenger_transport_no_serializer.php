<?php

$container->loadFromExtension('framework', array(
    'serializer' => array(
        'enabled' => false,
    ),
    'messenger' => array(
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
