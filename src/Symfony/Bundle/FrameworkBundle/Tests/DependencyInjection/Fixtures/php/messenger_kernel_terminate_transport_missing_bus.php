<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => false,
        'transports' => array(
            'kernel_terminate' => array(
                'dsn' => 'symfony://kernel.terminate',
                'options' => array('bus' => 'messenger.bus.commands'),
            ),
        ),
    ),
));
