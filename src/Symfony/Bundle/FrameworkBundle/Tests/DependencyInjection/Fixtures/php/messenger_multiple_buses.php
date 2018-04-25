<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'default_bus' => 'commands',
        'buses' => array(
            'commands' => null,
            'events' => array(
                'middlewares' => array(
                    'tolerate_no_handler',
                ),
            ),
            'queries' => array(
                'default_middlewares' => false,
                'middlewares' => array(
                    'route_messages',
                    'tolerate_no_handler',
                    'call_message_handler',
                ),
            ),
        ),
    ),
));
