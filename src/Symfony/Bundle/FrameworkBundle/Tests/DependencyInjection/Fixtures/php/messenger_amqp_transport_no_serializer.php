<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => false,
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
