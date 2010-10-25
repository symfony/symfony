<?php

$container->loadFromExtension('security', 'config', array(
    'providers' => array(
        'basic' => array(
            'users' => array(
                'foo' => array('password' => 'foo', 'roles' => 'ROLE_USER'),
            ),
        ),
    ),

    'firewalls' => array(
        'simple' => array('pattern' => '/login', 'security' => false),
        'secure' => array('stateless' => true,
            'http_basic' => true,
            'http_digest' => true,
            'form_login' => true,
            'anonymous' => true,
            'switch_user' => true,
            'x509' => true,
            'logout' => true,
        ),
    )
));
