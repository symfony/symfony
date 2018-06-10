<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => array(
            'enabled' => false,
        ),
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
