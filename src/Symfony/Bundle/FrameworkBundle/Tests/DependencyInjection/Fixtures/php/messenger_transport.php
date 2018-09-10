<?php

$container->loadFromExtension('framework', array(
    'serializer' => true,
    'messenger' => array(
        'serializer' => array(
            'format' => 'csv',
            'context' => array('enable_max_depth' => true),
        ),
        'transports' => array(
            'default' => 'amqp://localhost/%2f/messages',
        ),
    ),
));
