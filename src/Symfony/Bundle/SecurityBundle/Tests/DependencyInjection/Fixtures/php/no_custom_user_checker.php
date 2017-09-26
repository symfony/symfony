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
        'simple' => array('pattern' => '/login', 'security' => false),
        'secure' => array(
            'stateless' => true,
            'http_basic' => true,
            'form_login' => true,
            'anonymous' => true,
            'switch_user' => true,
            'x509' => true,
            'remote_user' => true,
            'logout' => true,
            'remember_me' => array('secret' => 'TheSecret'),
            'user_checker' => null,
        ),
    ),
));
