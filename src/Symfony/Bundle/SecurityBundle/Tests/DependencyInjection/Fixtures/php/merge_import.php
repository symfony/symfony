<?php

$container->loadFromExtension('security', array(
    'firewalls' => array(
        'main' => array(
            'form_login' => array(
                'login_path' => '/login',
            )
        )
    ),
    'role_hierarchy' => array(
        'FOO' => 'BAR',
        'ADMIN' => 'USER',
    ),
));
