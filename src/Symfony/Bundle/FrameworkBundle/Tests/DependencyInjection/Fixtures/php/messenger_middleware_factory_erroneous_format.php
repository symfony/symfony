<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'buses' => array(
            'command_bus' => array(
                'middleware' => array(
                    array(
                        'foo' => array('qux'),
                        'bar' => array('baz'),
                    ),
                ),
            ),
        ),
    ),
));
