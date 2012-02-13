<?php

$container->loadFromExtension('cache', array(
    'debug'    => '%kernel.debug%',
    'backends' => array(
        'memcached_be' => array(
            'memcached' => array(
                'servers' => array(
                    'memcached_server' => array(
                        'host'   => '127.0.0.1',
                        'port'   => 11211,
                        'weight' => 0
                    )
                )
            )
        )
    )
));
