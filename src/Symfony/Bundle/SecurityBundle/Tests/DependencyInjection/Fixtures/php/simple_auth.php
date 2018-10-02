<?php

$container->loadFromExtension('security', array(
    'providers' => array(
        'default' => array(
            'memory' => array(
                'users' => array(
                    'foo' => array('password' => 'foo', 'roles' => 'ROLE_USER'),
                ),
            ),
        ),
    ),

    'firewalls' => array(
        'simple_auth' => array(
            'provider' => 'default',
            'anonymous' => true,
            'simple_form' => array('authenticator' => 'simple_authenticator'),
        ),
    ),
));
