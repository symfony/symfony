<?php

$container->loadFromExtension('security', array(
    'access_decision_manager' => array(
        'allow_if_all_abstain' => true,
        'allow_if_equal_granted_denied' => false,
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
