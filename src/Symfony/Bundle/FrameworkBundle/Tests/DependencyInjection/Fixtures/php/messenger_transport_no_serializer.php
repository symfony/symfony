<?php

$container->loadFromExtension('framework', array(
    'serializer' => array(
        'enabled' => false,
    ),
    'messenger' => array(
        'serializer' => 'messenger.transport.symfony_serializer',
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
