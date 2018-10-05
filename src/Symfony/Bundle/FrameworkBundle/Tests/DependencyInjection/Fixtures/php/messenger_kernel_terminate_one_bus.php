<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => false,
        'buses' => array(
            'a_bus' => null,
        ),
        'transports' => array(
            'kernel_terminate' => array(
                'dsn' => 'symfony://kernel.terminate',
            ),
        ),
    ),
));
