<?php

$container->loadFromExtension('security', array(
    'access_decision_manager' => array(
        'service' => 'app.access_decision_manager',
        'strategy' => 'affirmative',
    ),
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
    ),
));
