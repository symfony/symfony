<?php

$container->loadFromExtension('security', array(
    'providers' => array(
        'default' => array('id' => 'foo'),
    ),

    'firewalls' => array(
        'some_firewall' => array(
            'pattern' => '/secured_area/.*',
            'form_login' => array(
                'check_path' => '/some_area/login_check',
            )
        )
    )
));
