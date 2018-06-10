<?php

$container->loadFromExtension('framework', array(
    'serializer' => true,
    'messenger' => array(
        'serializer' => true,
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
            'customised' => array(
                'dsn' => 'amqp://localhost/%2f/messages?exchange_name=exchange_name',
                'options' => array('queue' => array('name' => 'Queue')),
            ),
        ),
    ),
));
