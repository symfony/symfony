<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'default_bus' => 'messenger.bus.commands',
        'buses' => array(
            'messenger.bus.commands' => null,
            'messenger.bus.events' => array(
                'middleware' => array(
                    array('with_factory' => array('foo', true, array('bar' => 'baz'))),
                    'allow_no_handler',
                ),
            ),
            'messenger.bus.queries' => array(
                'default_middleware' => false,
                'middleware' => array(
                    'route_messages',
                    'allow_no_handler',
                    'call_message_handler',
                ),
            ),
        ),
    ),
));
