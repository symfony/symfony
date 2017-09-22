<?php

$container->loadFromExtension('security', array(
    'providers' => array(
        'default' => array(
            'memory' => $memory = array(
                'users' => array('foo' => array('password' => 'foo', 'roles' => 'ROLE_USER')),
            ),
        ),
        'with-dash' => array(
            'memory' => $memory,
        ),
    ),
    'firewalls' => array(
        'main' => array(
            'provider' => 'default',
            'form_login' => true,
            'logout_on_user_change' => true,
        ),
        'other' => array(
            'provider' => 'with-dash',
            'form_login' => true,
            'logout_on_user_change' => true,
        ),
    ),
));
